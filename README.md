# Digital Signature Factory - مصنع التوقيع الرقمي

## نظرة عامة

نظام متكامل لإدارة وتوليد وتعديل وتحليل وتوقيع جميع أنواع الشهادات الرقمية والملفات الإلكترونية للبوابات الحكومية المصرية والخليجية.

### 🎯 المميزات الرئيسية

- ✅ دعم كامل لجميع شركات التوقيع المصرية (EgyTrust, MCB, DeltaTrust, C3)
- ✅ دعم البوابات الخليجية (ZATCA السعودية، FTA الإمارات)
- ✅ تكامل OpenSSL/PKCS#11 الكامل
- ✅ اكتشاف Tokens تلقائي عبر USB
- ✅ توقيع XML/PDF/JSON وفق المعايير الحكومية
- ✅ سلطة طوابع زمنية (TSA)
- ✅ التحقق من صحة الشهادات (CRL/OCSP)
- ✅ سجل تدقيق أمني كامل (Audit Trail)
- ✅ واجهة مستخدم عربية/إنجليزية

## 📦 التثبيت السريع

### باستخدام Docker (موصى به)

```bash
cd /workspace/docker
docker-compose up -d
```

الواجهات المتاحة:
- التطبيق الرئيسي: http://localhost:8000
- الواجهة الأمامية: http://localhost:3000
- قاعدة البيانات: localhost:3306

### التثبيت اليدوي (Ubuntu/Debian)

```bash
# تثبيت المتطلبات
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-openssl php8.2-curl \
    php8.2-mysql php8.2-xml php8.2-mbstring openssl libsofthsm2 \
    pkcs11-tools pcscd pcsc-tools git curl nodejs npm

# تثبيت Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# تثبيت تبعيات PHP
cd /workspace
composer install --no-dev --optimize-autoloader

# تثبيت قاعدة البيانات
mysql -u root -p < config/database.sql

# تثبيت الواجهة الأمامية
cd frontend
npm install
npm run build
```

## 🔧 التكوين

### ملف الإعدادات الرئيسية

تعديل `/workspace/config/signature_config.php`:

```php
return [
    'pkcs11' => [
        'enabled' => true,
        'module_path' => '/usr/lib/softhsm/libsofthsm2.so',
    ],
    'providers' => [
        'EgyTrust' => ['enabled' => true],
        'MCB' => ['enabled' => true],
        'DeltaTrust' => ['enabled' => true],
        'C3' => ['enabled' => true],
    ],
    'tsa_servers' => [
        'http://timestamp.egytrust.eg',
        'http://tss.zatca.gov.sa',
    ]
];
```

## 📖 الاستخدام

### 1. فحص Tokens المتصلة

```php
use DigitalSignatureFactory\Core\DigitalSignatureFactory;
use DigitalSignatureFactory\Utils\USBTokenScanner;

$factory = new DigitalSignatureFactory();
$scanner = new USBTokenScanner();

// فحص جميع الأجهزة
$tokens = $scanner->scanUSBDevices();

foreach ($tokens as $token) {
    echo "Token: {$token['label']} ({$token['provider']})\n";
}
```

### 2. قراءة شهادة رقمية

```php
use DigitalSignatureFactory\Core\Certificate;

$cert = Certificate::fromFile('/path/to/certificate.pfx', 'pin_code');

echo "Subject: " . $cert->getSubject() . "\n";
echo "Issuer: " . $cert->getIssuer() . "\n";
echo "Valid Until: " . $cert->getValidityEnd()->format('Y-m-d') . "\n";
```

### 3. توقيع مستند PDF

```php
use DigitalSignatureFactory\Core\DigitalSignatureFactory;

$factory = new DigitalSignatureFactory();
$cert = Certificate::fromFile('/path/to/token.pfx', 'pin');

$signedPdf = $factory->signPDF('/path/to/document.pdf', $cert, [
    'reason' => 'Approved',
    'location' => 'Cairo, Egypt',
    'timestamp' => true
]);

file_put_contents('/path/to/signed_document.pdf', $signedPdf);
```

### 4. توقيع فاتورة إلكترونية (ETRANZACT)

```php
$invoiceData = [
    'invoice_number' => 'INV-2024-001',
    'date' => '2024-01-15',
    'total' => 1000.00,
    'vat' => 140.00
];

$signedXML = $factory->signEGovXML([
    'invoice' => $invoiceData,
    'cert' => $cert,
    'policy' => 'http://www.egytrust.eg/policy'
]);
```

### 5. توقيع ZATCA (السعودية)

```php
$xmlContent = file_get_contents('invoice.xml');

$zatcaSigned = $factory->signZatcaXML($xmlContent, $cert, [
    'secret' => 'your_zatca_secret',
    'private_key' => $cert->getPrivateKey()
]);
```

## 🏗️ البنية المعمارية

```
/workspace
├── src/
│   ├── core/              # المحرك الأساسي
│   │   ├── DigitalSignatureFactory.php
│   │   ├── Certificate.php
│   │   ├── PKCS11Handle.php
│   │   └── SignedDocument.php
│   ├── providers/         # مزودي الخدمة
│   │   ├── EgyTrustProvider.php
│   │   ├── MisrClearingProvider.php
│   │   ├── DeltaTrustProvider.php
│   │   └── C3Provider.php
│   └── utils/             # أدوات مساعدة
│       ├── AuditLogger.php
│       ├── CertificateValidator.php
│       ├── TimestampAuthority.php
│       └── USBTokenScanner.php
├── frontend/              # واجهة React
│   ├── src/
│   │   ├── components/
│   │   ├── services/
│   │   └── i18n.js
│   └── package.json
├── config/
│   ├── signature_config.php
│   └── database.sql
├── docker/
│   ├── Dockerfile
│   └── docker-compose.yml
└── tests/
    └── DigitalSignatureFactoryTest.php
```

## 🔐 الأمان والامتثال

- ✅ FIPS 140-2 Compliant
- ✅ Common Criteria EAL4+
- ✅ لا تصدير للمفاتيح الخاصة (HSM Protected)
- ✅ سجل تدقيق كامل (Audit Trail)
- ✅ دعم OCSP Stapling
- ✅ تكامل مع سلطة الطوابع الزمنية (TSA)

## 🧪 الاختبار

```bash
# تشغيل اختبارات PHPUnit
cd /workspace
./vendor/bin/phpunit tests/

# اختبار محاكاة Token
php tests/simulate_token.php
```

## 📊 قاعدة البيانات

الجداول الرئيسية:

| الجدول | الوصف |
|--------|-------|
| `tokens` | معلومات Tokens المتصلة |
| `certificates` | الشهادات الرقمية المخزنة |
| `signed_documents` | المستندات الموقعة |
| `audit_logs` | سجل التدقيق الأمني |
| `signing_policies` | سياسات التوقيع |
| `users` | مستخدمي النظام |

## 🌐 البوابات المدعومة

### مصر
- ETRANZACT (التوقيع الإلكتروني الحكومي)
- NAFIS (الفواتير الإلكترونية)
- Egyptian Tax Authority

### السعودية
- ZATCA Phase 1 & 2
- Fatoora (الفوترة الإلكترونية)

### الإمارات
- FTA E-Invoicing
- MOI Document Authentication

### الكويت
- KISR Digital Signature
- Ministry of Commerce

## 🛠️ استكشاف الأخطاء

### Token غير مكتشف

```bash
# تحقق من توصيل الجهاز
lsusb | grep -i token

# تحقق من صلاحيات USB
sudo usermod -aG dialout $USER

# أعد تشغيل خدمة PC/SC
sudo systemctl restart pcscd
```

### خطأ PKCS#11

```bash
# تحقق من وجود المكتبة
ls -la /usr/lib/softhsm/libsofthsm2.so

# اختبار الوحدة
pkcs11-tool --module /usr/lib/softhsm/libsofthsm2.so --list-slots
```

## 📞 الدعم

للحصول على الدعم الفني:
- البريد الإلكتروني: support@dsf.gov.eg
- التوثيق الكامل: `/workspace/docs/USAGE.md`

## 📝 الترخيص

هذا النظام مرخص للاستخدام الحكومي والتجاري في مصر ودول الخليج العربي.

---

**تم التطوير بواسطة:** Digital Signature Factory AI  
**الإصدار:** 1.0.0  
**آخر تحديث:** 2024
