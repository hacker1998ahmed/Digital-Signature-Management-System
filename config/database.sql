-- Digital Signature Factory Database Schema
-- مخطط قاعدة البيانات لنظام التوقيع الرقمي

CREATE DATABASE IF NOT EXISTS dsfactory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dsfactory;

-- جدول المستخدمين
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    organization VARCHAR(255),
    role ENUM('admin', 'operator', 'viewer') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- جدول الشهادات الرقمية
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    certificate_name VARCHAR(255) NOT NULL,
    certificate_type ENUM('pfx', 'p12', 'pem', 'cer', 'pkcs11') NOT NULL,
    subject_dn TEXT,
    issuer_dn TEXT,
    serial_number VARCHAR(100),
    thumbprint VARCHAR(64),
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    provider ENUM('EgyTrust', 'MCB', 'DeltaTrust', 'C3', 'Other'),
    file_path VARCHAR(512),
    encrypted_data MEDIUMTEXT,
    metadata JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_serial (serial_number),
    INDEX idx_thumbprint (thumbprint),
    INDEX idx_provider (provider),
    INDEX idx_validity (valid_from, valid_to)
) ENGINE=InnoDB;

-- جدول Tokens
CREATE TABLE tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_label VARCHAR(255) NOT NULL,
    token_serial VARCHAR(100),
    provider ENUM('EgyTrust', 'MCB', 'DeltaTrust', 'C3', 'Other'),
    manufacturer VARCHAR(255),
    model VARCHAR(255),
    pkcs11_module VARCHAR(512),
    slot_id INT,
    pin_encrypted VARCHAR(512),
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_label (token_label),
    INDEX idx_serial (token_serial),
    INDEX idx_provider (provider)
) ENGINE=InnoDB;

-- جدول المستندات الموقعة
CREATE TABLE signed_documents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM('xml', 'pdf', 'json') NOT NULL,
    document_hash VARCHAR(64) NOT NULL,
    signature_hash VARCHAR(64),
    certificate_id INT,
    token_id INT,
    signer_name VARCHAR(255),
    signer_dn TEXT,
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    timestamp_token TEXT,
    file_path VARCHAR(512),
    file_size BIGINT,
    gateway_submitted ENUM('etransact', 'zatca', 'fta', 'moi', 'none') DEFAULT 'none',
    gateway_response JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE SET NULL,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE SET NULL,
    INDEX idx_document_hash (document_hash),
    INDEX idx_signed_at (signed_at),
    INDEX idx_gateway (gateway_submitted)
) ENGINE=InnoDB;

-- جدول سجل التدقيق (Audit Trail)
CREATE TABLE audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    severity ENUM('INFO', 'WARNING', 'ERROR', 'CRITICAL') DEFAULT 'INFO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_severity (severity)
) ENGINE=InnoDB;

-- جدول إعدادات النظام
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- إدراج مستخدم افتراضي (كلمة المرور: admin123)
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@dsfactory.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- إدراج إعدادات افتراضية
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('default_provider', 'EgyTrust', 'string', 'مزود الخدمة الافتراضي'),
('enable_audit', 'true', 'boolean', 'تفعيل سجل التدقيق'),
('session_timeout', '3600', 'number', 'مهلة الجلسة بالثواني'),
('max_upload_size', '10485760', 'number', 'الحد الأقصى لحجم الملف بالبايت'),
('supported_gateways', '["etransact", "zatca", "fta"]', 'json', 'البوابات الحكومية المدعومة');

-- عرض لدمج المعلومات
CREATE VIEW v_certificates_summary AS
SELECT 
    c.id,
    c.certificate_name,
    c.certificate_type,
    c.subject_dn,
    c.provider,
    c.valid_from,
    c.valid_to,
    c.is_active,
    u.username as owner,
    CASE 
        WHEN c.valid_to < NOW() THEN 'expired'
        WHEN c.valid_from > NOW() THEN 'not_yet_valid'
        ELSE 'valid'
    END as status
FROM certificates c
LEFT JOIN users u ON c.user_id = u.id;

-- عرض لسجل التدقيق المفصل
CREATE VIEW v_audit_log_detailed AS
SELECT 
    a.id,
    a.action,
    a.entity_type,
    a.severity,
    a.created_at,
    u.username,
    u.full_name,
    a.ip_address,
    a.details
FROM audit_log a
LEFT JOIN users u ON a.user_id = u.id
ORDER BY a.created_at DESC;
