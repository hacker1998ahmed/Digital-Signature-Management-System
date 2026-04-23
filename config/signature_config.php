<?php

/**
 * Digital Signature Factory - Configuration File
 * ملف الإعدادات الرئيسي لنظام التوقيع الرقمي
 */

return [
    // إعدادات OpenSSL
    'openssl' => [
        'config_path' => '/etc/ssl/openssl.cnf',
        'engine' => 'pkcs11',
        'default_digest' => 'sha256',
        'default_key_size' => 4096
    ],
    
    // إعدادات PKCS#11
    'pkcs11' => [
        'enabled' => true,
        'module_path' => '/usr/lib/softhsm/libsofthsm2.so',
        'supported_tokens' => ['EgyTrust', 'MCB', 'DeltaTrust', 'C3'],
        'default_pin' => '12345678',
        'slot_id' => 0
    ],
    
    // إعدادات PKCS#12
    'pkcs12' => [
        'search_paths' => [
            '/etc/ssl/certs/tokens/',
            './tokens/',
            getenv('HOME') . '/.dsfactory/tokens/'
        ],
        'allowed_extensions' => ['pfx', 'p12'],
        'default_passphrase' => ''
    ],
    
    // مزودي الخدمة
    'providers' => [
        'EgyTrust' => [
            'enabled' => true,
            'api_url' => 'https://api.egytrust.eg/v1',
            'auth_method' => 'certificate',
            'ocsp_url' => 'http://ocsp.egytrust.eg',
            'crl_url' => 'http://crl.egytrust.eg'
        ],
        'MCB' => [
            'enabled' => true,
            'api_url' => 'https://services.mcb.net.eg/api',
            'auth_method' => 'token',
            'ocsp_url' => 'http://ocsp.mcb.net.eg'
        ],
        'DeltaTrust' => [
            'enabled' => true,
            'api_url' => 'https://api.deltatrust.com.eg/v1',
            'auth_method' => 'pkcs11'
        ],
        'C3' => [
            'enabled' => true,
            'api_url' => 'https://services.c3.com.eg/api',
            'auth_method' => 'hsm'
        ]
    ],
    
    // البوابات الحكومية
    'government_gateways' => [
        'egypt' => [
            'etransact' => 'https://etransact.gov.eg/esigning/service',
            'nafis' => 'https://nafis.gov.eg/api/v1/invoices',
            'eta' => 'https://eta.gov.eg/api/e-invoice'
        ],
        'saudi_arabia' => [
            'zatca' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
            'zatca_phase2' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal'
        ],
        'uae' => [
            'fta' => 'https://eservices.tax.gov.ae/api',
            'moi' => 'https://www.moi.gov.ae/api/documents'
        ]
    ],
    
    // إعدادات TSA (Timestamp Authority)
    'tsa' => [
        'enabled' => true,
        'servers' => [
            'egypt' => 'http://tsa.egytrust.eg',
            'global' => 'http://timestamp.digicert.com'
        ],
        'default_policy' => '1.3.6.1.4.1.13762.1'
    ],
    
    // سجل التدقيق (Audit Trail)
    'audit' => [
        'enabled' => true,
        'log_path' => '/var/log/dsfactory/audit.log',
        'retention_days' => 365,
        'log_level' => 'INFO'
    ],
    
    // قاعدة البيانات
    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'dsfactory',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    
    // الأمان
    'security' => [
        'encrypt_tokens' => true,
        'encryption_key' => getenv('ENCRYPTION_KEY'),
        'session_timeout' => 3600,
        'max_login_attempts' => 5,
        'lockout_time' => 900
    ],
    
    // اللغة والواجهة
    'locale' => [
        'default' => 'ar',
        'supported' => ['ar', 'en'],
        'timezone' => 'Africa/Cairo'
    ]
];
