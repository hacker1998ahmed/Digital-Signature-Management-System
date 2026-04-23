<?php

namespace DigitalSignatureFactory\Providers;

use DigitalSignatureFactory\Core\Certificate;

/**
 * MisrClearingProvider - مزود خدمة مصر المقاصة (MCB)
 * 
 * يدعم:
 * - ملفات PEM/CER
 * - Tokens USB
 * - التوقيع عبر البوابة الحكومية
 */
class MisrClearingProvider
{
    private array $config;
    private ?Certificate $currentCert = null;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * تحميل شهادة من ملف PEM
     */
    public function loadFromPEM(string $filePath): Certificate
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("PEM file not found: {$filePath}");
        }
        
        $content = file_get_contents($filePath);
        $cert = openssl_x509_read($content);
        
        if (!$cert) {
            throw new \RuntimeException("Failed to read PEM certificate");
        }
        
        $this->currentCert = new Certificate($cert, $filePath, 'pem', [
            'provider' => 'MCB'
        ]);
        
        return $this->currentCert;
    }
    
    /**
     * الاتصال بـ Token MCB
     */
    public function connectToken(string $pin): Certificate
    {
        $modulePath = $this->config['pkcs11']['module_path'] ?? '/usr/lib/softhsm/libsofthsm2.so';
        
        $pkcs11Handle = new \DigitalSignatureFactory\Core\PKCS11Handle($modulePath, $pin, 'MCB');
        
        $this->currentCert = $pkcs11Handle->getCertificate();
        
        return $this->currentCert;
    }
    
    /**
     * الحصول على معلومات المزود
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => 'MCB',
            'fullName' => 'Misr Clearing Bank',
            'website' => 'https://www.mcb.net.eg',
            'supportEmail' => 'support@mcb.net.eg',
            'ocspUrl' => 'http://ocsp.mcb.net.eg',
            'supportedFormats' => ['pem', 'cer', 'pkcs11'],
            'tokenSupport' => true
        ];
    }
}
