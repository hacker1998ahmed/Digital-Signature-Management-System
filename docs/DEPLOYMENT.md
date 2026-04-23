# Digital Signature Factory - Deployment Guide
# دليل النشر والاستخدام

## Table of Contents / فهرس المحتويات

1. [Overview / نظرة عامة](#overview)
2. [System Requirements / متطلبات النظام](#system-requirements)
3. [Installation Methods / طرق التثبيت](#installation-methods)
4. [Configuration / الإعدادات](#configuration)
5. [Usage Guide / دليل الاستخدام](#usage-guide)
6. [API Reference / مرجع API](#api-reference)
7. [Troubleshooting / حل المشاكل](#troubleshooting)
8. [Security Best Practices / أفضل ممارسات الأمان](#security-best-practices)

---

## Overview / نظرة عامة

**Digital Signature Factory** is a comprehensive digital signature management system designed for Egyptian and Gulf government portals. It supports all major certificate providers in the region.

**مصنع التوقيعات الرقمية** هو نظام شامل لإدارة التوقيعات الرقمية المصمم للبوابات الحكومية المصرية والخليجية. يدعم جميع مزودي الشهادات الرئيسيين في المنطقة.

### Supported Providers / المزودون المدعومون

| Provider | Country | Formats | Features |
|----------|---------|---------|----------|
| EgyTrust | Egypt 🇪🇬 | PFX, P12, Token USB | ETRANZACT, NAFIS |
| MCB (Misr Clearing) | Egypt 🇪🇬 | PEM, CER, Token | Banking, E-Invoicing |
| DeltaTrust | Egypt 🇪🇬 | PKCS#11 | SmartCard, HSM |
| C3 | Egypt 🇪🇬 | HSM, SmartCard | Enterprise |
| ZATCA | Saudi Arabia 🇸🇦 | XML, CSR | E-Invoicing Phase 2 |
| FTA | UAE 🇦🇪 | XML | VAT Filing |

---

## System Requirements / متطلبات النظام

### Minimum Requirements / الحد الأدنى

- **CPU**: 2 cores
- **RAM**: 4 GB
- **Storage**: 20 GB
- **OS**: Ubuntu 20.04+, Debian 11+, CentOS 8+, macOS 11+

### Recommended Requirements / الموصى به

- **CPU**: 4+ cores
- **RAM**: 8+ GB
- **Storage**: 50+ GB SSD
- **OS**: Ubuntu 22.04 LTS

### Required Software / البرامج المطلوبة

```bash
# PHP 8.2+ with extensions
php, php-cli, php-fpm, php-mysql, php-xml, php-curl, php-mbstring, php-zip, php-bcmath, php-intl, php-soap

# Database
MySQL 8.0+ or PostgreSQL 14+

# Web Server
Nginx 1.20+ or Apache 2.4+

# Additional Tools
OpenSSL, PCSC-Lite, OpenSC, SoftHSM2, Redis (optional)

# Node.js (for frontend)
Node.js 18+ and npm 9+
```

---

## Installation Methods / طرق التثبيت

### Method 1: Docker (Recommended) / الطريقة 1: Docker (موصى به)

```bash
# Clone repository
cd /workspace/docker

# Start all services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f app

# Access application
# Web: http://localhost:8000
# Frontend: http://localhost:3000
# Database: localhost:3306
```

### Method 2: Automated Script / الطريقة 2: سكربت تلقائي

```bash
# Download installation script
curl -O https://raw.githubusercontent.com/dsf-team/digital-signature-factory/main/installers/install.sh

# Make executable
chmod +x install.sh

# Run installation (basic)
sudo ./install.sh

# Run installation (with Electron desktop app)
sudo ./install.sh --with-electron

# Run installation (with tests)
sudo ./install.sh --run-tests
```

### Method 3: Manual Installation / الطريقة 3: تثبيت يدوي

```bash
# 1. Install dependencies (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
    php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath \
    php8.2-intl php8.2-soap mysql-server nginx git curl unzip \
    openssl pcscd opensc softhsm2 redis-server

# 2. Clone repository
cd /var/www
git clone https://github.com/dsf-team/digital-signature-factory.git
cd digital-signature-factory

# 3. Install Composer dependencies
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader

# 4. Setup environment
cp .env.example .env
# Edit .env with your settings

# 5. Generate keys
php -r "echo base64_encode(random_bytes(32));" # APP_KEY
php -r "echo base64_encode(random_bytes(48));" # JWT_SECRET

# 6. Create database
mysql -e "CREATE DATABASE digital_signature_factory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'dsf_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
mysql -e "GRANT ALL PRIVILEGES ON digital_signature_factory.* TO 'dsf_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# 7. Import database schema
mysql digital_signature_factory < config/database.sql

# 8. Build frontend
cd frontend
npm install
npm run build

# 9. Configure web server (see Nginx configuration below)

# 10. Set permissions
sudo chown -R www-data:www-data /var/www/digital-signature-factory
sudo chmod -R 755 storage public
```

---

## Configuration / الإعدادات

### Environment Variables / متغيرات البيئة

Edit `.env` file:

```bash
# Application
APP_NAME="Digital Signature Factory"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=digital_signature_factory
DB_USERNAME=dsf_user
DB_PASSWORD=your_secure_password

# JWT Authentication
JWT_SECRET=your_generated_jwt_secret
JWT_EXPIRY=3600

# File Upload
MAX_UPLOAD_SIZE=52428800
ALLOWED_EXTENSIONS=pfx,p12,pem,cer,crt,xml,json,pdf

# PKCS#11 Token
PKCS11_MODULE_PATH=/usr/lib/softhsm/libsofthsm2.so
DEFAULT_TOKEN_PIN=12345678

# Email Notifications
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_app_password

# Government APIs
ZATCA_API_URL=https://gw-fatoora.zatca.gov.sa/e-invoice/developer-portal
ETRANZACT_API_URL=https://etransact.gov.eg/api/v1
FTA_API_URL=https://tax.gov.ae/api
```

### Nginx Configuration / إعدادات Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/digital-signature-factory/public;
    index index.php index.html;

    # SSL redirect (uncomment in production)
    # return 301 https://$server_name$request_uri;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath$path;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }

    client_max_body_size 50M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
}

# SSL Configuration (Production)
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Same location blocks as above
}
```

---

## Usage Guide / دليل الاستخدام

### 1. First Login / تسجيل الدخول الأول

1. Navigate to `http://your-domain.com`
2. Default admin credentials (change immediately!):
   - Username: `admin`
   - Password: `admin123`

### 2. Connect Token / توصيل Token

```bash
# List connected tokens
php scripts/console.php token:list

# Get token info
php scripts/console.php token:info --serial=123456

# Extract certificates
php scripts/console.php token:extract --serial=123456
```

### 3. Sign Document / توقيع مستند

#### Via Web Interface / عبر واجهة الويب

1. Go to "Document Signer" / "توقيع المستندات"
2. Upload your document (XML/PDF/JSON)
3. Select certificate from token
4. Choose signing policy
5. Click "Sign" / "توقيع"
6. Download signed document

#### Via API / عبر API

```bash
# Get JWT token first
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Sign XML document
curl -X POST http://localhost:8000/api/sign/xml \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: multipart/form-data" \
  -F "document=@invoice.xml" \
  -F "cert_id=123" \
  -F "policy=etransact"
```

### 4. Bulk Signing / التوقيع الجماعي

```bash
# Prepare CSV with files list
# file_path,cert_id,policy
# /path/to/file1.xml,123,etransact
# /path/to/file2.pdf,124,zatca

# Run bulk signing job
php scripts/console.php bulk:sign --input=files.csv --output=signed/
```

### 5. Certificate Management / إدارة الشهادات

```bash
# List all certificates
php scripts/console.php cert:list

# View certificate details
php scripts/console.php cert:view --id=123

# Check expiration
php scripts/console.php cert:check-expiry

# Export certificate
php scripts/console.php cert:export --id=123 --output=backup.pfx

# Create self-signed certificate
php scripts/console.php cert:create \
  --cn="My Company" \
  --o="My Organization" \
  --c="EG" \
  --days=365
```

---

## API Reference / مرجع API

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/register` | User registration |
| POST | `/api/auth/logout` | User logout |
| POST | `/api/auth/refresh` | Refresh JWT token |
| POST | `/api/auth/forgot-password` | Request password reset |

### Certificate Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/certificates` | List certificates |
| GET | `/api/certificates/{id}` | Get certificate details |
| POST | `/api/certificates/upload` | Upload certificate |
| DELETE | `/api/certificates/{id}` | Delete certificate |
| POST | `/api/certificates/{id}/export` | Export certificate |

### Signing Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/sign/xml` | Sign XML document |
| POST | `/api/sign/pdf` | Sign PDF document |
| POST | `/api/sign/json` | Sign JSON document |
| POST | `/api/sign/bulk` | Bulk signing |
| GET | `/api/sign/history` | Signing history |

### Token Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tokens` | List connected tokens |
| GET | `/api/tokens/{id}` | Get token info |
| POST | `/api/tokens/scan` | Scan for tokens |

### Example API Usage / مثال استخدام API

```javascript
// JavaScript example
const API_BASE = 'http://localhost:8000/api';

// Login
async function login(username, password) {
  const response = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password })
  });
  const data = await response.json();
  return data.token;
}

// Sign document
async function signDocument(token, file, certId) {
  const formData = new FormData();
  formData.append('document', file);
  formData.append('cert_id', certId);
  
  const response = await fetch(`${API_BASE}/sign/pdf`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData
  });
  
  return await response.blob();
}
```

---

## Troubleshooting / حل المشاكل

### Common Issues / المشاكل الشائعة

#### 1. Token Not Detected / عدم اكتشاف Token

```bash
# Check if PCSC service is running
sudo systemctl status pcscd

# Restart PCSC service
sudo systemctl restart pcscd

# List connected smart cards
pcsc_scan

# Check USB devices
lsusb | grep -i smart
```

#### 2. OpenSSL Errors / أخطاء OpenSSL

```bash
# Check OpenSSL version
openssl version

# Test OpenSSL configuration
openssl s_client -connect localhost:443

# Regenerate certificates if needed
php scripts/console.php cert:regenerate
```

#### 3. Database Connection Failed / فشل الاتصال بقاعدة البيانات

```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u dsf_user -p digital_signature_factory

# Check error logs
tail -f /var/log/mysql/error.log
```

#### 4. Permission Denied / خطأ الصلاحيات

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/digital-signature-factory

# Fix permissions
sudo find /var/www/digital-signature-factory/storage -type d -exec chmod 755 {} \;
sudo find /var/www/digital-signature-factory/storage -type f -exec chmod 644 {} \;
```

#### 5. Frontend Not Loading / الواجهة الأمامية لا تعمل

```bash
# Rebuild frontend
cd frontend
rm -rf node_modules package-lock.json
npm install
npm run build

# Clear cache
sudo systemctl restart nginx
```

---

## Security Best Practices / أفضل ممارسات الأمان

### 1. Secure Your Installation / تأمين التثبيت

```bash
# Change default passwords immediately
# Update .env with strong secrets
# Enable SSL/TLS for production
# Disable debug mode in production
# Regular security updates
```

### 2. Token Security / أمن Tokens

- Never share PIN codes
- Store tokens in secure locations
- Use hardware tokens when possible
- Enable auto-lock after inactivity

### 3. Audit Logging / سجلات التدقيق

All actions are logged in the audit trail. Review regularly:

```bash
# View recent logs
php scripts/console.php audit:view --limit=100

# Export logs
php scripts/console.php audit:export --format=csv --output=audit_report.csv

# Search logs
php scripts/console.php audit:search --user=admin --action=SIGN
```

### 4. Backup Strategy / استراتيجية النسخ الاحتياطي

```bash
#!/bin/bash
# Daily backup script

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/dsf_$DATE"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u dsf_user -p digital_signature_factory > $BACKUP_DIR/database.sql

# Files backup
tar -czf $BACKUP_DIR/files.tar.gz /var/www/digital-signature-factory/storage

# Certificates backup (encrypted)
tar -czf - /var/www/digital-signature-factory/storage/certificates | \
  openssl enc -aes-256-cbc -salt -out $BACKUP_DIR/certificates.enc -pass pass:YOUR_BACKUP_PASSWORD

# Upload to remote storage (optional)
# aws s3 cp $BACKUP_DIR s3://your-bucket/backups/

# Keep only last 30 days
find /backups -type d -name "dsf_*" -mtime +30 -exec rm -rf {} \;
```

---

## Support / الدعم

- **Documentation**: https://docs.dsf-egypt.com
- **Issues**: https://github.com/dsf-team/digital-signature-factory/issues
- **Email**: support@dsf-egypt.com
- **Phone**: +20-XXX-XXX-XXXX (Egypt), +966-XXX-XXX-XXXX (Saudi)

---

## License / الترخيص

MIT License - See LICENSE file for details.

---

**Digital Signature Factory v2.0.0**  
© 2026 DSF Team. All rights reserved.
