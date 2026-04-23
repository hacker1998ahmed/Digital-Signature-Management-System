<?php

namespace DigitalSignatureFactory\Core;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * Certificate - فئة تمثيل الشهادة الرقمية
 * 
 * تدعم جميع أنواع الشهادات:
 * - X.509 Certificates
 * - PKCS#12 (.pfx/.p12)
 * - PEM (.pem/.cer/.crt)
 * - Smart Card Certificates
 */
class Certificate
{
    private OpenSSLCertificate $certificate;
    private ?string $path;
    private string $type;
    private array $metadata;
    private ?OpenSSLAsymmetricKey $privateKey = null;
    private array $extraCerts = [];
    
    public function __construct(
        OpenSSLCertificate $cert,
        ?string $path = null,
        string $type = 'x509',
        array $metadata = []
    ) {
        $this->certificate = $cert;
        $this->path = $path;
        $this->type = $type;
        $this->metadata = $metadata;
        
        if (isset($metadata['private_key'])) {
            $this->privateKey = $metadata['private_key'];
        }
        
        if (isset($metadata['extracerts'])) {
            $this->extraCerts = $metadata['extracerts'];
        }
    }
    
    /**
     * الحصول على Subject DN (Distinguished Name)
     */
    public function getSubjectDN(): string
    {
        $info = openssl_x509_parse($this->certificate);
        
        if (!$info) {
            return '';
        }
        
        $parts = [];
        
        if (isset($info['subject']['CN'])) {
            $parts[] = "CN={$info['subject']['CN']}";
        }
        if (isset($info['subject']['O'])) {
            $parts[] = "O={$info['subject']['O']}";
        }
        if (isset($info['subject']['OU'])) {
            $parts[] = "OU={$info['subject']['OU']}";
        }
        if (isset($info['subject']['C'])) {
            $parts[] = "C={$info['subject']['C']}";
        }
        if (isset($info['subject']['ST'])) {
            $parts[] = "ST={$info['subject']['ST']}";
        }
        if (isset($info['subject']['L'])) {
            $parts[] = "L={$info['subject']['L']}";
        }
        if (isset($info['subject']['emailAddress'])) {
            $parts[] = "emailAddress={$info['subject']['emailAddress']}";
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * الحصول على Issuer DN
     */
    public function getIssuerDN(): string
    {
        $info = openssl_x509_parse($this->certificate);
        
        if (!$info) {
            return '';
        }
        
        $parts = [];
        
        if (isset($info['issuer']['CN'])) {
            $parts[] = "CN={$info['issuer']['CN']}";
        }
        if (isset($info['issuer']['O'])) {
            $parts[] = "O={$info['issuer']['O']}";
        }
        if (isset($info['issuer']['C'])) {
            $parts[] = "C={$info['issuer']['C']}";
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * الحصول على اسم المنظمة
     */
    public function getOrganizationName(): string
    {
        $info = openssl_x509_parse($this->certificate);
        return $info['subject']['O'] ?? '';
    }
    
    /**
     * الحصول على الرقم التسلسلي للشهادة
     */
    public function getSerialNumber(): string
    {
        $info = openssl_x509_parse($this->certificate);
        return $info['serialNumber'] ?? '';
    }
    
    /**
     * الحصول على بصمة الشهادة (Thumbprint)
     */
    public function getThumbprint(): string
    {
        $output = '';
        openssl_x509_export($this->certificate, $output);
        return strtoupper(sha1($output));
    }
    
    /**
     * الحصول على تاريخ البدء (Not Before)
     */
    public function getNotBefore(): string
    {
        $info = openssl_x509_parse($this->certificate);
        return date('Y-m-d H:i:s', $info['validFrom_time_t'] ?? 0);
    }
    
    /**
     * الحصول على تاريخ الانتهاء (Not After)
     */
    public function getNotAfter(): string
    {
        $info = openssl_x509_parse($this->certificate);
        return date('Y-m-d H:i:s', $info['validTo_time_t'] ?? 0);
    }
    
    /**
     * التحقق من صلاحية الشهادة
     */
    public function isValid(): bool
    {
        $now = time();
        $info = openssl_x509_parse($this->certificate);
        
        if (!$info) {
            return false;
        }
        
        $validFrom = $info['validFrom_time_t'] ?? 0;
        $validTo = $info['validTo_time_t'] ?? 0;
        
        return $now >= $validFrom && $now <= $validTo;
    }
    
    /**
     * الحصول على Key Usage
     */
    public function getKeyUsage(): array
    {
        $info = openssl_x509_parse($this->certificate);
        $extensions = $info['extensions'] ?? [];
        
        $keyUsage = $extensions['keyUsage'] ?? '';
        
        return array_filter(array_map('trim', explode(',', $keyUsage)));
    }
    
    /**
     * الحصول على Extended Key Usage
     */
    public function getExtendedKeyUsage(): array
    {
        $info = openssl_x509_parse($this->certificate);
        $extensions = $info['extensions'] ?? [];
        
        $eku = $extensions['extendedKeyUsage'] ?? '';
        
        return array_filter(array_map('trim', explode(',', $eku)));
    }
    
    /**
     * الحصول على CRL Distribution Points
     */
    public function getCRLDistributionPoints(): array
    {
        $info = openssl_x509_parse($this->certificate);
        $extensions = $info['extensions'] ?? [];
        
        $crl = $extensions['crlDistributionPoints'] ?? '';
        
        if (empty($crl)) {
            return [];
        }
        
        return array_filter(array_map('trim', explode("\n", $crl)));
    }
    
    /**
     * الحصول على OCSP URLs
     */
    public function getOCSPUrls(): array
    {
        $info = openssl_x509_parse($this->certificate);
        $extensions = $info['extensions'] ?? [];
        
        $ocsp = $extensions['authorityInfoAccess'] ?? '';
        
        if (empty($ocsp)) {
            return [];
        }
        
        $urls = [];
        preg_match_all('/OCSP\s*-\s*(URI:)?(.+)/i', $ocsp, $matches);
        
        if (!empty($matches[2])) {
            $urls = array_map('trim', $matches[2]);
        }
        
        return $urls;
    }
    
    /**
     * الحصول على معلومات المزود (Provider Info)
     */
    public function getProviderInfo(): string
    {
        $subject = $this->getSubjectDN();
        
        // تحديد المزود بناءً على المعلومات
        if (strpos($subject, 'EgyTrust') !== false || strpos($subject, 'egytrust.eg') !== false) {
            return 'EgyTrust';
        } elseif (strpos($subject, 'MCB') !== false || strpos($subject, 'Misr Clearing') !== false) {
            return 'MCB';
        } elseif (strpos($subject, 'DeltaTrust') !== false) {
            return 'DeltaTrust';
        } elseif (strpos($subject, 'C3') !== false) {
            return 'C3';
        }
        
        return 'Unknown';
    }
    
    /**
     * الحصول على رقم الضريبة (VAT Number)
     */
    public function getVATNumber(): ?string
    {
        $info = openssl_x509_parse($this->certificate);
        $extensions = $info['extensions'] ?? [];
        
        // البحث عن VAT Number في extensions
        foreach ($extensions as $key => $value) {
            if (stripos($key, 'vat') !== false || stripos($value, 'tax') !== false) {
                preg_match('/[0-9]{14,15}/', $value, $matches);
                if (!empty($matches)) {
                    return $matches[0];
                }
            }
        }
        
        return null;
    }
    
    /**
     * الحصول على الشهادة كـ OpenSSL Certificate
     */
    public function getOpenSSLCertificate(): OpenSSLCertificate
    {
        return $this->certificate;
    }
    
    /**
     * الحصول على المفتاح الخاص
     */
    public function getPrivateKey(): ?OpenSSLAsymmetricKey
    {
        return $this->privateKey;
    }
    
    /**
     * الحصول على المفتاح العام
     */
    public function getPublicKey(): string
    {
        $output = '';
        openssl_x509_export($this->certificate, $output);
        return $output;
    }
    
    /**
     * تصدير الشهادة إلى PKCS#12
     */
    public function exportToPKCS12(string $passphrase = ''): ?string
    {
        if (!$this->privateKey) {
            throw new \RuntimeException("Private key not available for export");
        }
        
        $pkcs12 = '';
        
        if (!openssl_pkcs12_export(
            $this->getPublicKey(),
            $pkcs12,
            $this->privateKey,
            $passphrase,
            ['extracerts' => $this->extraCerts]
        )) {
            return null;
        }
        
        return $pkcs12;
    }
    
    /**
     * إعادة تصدير الشهادة بكلمة مرور جديدة
     */
    public function reExport(string $newPassphrase): self
    {
        $pkcs12 = $this->exportToPKCS12($newPassphrase);
        
        if (!$pkcs12) {
            throw new \RuntimeException("Failed to re-export certificate");
        }
        
        // قراءة الشهادة المصدرة حديثاً
        if (!openssl_pkcs12_read($pkcs12, $certs, $newPassphrase)) {
            throw new \RuntimeException("Failed to read re-exported certificate");
        }
        
        $newCert = openssl_x509_read($certs['cert']);
        
        return new self($newCert, null, 'pkcs12', [
            'private_key' => $certs['pkey'] ?? null,
            'extracerts' => $certs['extracerts'] ?? []
        ]);
    }
    
    /**
     * تعيين اسم ودي (Friendly Name)
     */
    public function setFriendlyName(string $name): void
    {
        $this->metadata['friendly_name'] = $name;
    }
    
    /**
     * تعيين وصف للشهادة
     */
    public function setDescription(string $description): void
    {
        $this->metadata['description'] = $description;
    }
    
    /**
     * الحصول على الشهادة كـ PEM string
     */
    public function toPEM(): string
    {
        return $this->getPublicKey();
    }
    
    /**
     * الحصول على معلومات الشهادة كـ array
     */
    public function toArray(): array
    {
        $info = openssl_x509_parse($this->certificate);
        
        return [
            'subject_dn' => $this->getSubjectDN(),
            'issuer_dn' => $this->getIssuerDN(),
            'serial_number' => $this->getSerialNumber(),
            'thumbprint' => $this->getThumbprint(),
            'valid_from' => $this->getNotBefore(),
            'valid_to' => $this->getNotAfter(),
            'is_valid' => $this->isValid(),
            'key_usage' => $this->getKeyUsage(),
            'extended_key_usage' => $this->getExtendedKeyUsage(),
            'crl_distribution_points' => $this->getCRLDistributionPoints(),
            'ocsp_urls' => $this->getOCSPUrls(),
            'provider' => $this->getProviderInfo(),
            'vat_number' => $this->getVATNumber(),
            'friendly_name' => $this->metadata['friendly_name'] ?? '',
            'description' => $this->metadata['description'] ?? '',
            'raw_info' => $info
        ];
    }
    
    /**
     * الحصول على شهادة issuer
     */
    public function getIssuerCertificate(): ?self
    {
        // البحث عن شهادة issuer في extraCerts
        $issuerDN = $this->getIssuerDN();
        
        foreach ($this->extraCerts as $extraCert) {
            $cert = openssl_x509_read($extraCert);
            $extraInfo = openssl_x509_parse($cert);
            
            $extraSubjectDN = '';
            if (isset($extraInfo['subject']['CN'])) {
                $extraSubjectDN = "CN={$extraInfo['subject']['CN']}";
            }
            
            if ($extraSubjectDN === $issuerDN) {
                return new self($cert, null, 'pem');
            }
        }
        
        return null;
    }
    
    /**
     * التحقق من سلسلة الشهادات
     */
    public function verifyChain(array $caCerts = []): bool
    {
        $caFile = tempnam(sys_get_temp_dir(), 'ca_');
        
        // كتابة شهادات CA إلى ملف مؤقت
        $caContent = '';
        foreach ($caCerts as $caCert) {
            if ($caCert instanceof self) {
                $caContent .= $caCert->toPEM() . "\n";
            } else {
                $caContent .= $caCert . "\n";
            }
        }
        
        file_put_contents($caFile, $caContent);
        
        $result = openssl_x509_verify($this->certificate, $caFile);
        
        unlink($caFile);
        
        return $result === 1;
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        // OpenSSL resources are automatically freed in PHP 8+
    }
}
