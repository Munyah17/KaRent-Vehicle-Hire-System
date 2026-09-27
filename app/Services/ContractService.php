<?php
namespace App\Services;

use App\Audit;
use App\Database;

/**
 * Renders templates with {{placeholders}} and stores the rendered result so
 * that historical contracts are never affected by later template edits.
 */
final class ContractService
{
    public static function context(int $bookingId): array
    {
        $b = BookingService::find($bookingId);
        if (!$b) return [];
        $deposit = DepositService::forBooking($bookingId);
        return [
            'company_name'    => setting('company_name', config('company_name')),
            'company_email'   => setting('company_email', ''),
            'company_phone'   => setting('company_phone', ''),
            'company_address' => setting('company_address', ''),
            'client_name'     => $b['client_name'],
            'client_no'       => $b['client_no'],
            'client_id'       => $b['client_no'],
            'booking_ref'     => $b['ref'],
            'vehicle_make'    => $b['make'],
            'vehicle_model'   => $b['model'],
            'vehicle_year'    => (string) Database::value(
                'SELECT year FROM vehicles WHERE id = ?', [$b['vehicle_id']]),
            'registration'    => $b['reg_no'],
            'pickup_date'     => fmt_datetime($b['pickup_at']),
            'return_date'     => fmt_datetime($b['return_at']),
            'rental_amount'   => money($b['total']),
            'deposit_amount'  => money($deposit['required_amount'] ?? $b['deposit_required']),
            'outstanding'     => money(BookingService::outstanding($bookingId)),
            'mileage'         => (string) (Database::value(
                'SELECT mileage FROM vehicles WHERE id = ?', [$b['vehicle_id']]) ?? '—'),
            'fuel_level'      => '—',
            'extra_notes'     => $b['notes'] ?? '',
            'generated_date'  => date('d M Y'),
        ];
    }

    public static function render(string $body, array $context): string
    {
        foreach ($context as $key => $value) {
            // Escape substituted values — template HTML stays, data stays text.
            $body = str_replace('{{' . $key . '}}', e((string) $value), $body);
        }
        // Remove any leftover placeholders.
        return (string) preg_replace('/\{\{[a-z_]+\}\}/', '—', $body);
    }

    /** Generate (or regenerate if not yet signed) a contract for a booking. */
    public static function generate(int $bookingId, string $templateCode, ?int $staffId): array
    {
        $template = Database::one(
            'SELECT * FROM contract_templates WHERE code = ? AND is_active = 1',
            [$templateCode]
        );
        if (!$template) return [null, 'Template not found.'];

        $context = self::context($bookingId);
        if (!$context) return [null, 'Booking not found.'];
        $body = self::render($template['body'], $context);
        $b = Database::one('SELECT client_id FROM bookings WHERE id = ?', [$bookingId]);

        $existing = Database::one(
            "SELECT id, status FROM contracts
             WHERE booking_id = ? AND template_code = ? AND status = 'generated'",
            [$bookingId, $templateCode]
        );
        if ($existing) {
            Database::run('UPDATE contracts SET body = ?, template_version = ? WHERE id = ?',
                [$body, $template['version'], $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = Database::insert(
                'INSERT INTO contracts
                 (booking_id, client_id, template_id, template_code, template_version,
                  title, body, created_by)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$bookingId, $b['client_id'], $template['id'], $templateCode,
                 $template['version'], $template['name'], $body, $staffId]
            );
        }
        Audit::log($staffId, 'generate_contract', 'contracts', 'contract', $id);
        return [$id, null];
    }

    public static function sign(int $contractId, string $signerName, string $signatureData, ?int $staffId): array
    {
        $c = Database::one('SELECT * FROM contracts WHERE id = ?', [$contractId]);
        if (!$c) return [false, 'Contract not found.'];
        if ($c['status'] === 'signed') return [false, 'Contract already signed.'];
        Database::begin();
        try {
            Database::run(
                'INSERT INTO signatures (contract_id, signer_name, signature_data, staff_id, ip)
                 VALUES (?,?,?,?,?)',
                [$contractId, $signerName, $signatureData, $staffId, client_ip()]
            );
            Database::run("UPDATE contracts SET status = 'signed' WHERE id = ?", [$contractId]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            return [false, 'Could not record signature.'];
        }
        Audit::log($staffId, 'sign_contract', 'contracts', 'contract', $contractId);
        return [true, null];
    }
}
