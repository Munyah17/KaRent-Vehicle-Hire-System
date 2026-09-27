<?php
namespace App\Services;

use App\Database;

/**
 * ZIMRA FDMS (Fiscalisation Data Management System) integration.
 *
 * PHP port of the essential parts of the open-source zimra-fdms TypeScript
 * SDK (https://github.com/munashe-chivandire/zimra-fdms) — same protocol:
 *   - ECDSA P-256 device key, CSR CN "ZIMRA-{serial}-{10-digit deviceID}"
 *   - Public registration endpoint; mTLS for all Device endpoints
 *   - Canonical signing strings + SHA-256 + ECDSA/DER signatures
 *   - Receipt counters, hash chain, fiscal-day counters, QR data
 *
 * Devices live in fdms_devices. Fiscalisation is optional:
 *   settings.fdms_enabled   = '1' master switch
 *   settings.fdms_scope     = 'all' | 'optin'  (optin = only clients with fiscalise=1)
 *   settings.fdms_device_id = fdms_devices.id of the default device
 *   settings.fdms_tax_id    = taxID used for hire receipts (from getConfig)
 *   settings.fdms_currency  = e.g. 'USD' | 'ZWG'
 */
final class FdmsService
{
    const BASE_URLS = [
        'test' => 'https://fdmsapitest.zimra.co.zw',
        'production' => 'https://fdmsapi.zimra.co.zw',
    ];

    private array $dev;
    private ?string $certFile = null;
    private ?string $keyFile = null;

    private function __construct(array $deviceRow)
    {
        $this->dev = $deviceRow;
    }

    public static function enabled(): bool
    {
        return setting('fdms_enabled', '0') === '1';
    }

    public static function scope(): string
    {
        return setting('fdms_scope', 'optin');
    }

    /** Default registered device (company/vendor level). */
    public static function defaultDevice(): ?self
    {
        $id = (int) setting('fdms_device_id', '0');
        $row = $id
            ? Database::one("SELECT * FROM fdms_devices WHERE id = ? AND status = 'registered'", [$id])
            : Database::one("SELECT * FROM fdms_devices WHERE status = 'registered' ORDER BY id LIMIT 1");
        return $row ? new self($row) : null;
    }

    /** Device for a specific client, if they have their own. */
    public static function forClient(int $clientId): ?self
    {
        $row = Database::one(
            "SELECT * FROM fdms_devices WHERE client_id = ? AND status = 'registered'", [$clientId]
        );
        return $row ? new self($row) : null;
    }

    /** Load any registered device row by its primary key. */
    public static function forDevice(int $id): ?self
    {
        $row = Database::one("SELECT * FROM fdms_devices WHERE id = ?", [$id]);
        return $row ? new self($row) : null;
    }

    public function row(): array { return $this->dev; }

    // ------------------------------------------------------------------
    // Crypto: keys, CSR, canonical signing
    // ------------------------------------------------------------------

    private static function cnf(): string
    {
        return APP_PATH . '/Vendor/openssl.cnf';
    }

    /** Generate a fresh EC P-256 keypair; returns [privateKeyPem, publicKeyPem]. */
    public static function generateKeyPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            'private_key_bits' => 384,
            'config' => self::cnf(),
        ]);
        if (!$key) {
            throw new \RuntimeException('openssl_pkey_new failed — check the openssl extension.');
        }
        openssl_pkey_export($key, $priv, null, ['config' => self::cnf()]);
        $details = openssl_pkey_get_details($key);
        return [$priv, $details['key']];
    }

    /** PEM CSR whose subject is only CN=ZIMRA-{serial}-{10-digit id}. */
    private static function buildCsr($key, string $serial, int $deviceId): string
    {
        $cn = sprintf('ZIMRA-%s-%s', $serial, str_pad((string) $deviceId, 10, '0', STR_PAD_LEFT));
        $csr = openssl_csr_new(['commonName' => $cn], $key, [
            'digest_alg' => 'sha256',
            'config' => self::cnf(),
        ]);
        if (!$csr) {
            throw new \RuntimeException('CSR generation failed.');
        }
        openssl_csr_export($csr, $pem, false);
        return $pem;
    }

    /** Sign a canonical string: hash = b64(sha256(s)); signature = b64(ECDSA DER). */
    private function sign(string $canonical): array
    {
        $key = openssl_pkey_get_private($this->dev['private_key_pem'] ?? '');
        if (!$key) throw new \RuntimeException('Device private key unusable.');
        if (!openssl_sign($canonical, $sig, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Signing failed.');
        }
        return [
            'hash' => base64_encode(hash('sha256', $canonical, true)),
            'signature' => base64_encode($sig),
        ];
    }

    // ------------------------------------------------------------------
    // Transport — JSON over HTTPS; Device endpoints use mutual TLS
    // ------------------------------------------------------------------

    private function baseUrl(): string
    {
        return self::BASE_URLS[$this->dev['environment'] ?? 'test'] ?? self::BASE_URLS['test'];
    }

    private function devicePath(string $endpoint): string
    {
        return sprintf('/Device/v1/%d/%s', (int) $this->dev['device_id'], $endpoint);
    }

    private function tlsFiles(): void
    {
        $dir = STORAGE_PATH . '/fdms';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $this->certFile = $dir . '/dev' . $this->dev['id'] . '-cert.pem';
        $this->keyFile = $dir . '/dev' . $this->dev['id'] . '-key.pem';
        file_put_contents($this->certFile, $this->dev['certificate_pem']);
        file_put_contents($this->keyFile, $this->dev['private_key_pem']);
        chmod($this->certFile, 0600);
        chmod($this->keyFile, 0600);
    }

    /**
     * @return array{status:int, body:array|null, error:?string}
     */
    private function request(string $method, string $path, ?array $body = null, bool $public = false): array
    {
        $ch = curl_init($this->baseUrl() . $path);
        $headers = [
            'DeviceID: ' . (int) $this->dev['device_id'],
            'DeviceModelName: ' . ($this->dev['model_name'] ?: 'Server'),
            'DeviceModelVersion: ' . ($this->dev['model_version'] ?: 'v1'),
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
        } else {
            $opts[CURLOPT_HTTPHEADER] = $headers;
            if ($method === 'POST') { $opts[CURLOPT_POST] = true; }
        }
        if (!$public && !empty($this->dev['certificate_pem'])) {
            $this->tlsFiles();
            $opts[CURLOPT_SSLCERT] = $this->certFile;
            $opts[CURLOPT_SSLKEY] = $this->keyFile;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $errno) {
            return ['status' => 0, 'body' => null, 'error' => 'Network error: ' . $err];
        }
        $rawBody = $headSize > 0 ? substr($raw, $headSize)
            : (str_contains($raw, "\r\n\r\n") ? substr($raw, strpos($raw, "\r\n\r\n") + 4) : $raw);
        $data = $rawBody !== '' ? json_decode($rawBody, true) : null;

        if ($code < 200 || $code >= 300) {
            $msg = $data['detail'] ?? $data['title'] ?? "HTTP $code";
            if (!empty($data['errorCode'])) $msg .= ' (errorCode: ' . $data['errorCode'] . ')';
            return ['status' => $code, 'body' => $data, 'error' => $msg];
        }
        return ['status' => $code, 'body' => $data, 'error' => null];
    }

    // ------------------------------------------------------------------
    // Registration (public endpoint, no mTLS)
    // ------------------------------------------------------------------

    /**
     * Register a new fiscal device. Stores keypair + issued certificate.
     * @return array{ok:bool, id:?int, error:?string}
     */
    public static function register(array $input): array
    {
        $deviceId = (int) ($input['device_id'] ?? 0);
        $serial = trim($input['serial_number'] ?? '');
        $activationKey = trim($input['activation_key'] ?? '');
        $env = in_array($input['environment'] ?? 'test', ['test', 'production'], true)
            ? $input['environment'] : 'test';
        if (!$deviceId || !$serial || !$activationKey) {
            return ['ok' => false, 'id' => null, 'error' => 'Device ID, serial and activation key are required.'];
        }

        try {
            [$privPem, $pubPem] = self::generateKeyPair();
            $key = openssl_pkey_get_private($privPem);
            $csr = self::buildCsr($key, $serial, $deviceId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => 'Key/CSR error: ' . $e->getMessage()];
        }

        $row = [
            'client_id' => !empty($input['client_id']) ? (int) $input['client_id'] : null,
            'label' => trim($input['label'] ?? ''),
            'device_id' => $deviceId,
            'serial_number' => $serial,
            'model_name' => trim($input['model_name'] ?? '') ?: 'Server',
            'model_version' => trim($input['model_version'] ?? '') ?: 'v1',
            'environment' => $env,
            'private_key_pem' => $privPem,
            'status' => 'pending',
        ];
        $devRowId = Database::insert(
            'INSERT INTO fdms_devices (client_id,label,device_id,serial_number,model_name,model_version,environment,private_key_pem,status)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$row['client_id'], $row['label'], $deviceId, $serial, $row['model_name'], $row['model_version'], $env, $privPem, 'pending']
        );
        $row['id'] = $devRowId;

        $svc = new self($row);
        $res = $svc->request('POST', sprintf('/Public/v1/%d/RegisterDevice', $deviceId), [
            'certificateRequest' => $csr,
            'activationKey' => strtoupper($activationKey),
        ], true);

        if ($res['error'] || empty($res['body']['certificate'])) {
            Database::run('UPDATE fdms_devices SET status=? WHERE id=?', ['error', $devRowId]);
            return ['ok' => false, 'id' => $devRowId, 'error' => $res['error'] ?? 'FDMS returned no certificate.'];
        }

        Database::run(
            "UPDATE fdms_devices SET certificate_pem=?, status='registered', registered_at=NOW() WHERE id=?",
            [$res['body']['certificate'], $devRowId]
        );
        return ['ok' => true, 'id' => $devRowId, 'error' => null];
    }

    // ------------------------------------------------------------------
    // Device calls
    // ------------------------------------------------------------------

    public function ping(): array { return $this->request('GET', $this->devicePath('Ping')); }
    public function getStatus(): array { return $this->request('GET', $this->devicePath('GetStatus')); }

    public function getConfig(): array
    {
        $res = $this->request('GET', $this->devicePath('GetConfig'));
        if (!$res['error'] && !empty($res['body']['qrUrl'])) {
            Database::run('UPDATE fdms_devices SET qr_url=? WHERE id=?', [$res['body']['qrUrl'], $this->dev['id']]);
            $this->dev['qr_url'] = $res['body']['qrUrl'];
        }
        return $res;
    }

    // ------------------------------------------------------------------
    // Fiscal-day state (JSON in fdms_devices.day_state / pending_receipt)
    // ------------------------------------------------------------------

    private function state(): ?array
    {
        return $this->dev['day_state'] ? json_decode($this->dev['day_state'], true) : null;
    }

    private function saveState(?array $s): void
    {
        Database::run('UPDATE fdms_devices SET day_state=? WHERE id=?', [
            $s ? json_encode($s) : null, $this->dev['id'],
        ]);
        $this->dev['day_state'] = $s ? json_encode($s) : null;
    }

    private function pending(): ?array
    {
        $pending = Database::value('SELECT pending_receipt FROM fdms_devices WHERE id=?', [$this->dev['id']]);
        return $pending ? json_decode($pending, true) : null;
    }

    private function setPending(?array $p): void
    {
        Database::run('UPDATE fdms_devices SET pending_receipt=? WHERE id=?', [
            $p ? json_encode($p) : null, $this->dev['id'],
        ]);
    }

    // ------------------------------------------------------------------
    // Fiscal day
    // ------------------------------------------------------------------

    public function openDay(): array
    {
        if ($this->pending()) {
            return ['ok' => false, 'error' => 'A receipt submit is pending — reconcile first.'];
        }
        $status = $this->getStatus();
        if ($status['error']) return ['ok' => false, 'error' => $status['error']];
        $dayStatus = $status['body']['fiscalDayStatus'] ?? '';
        if ($dayStatus !== 'FiscalDayClosed') {
            return ['ok' => false, 'error' => "Cannot open a day while status is $dayStatus."];
        }
        $res = $this->request('POST', $this->devicePath('OpenDay'), [
            'fiscalDayNo' => null,
            'fiscalDayOpened' => self::fdmsDateTime(),
        ]);
        if ($res['error']) return ['ok' => false, 'error' => $res['error']];

        $lastGlobal = max(
            (int) ($status['body']['lastReceiptGlobalNo'] ?? 0),
            (int) ($this->dev['last_receipt_global_no'] ?? 0)
        );
        $this->saveState([
            'fiscalDayNo' => (int) $res['body']['fiscalDayNo'],
            'fiscalDayDate' => date('Y-m-d'),
            'receiptCounter' => 0,
            'receiptGlobalNo' => $lastGlobal,
            'previousReceiptHash' => null,
            'previousReceiptDate' => null,
            'counters' => [],
        ]);
        return ['ok' => true, 'fiscalDayNo' => (int) $res['body']['fiscalDayNo']];
    }

    public function closeDay(): array
    {
        $s = $this->state();
        if (!$s) return ['ok' => false, 'error' => 'No open fiscal day locally.'];
        $counters = array_values(array_filter($s['counters'] ?? [], fn($c) => self::cents($c['fiscalCounterValue']) !== 0));
        $canonical = self::fiscalDayString((int) $this->dev['device_id'], $s['fiscalDayNo'], $s['fiscalDayDate'], $counters);
        $sig = $this->sign($canonical);
        $res = $this->request('POST', $this->devicePath('CloseDay'), [
            'fiscalDayNo' => $s['fiscalDayNo'],
            'fiscalDayCounters' => $counters,
            'fiscalDayDeviceSignature' => $sig,
            'receiptCounter' => $s['receiptCounter'],
        ]);
        if ($res['error']) return ['ok' => false, 'error' => $res['error']];
        $this->saveState(null);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Receipts
    // ------------------------------------------------------------------

    /**
     * Build, sign and submit one receipt. State persists before submit so a
     * network failure leaves a pending marker for reconcile().
     * @param array $lines   [name, price(float, tax-inclusive), qty, taxId, taxPercent, taxCode]
     * @param array $payments [moneyType => amount]
     */
    public function submitReceipt(string $invoiceNo, array $lines, array $payments, ?array $buyer = null, string $currency = 'USD'): array
    {
        $s = $this->state();
        if (!$s) return ['ok' => false, 'error' => 'No fiscal day open. Open a day first.'];
        if ($this->pending()) return ['ok' => false, 'error' => 'A submit is pending — reconcile first.'];

        // receiptDate must strictly increase (RCPT030)
        $ts = time();
        if (!empty($s['previousReceiptDate']) && date('Y-m-d\TH:i:s', $ts) <= $s['previousReceiptDate']) {
            $ts = strtotime($s['previousReceiptDate']) + 1;
        }
        $date = date('Y-m-d\TH:i:s', $ts);

        $rLines = [];
        $i = 0;
        foreach ($lines as $l) {
            $priceC = self::cents((float) $l['price']);
            $rLines[] = [
                'receiptLineType' => 'Sale',
                'receiptLineNo' => ++$i,
                'receiptLineName' => $l['name'],
                'receiptLinePrice' => $priceC / 100,
                'receiptLineQuantity' => (float) $l['qty'],
                'receiptLineTotal' => ($priceC * (float) $l['qty']) / 100,
                'taxCode' => $l['taxCode'] ?? null,
                'taxPercent' => $l['taxPercent'] ?? null,
                'taxID' => (int) $l['taxId'],
            ];
        }
        $taxes = self::buildReceiptTaxes($rLines, true);
        $totalC = 0;
        foreach ($rLines as $l) $totalC += self::cents($l['receiptLineTotal']);

        $payRows = [];
        $payTotal = 0;
        foreach ($payments as $moneyType => $amount) {
            $payTotal += self::cents((float) $amount);
            $payRows[] = ['moneyTypeCode' => $moneyType, 'paymentAmount' => self::cents((float) $amount) / 100];
        }
        if ($payTotal !== $totalC) {
            return ['ok' => false, 'error' => 'Payments total does not equal receipt total.'];
        }

        $unsigned = [
            'receiptType' => 'FiscalInvoice',
            'receiptCurrency' => strtoupper($currency),
            'receiptCounter' => $s['receiptCounter'] + 1,
            'receiptGlobalNo' => $s['receiptGlobalNo'] + 1,
            'invoiceNo' => $invoiceNo,
            'buyerData' => $buyer,
            'receiptNotes' => null,
            'receiptDate' => $date,
            'creditDebitNote' => null,
            'receiptLinesTaxInclusive' => true,
            'receiptLines' => $rLines,
            'receiptTaxes' => $taxes,
            'receiptPayments' => $payRows,
            'receiptTotal' => $totalC / 100,
            'receiptPrintForm' => 'Receipt48',
        ];
        $canonical = self::receiptString((int) $this->dev['device_id'], $unsigned, $s['previousReceiptHash']);
        $unsigned['receiptDeviceSignature'] = $this->sign($canonical);
        $receipt = $unsigned;

        $stateAfter = $s;
        $stateAfter['receiptCounter']++;
        $stateAfter['receiptGlobalNo']++;
        $stateAfter['previousReceiptHash'] = $unsigned['receiptDeviceSignature']['hash'];
        $stateAfter['previousReceiptDate'] = $date;
        $stateAfter['counters'] = self::accumulate($s['counters'] ?? [], $receipt);

        $this->setPending(['receipt' => $receipt, 'stateAfter' => $stateAfter, 'date' => $date]);

        $res = $this->request('POST', $this->devicePath('SubmitReceipt'), ['receipt' => $receipt]);
        if ($res['error'] && $res['status'] === 0) {
            return ['ok' => false, 'pending' => true, 'error' => $res['error']]; // network — keeps marker
        }
        if ($res['error']) {
            $this->setPending(null); // FDMS refused — not pending, counters not advanced
            return ['ok' => false, 'error' => $res['error']];
        }

        $this->saveState($stateAfter);
        $this->setPending(null);
        Database::run('UPDATE fdms_devices SET last_receipt_global_no=? WHERE id=?', [$stateAfter['receiptGlobalNo'], $this->dev['id']]);

        $qrUrl = $this->dev['qr_url'] ?? '';
        $qr = $qrUrl ? self::qrData($qrUrl, (int) $this->dev['device_id'], $date, $receipt['receiptGlobalNo'], $receipt['receiptDeviceSignature']['signature']) : null;

        return [
            'ok' => true,
            'receipt' => $receipt,
            'receipt_id' => $res['body']['receiptID'] ?? null,
            'qr' => $qr,
            'validation_errors' => $res['body']['validationErrors'] ?? [],
        ];
    }

    /** Settle a pending submit against FDMS (called on open/startup). */
    public function reconcile(): string
    {
        $pending = $this->pending();
        if (!$pending) return 'none';
        $g = $pending['receipt']['receiptGlobalNo'];
        $status = $this->getStatus();
        if (!$status['error'] && ($status['body']['lastReceiptGlobalNo'] ?? 0) >= $g) {
            $this->saveState($pending['stateAfter']);
            $this->setPending(null);
            return 'confirmed';
        }
        $res = $this->request('POST', $this->devicePath('SubmitReceipt'), ['receipt' => $pending['receipt']]);
        if ($res['error']) {
            if ($res['status'] !== 0) $this->setPending(null);
            return $res['status'] === 0 ? 'still-pending' : 'rejected';
        }
        $this->saveState($pending['stateAfter']);
        $this->setPending(null);
        return 'resubmitted';
    }

    // ------------------------------------------------------------------
    // Canonical strings (port of the SDK's signing.ts — load-bearing)
    // ------------------------------------------------------------------

    private static function cents(float $amount): int
    {
        return (int) round($amount * 100 + ($amount >= 0 ? 1e-9 : -1e-9) * 100);
    }

    private static function fdmsDateTime(?int $ts = null): string
    {
        return date('Y-m-d\TH:i:s', $ts ?? time());
    }

    private static function compareTaxes(array $a, array $b): int
    {
        if ($a['taxID'] !== $b['taxID']) return $a['taxID'] <=> $b['taxID'];
        return strcmp($a['taxCode'] ?? '', $b['taxCode'] ?? '');
    }

    private static function taxesString(array $taxes): string
    {
        usort($taxes, [self::class, 'compareTaxes']);
        $out = '';
        foreach ($taxes as $t) {
            $out .= ($t['taxCode'] ?? '') .
                ($t['taxPercent'] !== null ? number_format((float) $t['taxPercent'], 2, '.', '') : '') .
                self::cents((float) $t['taxAmount']) .
                self::cents((float) $t['salesAmountWithTax']);
        }
        return $out;
    }

    private static function receiptString(int $deviceId, array $r, ?string $prevHash): string
    {
        return $deviceId .
            strtoupper($r['receiptType']) .
            strtoupper($r['receiptCurrency']) .
            $r['receiptGlobalNo'] .
            $r['receiptDate'] .
            self::cents((float) $r['receiptTotal']) .
            self::taxesString($r['receiptTaxes']) .
            ($prevHash ?? '');
    }

    private static function fiscalDayString(int $deviceId, int $dayNo, string $dayDate, array $counters): string
    {
        static $typeOrder = [
            'SaleByTax' => 1, 'SaleTaxByTax' => 2, 'CreditNoteByTax' => 3,
            'CreditNoteTaxByTax' => 4, 'DebitNoteByTax' => 5, 'DebitNoteTaxByTax' => 6,
            'BalanceByMoneyType' => 7,
        ];
        static $moneyOrder = [
            'Cash' => 0, 'Card' => 1, 'MobileWallet' => 2, 'Coupon' => 3,
            'Credit' => 4, 'BankTransfer' => 5, 'Other' => 6,
        ];
        $nz = array_values(array_filter($counters, fn($c) => self::cents((float) $c['fiscalCounterValue']) !== 0));
        usort($nz, function ($a, $b) use ($typeOrder, $moneyOrder) {
            $pa = $typeOrder[$a['fiscalCounterType']] ?? 99;
            $pb = $typeOrder[$b['fiscalCounterType']] ?? 99;
            if ($pa !== $pb) return $pa <=> $pb;
            $c = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
            if ($c !== 0) return $c;
            $ta = $a['fiscalCounterTaxID'] ?? -1;
            $tb = $b['fiscalCounterTaxID'] ?? -1;
            if ($ta !== $tb) return $ta <=> $tb;
            return ($moneyOrder[$a['fiscalCounterMoneyType'] ?? ''] ?? 99) <=> ($moneyOrder[$b['fiscalCounterMoneyType'] ?? ''] ?? 99);
        });
        $counterStr = '';
        foreach ($nz as $c) {
            $counterStr .= strtoupper($c['fiscalCounterType']) .
                strtoupper($c['fiscalCounterCurrency']) .
                (isset($c['fiscalCounterTaxPercent']) && $c['fiscalCounterTaxPercent'] !== null
                    ? number_format((float) $c['fiscalCounterTaxPercent'], 2, '.', '') : '') .
                (isset($c['fiscalCounterMoneyType']) && $c['fiscalCounterMoneyType']
                    ? strtoupper($c['fiscalCounterMoneyType']) : '') .
                self::cents((float) $c['fiscalCounterValue']);
        }
        return $deviceId . $dayNo . $dayDate . $counterStr;
    }

    /** Group lines by tax and compute the receipt tax summary (integer cents). */
    private static function buildReceiptTaxes(array $lines, bool $inclusive): array
    {
        $groups = [];
        foreach ($lines as $l) {
            $k = $l['taxID'] . '|' . ($l['taxPercent'] ?? '') . '|' . ($l['taxCode'] ?? '');
            if (!isset($groups[$k])) {
                $groups[$k] = [
                    'taxCode' => $l['taxCode'] ?? null,
                    'taxPercent' => $l['taxPercent'] ?? null,
                    'taxID' => $l['taxID'],
                    '_tax' => 0, '_sales' => 0,
                ];
            }
            $totalC = self::cents($l['receiptLineTotal']);
            $rate = (($l['taxPercent'] ?? 0) / 100);
            $taxC = $inclusive ? (int) round($totalC - $totalC / (1 + $rate)) : (int) round($totalC * $rate);
            $groups[$k]['_tax'] += $taxC;
            $groups[$k]['_sales'] += $inclusive ? $totalC : $totalC + $taxC;
        }
        $taxes = [];
        foreach ($groups as $g) {
            $taxes[] = [
                'taxCode' => $g['taxCode'],
                'taxPercent' => $g['taxPercent'],
                'taxID' => $g['taxID'],
                'taxAmount' => $g['_tax'] / 100,
                'salesAmountWithTax' => $g['_sales'] / 100,
            ];
        }
        usort($taxes, [self::class, 'compareTaxes']);
        return $taxes;
    }

    /** Fold a receipt into the running fiscal-day counters. */
    private static function accumulate(array $counters, array $r): array
    {
        $currency = $r['receiptCurrency'];
        $sign = $r['receiptType'] === 'CreditNote' ? -1 : 1;
        $byTax = $r['receiptType'] === 'FiscalInvoice' ? 'SaleByTax'
            : ($r['receiptType'] === 'CreditNote' ? 'CreditNoteByTax' : 'DebitNoteByTax');
        $taxByTax = $r['receiptType'] === 'FiscalInvoice' ? 'SaleTaxByTax'
            : ($r['receiptType'] === 'CreditNote' ? 'CreditNoteTaxByTax' : 'DebitNoteTaxByTax');

        $upsert = function (callable $match, array $create, float $delta) use (&$counters) {
            foreach ($counters as &$c) {
                if ($match($c)) {
                    $c['fiscalCounterValue'] = (self::cents((float) $c['fiscalCounterValue']) + self::cents($delta)) / 100;
                    return;
                }
            }
            $create['fiscalCounterValue'] = self::cents($delta) / 100;
            $counters[] = $create;
        };

        foreach ($r['receiptTaxes'] as $t) {
            $upsert(
                fn($c) => $c['fiscalCounterType'] === $byTax && $c['fiscalCounterCurrency'] === $currency && ($c['fiscalCounterTaxID'] ?? null) === $t['taxID'],
                ['fiscalCounterType' => $byTax, 'fiscalCounterCurrency' => $currency, 'fiscalCounterTaxID' => $t['taxID'], 'fiscalCounterTaxPercent' => $t['taxPercent'], 'fiscalCounterValue' => 0],
                $sign * $t['salesAmountWithTax']
            );
            $upsert(
                fn($c) => $c['fiscalCounterType'] === $taxByTax && $c['fiscalCounterCurrency'] === $currency && ($c['fiscalCounterTaxID'] ?? null) === $t['taxID'],
                ['fiscalCounterType' => $taxByTax, 'fiscalCounterCurrency' => $currency, 'fiscalCounterTaxID' => $t['taxID'], 'fiscalCounterTaxPercent' => $t['taxPercent'], 'fiscalCounterValue' => 0],
                $sign * $t['taxAmount']
            );
        }
        foreach ($r['receiptPayments'] as $p) {
            $upsert(
                fn($c) => $c['fiscalCounterType'] === 'BalanceByMoneyType' && $c['fiscalCounterCurrency'] === $currency && ($c['fiscalCounterMoneyType'] ?? null) === $p['moneyTypeCode'],
                ['fiscalCounterType' => 'BalanceByMoneyType', 'fiscalCounterCurrency' => $currency, 'fiscalCounterMoneyType' => $p['moneyTypeCode'], 'fiscalCounterValue' => 0],
                $sign * $p['paymentAmount']
            );
        }
        return $counters;
    }

    /** QR payload for the printed fiscal receipt. */
    public static function qrData(string $qrUrl, int $deviceId, string $receiptDate, int $globalNo, string $sigB64): string
    {
        $md5_16 = substr(md5(base64_decode($sigB64), true), 0, 16);
        $base = str_ends_with($qrUrl, '/') ? $qrUrl : $qrUrl . '/';
        return $base .
            str_pad((string) $deviceId, 10, '0', STR_PAD_LEFT) .
            date('dmY', strtotime($receiptDate)) .
            str_pad((string) $globalNo, 10, '0', STR_PAD_LEFT) .
            $md5_16;
    }

    // ------------------------------------------------------------------
    // App hook: issue a fiscal receipt for a successful payment
    // ------------------------------------------------------------------

    /** Money-type mapping for our payment methods. */
    public static function moneyType(string $method): string
    {
        return match (strtolower($method)) {
            'cash' => 'Cash',
            'card', 'visa', 'mastercard' => 'Card',
            'paynow', 'ecocash', 'onemoney', 'innbucks' => 'MobileWallet',
            'wallet' => 'Credit',
            'bank', 'transfer' => 'BankTransfer',
            default => 'Other',
        };
    }

    /**
     * Issue a fiscal receipt for a payment when enabled and the client has
     * opted in (or scope is 'all'). Never throws; failures are recorded in
     * fdms_receipts for retry from the admin console.
     */
    public static function issueForPayment(int $paymentId, ?int $receiptRowId = null): void
    {
        if (!self::enabled()) return;

        $p = Database::one(
            'SELECT p.*, b.ref bref, c.full_name, c.tax_no, c.fiscalise
             FROM payments p LEFT JOIN bookings b ON b.id=p.booking_id
             LEFT JOIN clients c ON c.id=p.client_id WHERE p.id=?', [$paymentId]
        );
        if (!$p) return;
        if (self::scope() === 'optin' && !(int) ($p['fiscalise'] ?? 0)) return;

        $dev = self::forClient((int) ($p['client_id'] ?? 0)) ?? self::defaultDevice();
        if (!$dev) {
            Database::run(
                'INSERT INTO fdms_receipts (fdms_device_id, payment_id, booking_id, invoice_no, status, error) VALUES (NULL,?,?,?,?,?)',
                [$p['id'], $p['booking_id'] ?? null, $p['txn_id'] ?? null, 'failed', 'No registered fiscal device.']
            );
            return;
        }

        if ($receiptRowId) {
            $rowId = $receiptRowId;
        } else {
            Database::run(
                'INSERT INTO fdms_receipts (fdms_device_id, payment_id, booking_id, invoice_no, status, error) VALUES (?,?,?,?,?,?)',
                [$dev->row()['id'], $p['id'], $p['booking_id'] ?? null, $p['txn_id'] ?? null, 'pending', null]
            );
            $rowId = (int) Database::pdo()->lastInsertId();
        }
        try {
            $dev->reconcile();
            $taxId = (int) setting('fdms_tax_id', '0');
            $currency = setting('fdms_currency', config('currency', 'USD'));
            $taxPercent = null;
            if ($taxId) {
                $cfg = $dev->getConfig();
                foreach (($cfg['body']['applicableTaxes'] ?? []) as $t) {
                    if ((int) $t['taxID'] === $taxId) { $taxPercent = $t['taxPercent']; break; }
                }
            }
            $buyer = ['buyerRegisterName' => $p['full_name'] ?? 'Customer'];
            if (!empty($p['tax_no'])) $buyer['buyerTIN'] = $p['tax_no'];

            $desc = 'Vehicle hire ' . ($p['bref'] ?? $p['txn_id']) . ' — ' . ucfirst($p['purpose'] ?? 'payment');
            $res = $dev->submitReceipt(
                $p['txn_id'],
                [['name' => $desc, 'price' => (float) $p['amount'], 'qty' => 1, 'taxId' => $taxId, 'taxPercent' => $taxPercent]],
                [self::moneyType($p['method']) => (float) $p['amount']],
                $buyer,
                $currency
            );
            if ($res['ok']) {
                self::logReceipt($res, 'submitted', null, $rowId);
            } else {
                self::logReceipt(null, 'failed', $res['error'] ?? 'unknown', $rowId);
            }
        } catch (\Throwable $e) {
            self::logReceipt(null, 'failed', $e->getMessage(), $rowId);
        }
    }

    private static function logReceipt(?array $res, string $status, ?string $error, int $rowId): void
    {
        Database::run(
            'UPDATE fdms_receipts SET status=?, error=?, receipt_json=?, qr_data=?, server_receipt_id=?,
                receipt_global_no=?, receipt_counter=? WHERE id=?',
            [$status, $error, isset($res['receipt']) ? json_encode($res['receipt']) : null,
             $res['qr'] ?? null, $res['receipt_id'] ?? null,
             $res['receipt']['receiptGlobalNo'] ?? null, $res['receipt']['receiptCounter'] ?? null, $rowId]
        );
    }

    /** Retry a failed/pending receipt row (admin action). */
    public static function retry(int $receiptRowId): array
    {
        $row = Database::one('SELECT * FROM fdms_receipts WHERE id=?', [$receiptRowId]);
        if (!$row || !$row['payment_id']) return ['ok' => false, 'error' => 'Receipt not found.'];
        self::issueForPayment((int) $row['payment_id'], $receiptRowId);
        $after = Database::one('SELECT status, error FROM fdms_receipts WHERE id=?', [$receiptRowId]);
        return ['ok' => $after['status'] === 'submitted', 'error' => $after['error']];
    }
}
