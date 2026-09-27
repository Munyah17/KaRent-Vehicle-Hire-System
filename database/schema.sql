-- ============================================================
-- Vehicle Hire Management System — MySQL Schema
-- Simple/Commercial Version. All money uses DECIMAL; no floats.
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Access control ----------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    module VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(40) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE user_permissions (
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts (email, ip, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_token (token_hash)
) ENGINE=InnoDB;

-- ---------- Clients ----------
CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL UNIQUE,
    client_no VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(160) NOT NULL,
    dob DATE DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    national_id VARCHAR(60) DEFAULT NULL,
    licence_no VARCHAR(60) DEFAULT NULL,
    licence_expiry DATE DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    kyc_status ENUM('pending','under_review','verified','rejected') NOT NULL DEFAULT 'pending',
    account_status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    source ENUM('online','walk_in') NOT NULL DEFAULT 'online',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_clients_phone (phone),
    INDEX idx_clients_email (email),
    INDEX idx_clients_national_id (national_id),
    INDEX idx_clients_kyc (kyc_status)
) ENGINE=InnoDB;

CREATE TABLE client_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    doc_type ENUM('national_id','drivers_licence','passport','proof_of_address','other') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_client_docs (client_id, doc_type)
) ENGINE=InnoDB;

-- ---------- Vehicles ----------
CREATE TABLE vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reg_no VARCHAR(30) NOT NULL UNIQUE,
    make VARCHAR(60) NOT NULL,
    model VARCHAR(60) NOT NULL,
    year SMALLINT UNSIGNED DEFAULT NULL,
    colour VARCHAR(40) DEFAULT NULL,
    transmission ENUM('automatic','manual') NOT NULL DEFAULT 'automatic',
    fuel_type ENUM('petrol','diesel','hybrid','electric') NOT NULL DEFAULT 'petrol',
    engine_capacity VARCHAR(20) DEFAULT NULL,
    seats TINYINT UNSIGNED NOT NULL DEFAULT 5,
    mileage INT UNSIGNED NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    weekly_rate DECIMAL(10,2) DEFAULT NULL,
    monthly_rate DECIMAL(10,2) DEFAULT NULL,
    deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    excess_mileage_rate DECIMAL(8,2) DEFAULT NULL,
    status ENUM('available','reserved','on_hire','maintenance','unavailable') NOT NULL DEFAULT 'available',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vehicles_status (status),
    INDEX idx_vehicles_public (is_public, status)
) ENGINE=InnoDB;

CREATE TABLE vehicle_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    INDEX idx_vp_vehicle (vehicle_id)
) ENGINE=InnoDB;

CREATE TABLE vehicle_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    doc_type ENUM('registration','insurance','licence','other') NOT NULL,
    title VARCHAR(120) DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    expiry_date DATE DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Future-proofing: a vehicle may have devices (GPS tracker, dashcam...) later.
CREATE TABLE vehicle_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    device_type VARCHAR(40) NOT NULL,
    identifier VARCHAR(120) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    installed_at DATE DEFAULT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Suppliers / expenses ----------
CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    contact_person VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    service_category VARCHAR(80) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Bookings ----------
CREATE TABLE bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(20) NOT NULL UNIQUE,
    client_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    pickup_at DATETIME NOT NULL,
    return_at DATETIME NOT NULL,
    status ENUM('pending','confirmed','active','completed','cancelled','overdue') NOT NULL DEFAULT 'pending',
    base_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    additional_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_required DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    source ENUM('online','walk_in') NOT NULL DEFAULT 'online',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bookings_client (client_id),
    INDEX idx_bookings_vehicle_dates (vehicle_id, pickup_at, return_at),
    INDEX idx_bookings_status (status),
    INDEX idx_bookings_dates (pickup_at, return_at)
) ENGINE=InnoDB;

CREATE TABLE booking_charges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    label VARCHAR(160) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE booking_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    changed_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bsh (booking_id)
) ENGINE=InnoDB;

CREATE TABLE booking_extensions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    old_return_at DATETIME NOT NULL,
    new_return_at DATETIME NOT NULL,
    additional_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_by INT UNSIGNED DEFAULT NULL,
    decided_by INT UNSIGNED DEFAULT NULL,
    decided_at DATETIME DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Payments / deposits / wallets ----------
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    txn_id VARCHAR(40) NOT NULL UNIQUE,
    booking_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('cash','bank_transfer','paynow','card','wallet','other') NOT NULL,
    reference VARCHAR(120) DEFAULT NULL,
    purpose ENUM('rental','deposit','topup','extension','other') NOT NULL DEFAULT 'rental',
    status ENUM('pending','successful','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    poll_url VARCHAR(255) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payments_booking (booking_id, status),
    INDEX idx_payments_client (client_id),
    INDEX idx_payments_status (status)
) ENGINE=InnoDB;

CREATE TABLE deposits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL UNIQUE,
    client_id INT UNSIGNED NOT NULL,
    required_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    received_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deducted_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','partial','held','released','forfeited') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE deposit_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deposit_id INT UNSIGNED NOT NULL,
    type ENUM('received','deduction','refund','adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(160) DEFAULT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE wallets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Wallet balance is DERIVED from this ledger (SUM of signed amounts).
CREATE TABLE wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT UNSIGNED NOT NULL,
    ref VARCHAR(40) NOT NULL UNIQUE,
    type ENUM('topup','booking_payment','refund','adjustment','debit','credit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,          -- positive = credit, negative = debit
    description VARCHAR(255) DEFAULT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    booking_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_wallet_txn (wallet_id, created_at)
) ENGINE=InnoDB;

-- ---------- Contracts / documents ----------
CREATE TABLE contract_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Generated contracts preserve the exact rendered body — template edits never
-- alter historical documents.
CREATE TABLE contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED DEFAULT NULL,
    template_code VARCHAR(40) NOT NULL,
    template_version INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('generated','signed','void') NOT NULL DEFAULT 'generated',
    file_path VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_contracts_booking (booking_id)
) ENGINE=InnoDB;

CREATE TABLE signatures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    signer_name VARCHAR(160) NOT NULL,
    signature_data MEDIUMTEXT DEFAULT NULL,
    signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    staff_id INT UNSIGNED DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Checklists ----------
CREATE TABLE checklist_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(40) NOT NULL,           -- EXTERIOR / INTERIOR
    label VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE checklists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    type ENUM('collection','return') NOT NULL,
    mileage INT UNSIGNED DEFAULT NULL,
    fuel_level TINYINT UNSIGNED DEFAULT NULL, -- 0-100 %
    condition_notes TEXT DEFAULT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    client_ack_name VARCHAR(160) DEFAULT NULL,
    client_ack_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_checklist (booking_id, type)
) ENGINE=InnoDB;

CREATE TABLE checklist_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    status ENUM('good','damaged','missing','na') NOT NULL DEFAULT 'good',
    comment VARCHAR(255) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES checklist_items(id),
    UNIQUE KEY uq_result (checklist_id, item_id)
) ENGINE=InnoDB;

-- ---------- Maintenance / damage / expenses ----------
CREATE TABLE maintenance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    maint_type VARCHAR(80) NOT NULL,         -- Service, Tyres, Repair, Inspection...
    maint_date DATE NOT NULL,
    mileage INT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier_id INT UNSIGNED DEFAULT NULL,
    receipt VARCHAR(255) DEFAULT NULL,
    next_service_date DATE DEFAULT NULL,
    next_service_mileage INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_maint_vehicle (vehicle_id),
    INDEX idx_maint_next (next_service_date)
) ENGINE=InnoDB;

CREATE TABLE damages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED DEFAULT NULL,
    booking_id INT UNSIGNED DEFAULT NULL,
    incident_date DATE NOT NULL,
    description TEXT NOT NULL,
    photos TEXT DEFAULT NULL,               -- JSON array of file names
    estimated_cost DECIMAL(10,2) DEFAULT NULL,
    actual_cost DECIMAL(10,2) DEFAULT NULL,
    client_charge DECIMAL(10,2) DEFAULT NULL,
    status ENUM('reported','assessed','charged','resolved') NOT NULL DEFAULT 'reported',
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category ENUM('fuel','maintenance','repairs','cleaning','insurance','licensing','office','marketing','other') NOT NULL DEFAULT 'other',
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    supplier_id INT UNSIGNED DEFAULT NULL,
    vehicle_id INT UNSIGNED DEFAULT NULL,
    receipt VARCHAR(255) DEFAULT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_expenses_date (expense_date),
    INDEX idx_expenses_cat (category)
) ENGINE=InnoDB;

-- ---------- Support / notifications / audit / settings ----------
CREATE TABLE support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    subject VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    staff_reply TEXT DEFAULT NULL,
    replied_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    channel ENUM('in_app','email','sms','whatsapp') NOT NULL DEFAULT 'in_app',
    status ENUM('unread','read','sent','failed') NOT NULL DEFAULT 'unread',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    module VARCHAR(60) NOT NULL,
    record_type VARCHAR(60) DEFAULT NULL,
    record_id BIGINT UNSIGNED DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_module (module, created_at),
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE settings (
    `key` VARCHAR(80) PRIMARY KEY,
    `value` TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    title VARCHAR(120) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cta1_label VARCHAR(60) DEFAULT NULL,
    cta1_url VARCHAR(255) DEFAULT NULL,
    cta2_label VARCHAR(60) DEFAULT NULL,
    cta2_url VARCHAR(255) DEFAULT NULL,
    overlay TINYINT UNSIGNED NOT NULL DEFAULT 70,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active, sort_order)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
