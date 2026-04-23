# Digital Signature Factory - Deployment Guide
# دليل النشر والاستخدام

## 📋 المحتويات

1. [متطلبات النظام](#system-requirements)
2. [التثبيت السريع](#quick-installation)
3. [نشر Docker](#docker-deployment)
4. [تطبيق سطح المكتب](#desktop-application)
5. [الإعدادات المتقدمة](#advanced-configuration)
6. [الأمان](#security)
7. [استكشاف الأخطاء](#troubleshooting)

---

## <a name="system-requirements"></a> 1. متطلبات النظام

### الحد الأدنى
- **CPU**: Dual Core 2.0 GHz
- **RAM**: 4 GB
- **Storage**: 10 GB free space
- **OS**: Ubuntu 20.04+, Debian 11+, Fedora 36+, Windows 10+, macOS 11+

### الموصى به
- **CPU**: Quad Core 3.0 GHz+
- **RAM**: 8 GB+
- **Storage**: 50 GB SSD
- **OS**: Ubuntu 22.04 LTS, Windows 11, macOS 13+

### الاعتماديات البرمجية
```bash
# PHP 8.2+ with extensions
php-cli php-mysql php-xml php-curl php-intl php-mbstring

# Database
MySQL 8.0+ or MariaDB 10.6+

# Node.js
Node.js 18+ and npm 9+

# OpenSSL & PKCS#11
openssl softhsm2 pcscd opensc
```

---

## <a name="quick-installation"></a> 2. التثبيت السريع

### Linux/Mac
```bash
# Clone repository
git clone https://github.com/your-org/digital-signature-factory.git
cd digital-signature-factory

# Run installer
chmod +x installers/install.sh
./installers/install.sh --with-electron

# Or system-wide installation
sudo ./installers/install.sh --system-wide --with-electron
```

### Windows (PowerShell)
```powershell
# Download and run installer
Invoke-WebRequest -Uri "https://example.com/installer.ps1" -OutFile "installer.ps1"
.\installer.ps1
```

### Manual Installation
```bash
# 1. Install dependencies
apt-get install php8.2-cli php8.2-mysql mysql-server nodejs npm

# 2. Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 3. Install PHP dependencies
composer install

# 4. Setup database
mysql -u root -p < config/database.sql

# 5. Configure application
cp config/signature_config.example.php config/signature_config.php
# Edit config/signature_config.php with your settings

# 6. Build frontend
cd frontend && npm install && npm run build

# 7. Start server
php -S localhost:8000 -t public/
```

---

## <a name="docker-deployment"></a> 3. نشر Docker

### Quick Start
```bash
cd docker
docker-compose up -d
```

### Services
- **API**: http://localhost:8000
- **Frontend**: http://localhost:3000
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

### Production Docker
```bash
# Build custom image
docker-compose -f docker-compose.prod.yml up -d --build

# Scale workers
docker-compose up -d --scale worker=3
```

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=mysql
DB_DATABASE=signature_factory
DB_USERNAME=dsf_user
DB_PASSWORD=your_secure_password
JWT_SECRET=your_jwt_secret_key
```

---

## <a name="desktop-application"></a> 4. تطبيق سطح المكتب

### Development
```bash
cd electron
npm install
npm run dev
```

### Build for Production

#### Windows
```bash
npm run build:win
# Output: dist/Digital Signature Factory-setup.exe
```

#### macOS
```bash
npm run build:mac
# Output: dist/Digital Signature Factory.dmg
```

#### Linux
```bash
npm run build:linux
# Output: dist/Digital Signature Factory.AppImage
```

### Features
- ✅ System tray integration
- ✅ Auto-updates
- ✅ Native file dialogs
- ✅ USB Token detection
- ✅ Offline mode support
- ✅ Multi-language (Arabic/English)

---

## <a name="advanced-configuration"></a> 5. الإعدادات المتقدمة

### PKCS#11 Configuration
```bash
# Configure SoftHSM
mkdir -p /var/lib/softhsm/tokens
softhsm2-util --init-token --slot 0 --label "DSF-Token"

# Set PIN (default: 12345678)
# Store token in secure location
```

### HSM Integration
```php
// config/signature_config.php
'hsm' => [
    'enabled' => true,
    'provider' => 'safenet', // or 'utimaco', 'thales'
    'module_path' => '/opt/safenet/lib/libCryptoki2.so',
    'slot_id' => 0,
    'pin' => env('HSM_PIN')
]
```

### Load Balancer Setup
```nginx
# Nginx configuration
upstream dsf_backend {
    least_conn;
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
    server 127.0.0.1:8003;
}

server {
    listen 443 ssl;
    server_name signature.yourdomain.com;
    
    location / {
        proxy_pass http://dsf_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

---

## <a name="security"></a> 6. الأمان

### Best Practices

1. **Database Security**
   ```sql
   -- Use strong passwords
   -- Enable SSL connections
   -- Regular backups
   ```

2. **Application Security**
   ```php
   // Enable HTTPS only
   'force_https' => true,
   
   // Secure session settings
   'session_secure' => true,
   'session_httponly' => true,
   
   // Rate limiting
   'rate_limit' => 100, // requests per minute
   ```

3. **Token Security**
   - Never store PINs in plain text
   - Use HSM for production
   - Regular token rotation

4. **Audit Logging**
   - All operations logged
   - Logs retained for 90 days minimum
   - Export capabilities for compliance

### Firewall Rules
```bash
# Allow only necessary ports
ufw allow 443/tcp  # HTTPS
ufw allow 22/tcp   # SSH
ufw enable
```

---

## <a name="troubleshooting"></a> 7. استكشاف الأخطاء

### Common Issues

#### Token Not Detected
```bash
# Check PC/SC service
systemctl status pcscd

# List connected readers
pcsc_scan

# Check permissions
ls -la /dev/usb*
```

#### Database Connection Failed
```bash
# Check MySQL status
systemctl status mysql

# Test connection
mysql -u dsf_user -p -h localhost signature_factory

# Check logs
tail -f storage/logs/error.log
```

#### PHP Extension Missing
```bash
# List loaded extensions
php -m

# Install missing extension
apt-get install php8.2-pkcs11
```

#### Electron App Won't Start
```bash
# Clear cache
rm -rf ~/.config/digital-signature-factory

# Rebuild native modules
cd electron
npm rebuild

# Check logs
tail -f ~/.config/digital-signature-factory/logs/main.log
```

### Getting Help
- Documentation: `/docs` folder
- Issues: GitHub Issues
- Email: support@digitalsignaturefactory.com

---

## 📞 الدعم الفني

للحصول على الدعم الفني والترخيص التجاري:
- **Email**: support@digitalsignaturefactory.com
- **Website**: https://digitalsignaturefactory.com
- **Documentation**: https://docs.digitalsignaturefactory.com

© 2024 Digital Signature Factory. جميع الحقوق محفوظة.
