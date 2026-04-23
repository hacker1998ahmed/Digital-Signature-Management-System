# Digital Signature Factory - دليل الاستخدام
## مصنع التوقيع الرقمي - وثائق الاستخدام

## 📋 المحتويات

1. [البداية السريعة](#البداية-السريعة)
2. [قراءة الشهادات](#قراءة-الشهادات)
3. [التوقيع الرقمي](#التوقيع-الرقمي)
4. [تكامل البوابات الحكومية](#تكامل-البوابات-الحكومية)
5. [إدارة Tokens](#إدارة-tokens)

---

## البداية السريعة

### 1. تحميل المكتبة

```php
<?php
require_once 'vendor/autoload.php';

use DigitalSignatureFactory\Core\DigitalSignatureFactory;
use DigitalSignatureFactory\Core\Certificate;

// إنشاء نسخة من المصنع
$factory = new DigitalSignatureFactory();
```

### 2. اكتشاف Tokens المتصلة

```php
// مسح جميع الـ Tokens المتصلة
$tokens = $factory->detectTokens();

foreach ($tokens as $token) {
    echo "Token Type: " . $token['type'] . "\n";
    echo "Label: " . ($token['label'] ?? $token['filename']) . "\n";
}
```

---

## قراءة الشهادات

### من ملف PFX/P12 (EgyTrust)

```php
<?php
// تحميل شهادة من ملف PFX
$cert = $factory->readCertificate('/path/to/token.pfx', 'pfx');

// أو مع كلمة المرور
$pkcs12Data = file_get_contents('/path/to/token.pfx');
openssl_pkcs12_read($pkcs12Data, $certs, 'your_pin');
$cert = openssl_x509_read($certs['cert']);
```

### من ملف PEM/CER

```php
<?php
$cert = $factory->readCertificate('/path/to/certificate.pem', 'pem');
```

### من Token PKCS#11

```php
<?php
// الاتصال بـ Token
$pkcs11 = $factory->connectPKCS11(
    '/usr/lib/softhsm/libsofthsm2.so',
    '12345678', // PIN
    'EgyTrust-Token'
);

// الحصول على الشهادة
$cert = $pkcs11->getCertificate();
```

### استخراج معلومات الشهادة

```php
<?php
echo "Subject: " . $cert->getSubjectDN() . "\n";
echo "Issuer: " . $cert->getIssuerDN() . "\n";
echo "Serial: " . $cert->getSerialNumber() . "\n";
echo "Thumbprint: " . $cert->getThumbprint() . "\n";
echo "Valid From: " . $cert->getNotBefore() . "\n";
echo "Valid To: " . $cert->getNotAfter() . "\n";
echo "Is Valid: " . ($cert->isValid() ? 'Yes' : 'No') . "\n";
echo "Provider: " . $cert->getProviderInfo() . "\n";

// معلومات كاملة
$info = $cert->toArray();
print_r($info);
```

---

## التوقيع الرقمي

### توقيع XML (ETRANZACT)

```php
<?php
$invoiceData = [
    'InvoiceNumber' => 'INV-2026-001',
    'IssueDate' => '2026-01-15',
    'SellerName' => 'شركة المثال',
    'SellerVATNumber' => '123456789012345',
    'TotalAmount' => 10000.00,
    'VATAmount' => 1500.00,
    'GrandTotal' => 11500.00
];

$signedXML = $factory->signEGovXML([
    'invoice' => $invoiceData,
    'cert' => $cert,
    'policy' => 'http://www.egytrust.eg/policy'
]);

file_put_contents('signed_invoice.xml', $signedXML);
```

### توقيع ZATCA (السعودية)

```php
<?php
$xmlContent = file_get_contents('invoice.xml');

$zatcaSigned = $factory->signZatcaXML($xmlContent, $cert, [
    'phase' => 'production',
    'compliance_request' => true
]);

file_put_contents('zatca_signed_invoice.xml', $zatcaSigned);
```

### توقيع PDF (PAdES)

```php
<?php
$signedPdfPath = $factory->signPDF(
    'document.pdf',
    $cert,
    [
        'reason' => 'Document Approval',
        'location' => 'Cairo, Egypt',
        'contact' => 'legal@company.com'
    ]
);

copy($signedPdfPath, 'signed_document.pdf');
unlink($signedPdfPath);
```

---

## تكامل البوابات الحكومية

### مصر - ETRANZACT

```php
<?php
$response = $factory->submitToGovernmentGateway('etransact', [
    'document' => $signedXML,
    'cert_id' => $cert->getSerialNumber(),
    'timestamp' => time()
]);

if ($response['status'] === 'success') {
    echo "Submitted successfully. Reference: " . $response['reference_id'];
}
```

### السعودية - ZATCA

```php
<?php
// Phase 1: CSR Signing
$csrResponse = $factory->submitToGovernmentGateway('zatca', [
    'csr' => $csrData,
    'otp' => '123456'
]);

// Phase 2: Production Onboarding
$productionCert = $factory->signZatcaXML($invoiceXML, $cert);
```

### الإمارات - FTA

```php
<?php
$ftaResponse = $factory->submitToGovernmentGateway('fta', [
    'tax_invoice' => $signedXML,
    'trn' => '123456789012345'
]);
```

---

## إدارة Tokens

### إنشاء شهادة جديدة

```php
<?php
// إنشاء Root CA
$rootCA = $factory->createRootCA([
    'commonName' => 'MyCompany Root CA',
    'organizationName' => 'MyCompany',
    'countryName' => 'EG',
    'keySize' => 4096
]);

// إنشاء شهادة توقيع كود
$codeSignCert = $factory->createCodeSigningCertificate(
    $rootCA,
    'MyApplication.exe'
);

// إنشاء شهادة TSA
$tsaCert = $factory->createTSACertificate($rootCA);
```

### إعادة تصدير شهادة

```php
<?php
// تغيير كلمة المرور
$newPkcs12 = $factory->reExportCertificate($cert, 'new_password_123');
file_put_contents('exported_cert.pfx', $newPkcs12);
```

### التحقق من OCSP

```php
<?php
$isValid = $factory->verifyOCSP($cert);

if ($isValid) {
    echo "Certificate is valid and not revoked";
} else {
    echo "Certificate verification failed";
}
```

---

## أمثلة متقدمة

### توقيع جماعي (Bulk Signing)

```php
<?php
$files = glob('invoices/*.xml');
$signedFiles = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $signed = $factory->signEGovXML([
        'invoice' => simplexml_load_string($content),
        'cert' => $cert
    ]);
    
    $signedFiles[] = [
        'original' => $file,
        'signed' => 'signed_' . basename($file),
        'content' => $signed
    ];
    
    file_put_contents('signed_' . basename($file), $signed);
}

echo "Signed " . count($signedFiles) . " files successfully";
```

### مراقبة صلاحية الشهادات

```php
<?php
$certs = [
    '/path/to/cert1.pem',
    '/path/to/cert2.pfx'
];

foreach ($certs as $certPath) {
    $cert = $factory->readCertificate($certPath);
    $daysLeft = (strtotime($cert->getNotAfter()) - time()) / 86400;
    
    if ($daysLeft < 30) {
        echo "WARNING: {$certPath} expires in {$daysLeft} days!\n";
    }
}
```

---

## استكشاف الأخطاء

### مشكلة: "Failed to read PKCS#12 file"

**الحل:** تأكد من صحة كلمة المرور
```php
try {
    $cert = $factory->readCertificate('token.pfx', 'pfx');
} catch (\RuntimeException $e) {
    echo "Error: " . $e->getMessage();
    // تحقق من كلمة المرور
}
```

### مشكلة: "PKCS#11 module not found"

**الحل:** تثبيت SoftHSM
```bash
sudo apt-get install softhsm2 opensc
```

### مشكلة: "Private key not available"

**الحل:** تأكد من أن الملف يحتوي على المفتاح الخاص
```php
// للشهادات بدون مفتاح خاص، استخدم Token
$pkcs11 = $factory->connectPKCS11($module, $pin);
$cert = $pkcs11->getCertificate();
```

---

## الدعم الفني

للحصول على المساعدة:
- 📧 البريد الإلكتروني: support@dsfactory.ai
- 🌐 الموقع: https://dsfactory.ai
- 📚 التوثيق الكامل: /docs/

---

**تم التطوير بواسطة Digital Signature Factory AI © 2026**
