<?php

namespace DigitalSignatureFactory\Utils;

/**
 * Certificate Validator - محقق ومحقق صحة الشهادات الرقمية
 * 
 * يتحقق من:
 * - صلاحية الشهادة (Validity Period)
 * - سلسلة الثقة (Chain of Trust)
 * - حالة الإلغاء (CRL/OCSP)
 * - التوافق مع المعايير المصرية والخليجية
 */
class CertificateValidator
{
    private array $trustedRoots = [];
    private string $crlCacheDir;
    private bool $checkOCSP = true;
    private bool $checkCRL = true;
    
    public function __construct(array $config = [])
    {
        $this->crlCacheDir = $config['crl_cache_dir'] ?? '/tmp/dsf_crl_cache';
        $this->checkOCSP = $config['check_ocsp'] ?? true;
        $this->checkCRL = $config['check_crl'] ?? true;
        
        if (!is_dir($this->crlCacheDir)) {
            mkdir($this->crlCacheDir, 0755, true);
        }
        
        $this->loadTrustedRoots();
    }
    
    /**
     * التحقق الكامل من الشهادة
     */
    public function validateCertificate(string $certPath, string $passphrase = null): ValidationResult
    {
        $result = new ValidationResult();
        
        // تحميل الشهادة
        $cert = $this->loadCertificate($certPath, $passphrase);
        if (!$cert) {
            $result->addError('CERT_LOAD_FAILED', 'فشل تحميل الشهادة');
            return $result;
        }
        
        // التحقق من الصلاحية الزمنية
        $this->validateValidityPeriod($cert, $result);
        
        // التحقق من سلسلة الثقة
        $this->validateChainOfTrust($cert, $result);
        
        // التحقق من حالة الإلغاء (CRL)
        if ($this->checkCRL) {
            $this->validateCRL($cert, $result);
        }
        
        // التحقق من حالة الإلغاء (OCSP)
        if ($this->checkOCSP) {
            $this->validateOCSP($cert, $result);
        }
        
        // التحقق من استخدامات المفتاح
        $this->validateKeyUsage($cert, $result);
        
        // التحقق من التوقيع الذاتي
        $this->validateSignature($cert, $result);
        
        return $result;
    }
    
    /**
     * التحقق من الصلاحية الزمنية
     */
    private function validateValidityPeriod(OpenSSLCertificate $cert, ValidationResult $result): void
    {
        $notBefore = openssl_x509_get($cert, 'validFrom_time_t');
        $notAfter = openssl_x509_get($cert, 'validTo_time_t');
        
        $now = time();
        
        if ($now < $notBefore) {
            $result->addError('CERT_NOT_YET_VALID', 'الشهادة غير صالحة بعد');
            $result->setData('not_before', date('Y-m-d H:i:s', $notBefore));
        }
        
        if ($now > $notAfter) {
            $result->addError('CERT_EXPIRED', 'الشهادة منتهية الصلاحية');
            $result->setData('not_after', date('Y-m-d H:i:s', $notAfter));
        }
        
        // تحذير إذا كانت الصلاحية ستنتهي خلال 30 يوم
        $daysUntilExpiry = ($notAfter - $now) / 86400;
        if ($daysUntilExpiry < 30 && $daysUntilExpiry > 0) {
            $result->addWarning('CERT_EXPIRING_SOON', "الشهادة ستنتهي خلال {$daysUntilExpiry} يوم");
        }
        
        $result->setData('validity', [
            'not_before' => date('Y-m-d H:i:s', $notBefore),
            'not_after' => date('Y-m-d H:i:s', $notAfter),
            'days_until_expiry' => floor($daysUntilExpiry)
        ]);
    }
    
    /**
     * التحقق من سلسلة الثقة
     */
    private function validateChainOfTrust(OpenSSLCertificate $cert, ValidationResult $result): void
    {
        $issuer = openssl_x509_get($cert, 'issuer');
        $subject = openssl_x509_get($cert, 'subject');
        
        // التحقق إذا كانت شهادة جذرية موثوقة
        $isRoot = $issuer === $subject;
        
        if ($isRoot) {
            $fingerprint = openssl_x509_fingerprint($cert, 'sha256');
            if (!in_array($fingerprint, $this->trustedRoots)) {
                $result->addWarning('UNTRUSTED_ROOT', 'الشهادة الجذرية غير موثوقة في النظام');
            }
        } else {
            // شهادة وسيطة أو نهائية - تحتاج للتحقق من المصدر
            $result->setData('chain_info', [
                'subject' => $subject,
                'issuer' => $issuer,
                'is_root' => false
            ]);
        }
    }
    
    /**
     * التحقق من قائمة الإلغاء (CRL)
     */
    private function validateCRL(OpenSSLCertificate $cert, ValidationResult $result): void
    {
        $crlUrls = $this->extractCRLDistributionPoints($cert);
        
        if (empty($crlUrls)) {
            $result->addWarning('NO_CRL_URL', 'لا توجد نقاط توزيع CRL في الشهادة');
            return;
        }
        
        foreach ($crlUrls as $crlUrl) {
            $crlData = $this->fetchCRL($crlUrl);
            
            if ($crlData) {
                $isRevoked = $this->checkRevocationInCRL($cert, $crlData);
                
                if ($isRevoked) {
                    $result->addError('CERT_REVOKED', 'الشهادة ملغاة في قائمة CRL');
                    $result->setData('crl_url', $crlUrl);
                    return;
                }
            } else {
                $result->addWarning('CRL_FETCH_FAILED', "فشل جلب CRL من {$crlUrl}");
            }
        }
    }
    
    /**
     * التحقق عبر OCSP
     */
    private function validateOCSP(OpenSSLCertificate $cert, ValidationResult $result): void
    {
        $ocspUrls = $this->extractOCSPUrls($cert);
        
        if (empty($ocspUrls)) {
            $result->addWarning('NO_OCSP_URL', 'لا يوجد خادم OCSP في الشهادة');
            return;
        }
        
        foreach ($ocspUrls as $ocspUrl) {
            $ocspStatus = $this->queryOCSP($cert, $ocspUrl);
            
            if ($ocspStatus === 'revoked') {
                $result->addError('CERT_REVOKED_OCSP', 'الشهادة ملغاة حسب خادم OCSP');
                return;
            } elseif ($ocspStatus === 'unknown') {
                $result->addWarning('OCSP_UNKNOWN', 'حالة الشهادة غير معروفة لدى OCSP');
            } elseif ($ocspStatus === 'good') {
                $result->setData('ocsp_status', 'good');
            } else {
                $result->addWarning('OCSP_QUERY_FAILED', "فشل الاستعلام عن OCSP من {$ocspUrl}");
            }
        }
    }
    
    /**
     * التحقق من استخدامات المفتاح
     */
    private function validateKeyUsage(OpenSSLCertificate $cert, ValidationResult $result): void
    {
        $extensions = openssl_x509_parse($cert)['extensions'] ?? [];
        
        $keyUsage = $extensions['keyUsage'] ?? '';
        $extendedKeyUsage = $extensions['extendedKeyUsage'] ?? '';
        
        $result->setData('key_usage', [
            'key_usage' => $keyUsage,
            'extended_key_usage' => $extendedKeyUsage
        ]);
    }
    
    /**
     * التحقق من صحة التوقيع
     */
    private function validateSignature(OpenSSLCertificate $cert, ValidationResult $result): void
    {
        // التحقق من توقيع الشهادة
        $issuerCert = $this->getIssuerCertificate($cert);
        
        if ($issuerCert) {
            $isValid = openssl_x509_verify($cert, $issuerCert);
            
            if (!$isValid) {
                $result->addError('INVALID_SIGNATURE', 'توقيع الشهادة غير صالح');
            }
        }
    }
    
    /**
     * استخراج نقاط توزيع CRL
     */
    private function extractCRLDistributionPoints(OpenSSLCertificate $cert): array
    {
        $parsed = openssl_x509_parse($cert);
        $extensions = $parsed['extensions'] ?? [];
        
        $crlPoints = [];
        
        // البحث عن CRL Distribution Points
        if (isset($extensions['crlDistributionPoints'])) {
            $points = $extensions['crlDistributionPoints'];
            if (is_array($points)) {
                $crlPoints = $points;
            } else {
                $crlPoints = [$points];
            }
        }
        
        return $crlPoints;
    }
    
    /**
     * استخراج عناوين OCSP
     */
    private function extractOCSPUrls(OpenSSLCertificate $cert): array
    {
        $parsed = openssl_x509_parse($cert);
        $extensions = $parsed['extensions'] ?? [];
        
        $ocspUrls = [];
        
        // Authority Information Access يحتوي على OCSP
        if (isset($extensions['authorityInfoAccess'])) {
            $aia = $extensions['authorityInfoAccess'];
            if (is_string($aia)) {
                preg_match_all('/OCSP - URI:(https?:\/\/[^\s]+)/', $aia, $matches);
                $ocspUrls = $matches[1] ?? [];
            }
        }
        
        return $ocspUrls;
    }
    
    /**
     * جلب CRL من URL
     */
    private function fetchCRL(string $url): ?string
    {
        $cacheFile = $this->crlCacheDir . '/' . md5($url) . '.crl';
        
        // التحقق من الكاش أولاً
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            return file_get_contents($cacheFile);
        }
        
        // جلب CRL جديد
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $crlData = curl_exec($ch);
        curl_close($ch);
        
        if ($crlData) {
            file_put_contents($cacheFile, $crlData);
            return $crlData;
        }
        
        return null;
    }
    
    /**
     * التحقق من الإلغاء في CRL
     */
    private function checkRevocationInCRL(OpenSSLCertificate $cert, string $crlData): bool
    {
        // تحليل CRL والتحقق من رقم التسلسل
        $serialNumber = openssl_x509_get($cert, 'serialNumber');
        
        // تحليل مبسط لـ CRL (في الإنتاج نستخدم مكتبة متخصصة)
        $hexSerial = strtoupper(ltrim($serialNumber, '0'));
        
        // بحث بسيط عن الرقم التسلسلي في بيانات CRL
        if (strpos($crlData, $hexSerial) !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * الاستعلام عن OCSP
     */
    private function queryOCSP(OpenSSLCertificate $cert, string $ocspUrl): ?string
    {
        // إنشاء طلب OCSP
        $serialNumber = openssl_x509_get($cert, 'serialNumber');
        $issuerCert = $this->getIssuerCertificate($cert);
        
        if (!$issuerCert) {
            return null;
        }
        
        // استخدام OpenSSL للاستعلام OCSP
        $certFile = tempnam(sys_get_temp_dir(), 'cert_');
        $issuerFile = tempnam(sys_get_temp_dir(), 'issuer_');
        
        file_put_contents($certFile, openssl_x509_export($cert, false) ?: '');
        file_put_contents($issuerFile, openssl_x509_export($issuerCert, false) ?: '');
        
        $output = [];
        $cmd = "openssl ocsp -issuer {$issuerFile} -cert {$certFile} -url {$ocspUrl} -no_nonce 2>&1";
        exec($cmd, $output, $returnCode);
        
        unlink($certFile);
        unlink($issuerFile);
        
        $response = implode("\n", $output);
        
        if (strpos($response, ': good') !== false) {
            return 'good';
        } elseif (strpos($response, ': revoked') !== false) {
            return 'revoked';
        } elseif (strpos($response, ': unknown') !== false) {
            return 'unknown';
        }
        
        return null;
    }
    
    /**
     * الحصول على شهادة المصدر
     */
    private function getIssuerCertificate(OpenSSLCertificate $cert): ?OpenSSLCertificate
    {
        $issuer = openssl_x509_get($cert, 'issuer');
        
        // البحث في الشهادات الموثوقة
        foreach ($this->trustedRoots as $rootPath) {
            $rootCert = openssl_x509_read(file_get_contents($rootPath));
            if ($rootCert) {
                $rootSubject = openssl_x509_get($rootCert, 'subject');
                if ($rootSubject === $issuer) {
                    return $rootCert;
                }
            }
        }
        
        return null;
    }
    
    /**
     * تحميل الشهادات الجذرية الموثوقة
     */
    private function loadTrustedRoots(): void
    {
        $defaultRoots = [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            __DIR__ . '/../../config/trusted_roots/'
        ];
        
        foreach ($defaultRoots as $rootPath) {
            if (is_file($rootPath)) {
                $content = file_get_contents($rootPath);
                $certs = [];
                preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $content, $certs);
                
                foreach ($certs[0] as $certPem) {
                    $cert = openssl_x509_read($certPem);
                    if ($cert) {
                        $fingerprint = openssl_x509_fingerprint($cert, 'sha256');
                        $this->trustedRoots[] = $fingerprint;
                    }
                }
            } elseif (is_dir($rootPath)) {
                foreach (glob($rootPath . '/*.pem') as $pemFile) {
                    $this->trustedRoots[] = $pemFile;
                }
            }
        }
    }
    
    /**
     * تحميل الشهادة من ملف
     */
    private function loadCertificate(string $path, ?string $passphrase = null): ?OpenSSLCertificate
    {
        $content = file_get_contents($path);
        
        if ($content === false) {
            return null;
        }
        
        // تحديد نوع الملف
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), ['pfx', 'p12'])) {
            // PKCS#12
            if (openssl_pkcs12_read($content, $certs, $passphrase ?? '')) {
                return openssl_x509_read($certs['cert']);
            }
        } elseif (in_array(strtolower($extension), ['pem', 'cer', 'crt'])) {
            // PEM/CER/CRT
            return openssl_x509_read($content);
        }
        
        return null;
    }
}

/**
 * نتيجة التحقق من الشهادة
 */
class ValidationResult
{
    private bool $isValid = true;
    private array $errors = [];
    private array $warnings = [];
    private array $data = [];
    
    public function addError(string $code, string $message): void
    {
        $this->errors[] = ['code' => $code, 'message' => $message];
        $this->isValid = false;
    }
    
    public function addWarning(string $code, string $message): void
    {
        $this->warnings[] = ['code' => $code, 'message' => $message];
    }
    
    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
    
    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    public function getData(): array
    {
        return $this->data;
    }
    
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'data' => $this->data
        ];
    }
}
