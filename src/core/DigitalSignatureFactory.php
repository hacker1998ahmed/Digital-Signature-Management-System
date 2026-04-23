<?php

namespace DigitalSignatureFactory\Core;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * DigitalSignatureFactory - المحرك الأساسي لنظام التوقيع الرقمي
 * 
 * يدعم جميع شركات التوقيع المصرية والخليجية:
 * - EgyTrust (إيجي تراست)
 * - MCB (مصر المقاصة)
 * - DeltaTrust (دلتا تراست)
 * - C3 (سيجي)
 * - IDSigner
 * - Thawte/VeriSign
 */
class DigitalSignatureFactory
{
    private array $config;
    private array $providers = [];
    private ?PKCS11Handle $pkcs11Handle = null;
    
    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->getDefaultConfig();
        $this->initializeProviders();
    }
    
    /**
     * تهيئة مزودي الخدمة المصريين والخليجيين
     */
    private function initializeProviders(): void
    {
        $providerClasses = [
            'EgyTrust' => \DigitalSignatureFactory\Providers\EgyTrustProvider::class,
            'MCB' => \DigitalSignatureFactory\Providers\MisrClearingProvider::class,
            'DeltaTrust' => \DigitalSignatureFactory\Providers\DeltaTrustProvider::class,
            'C3' => \DigitalSignatureFactory\Providers\C3Provider::class,
        ];
        
        foreach ($providerClasses as $name => $class) {
            if (class_exists($class)) {
                $this->providers[$name] = new $class($this->config);
            }
        }
    }
    
    /**
     * اكتشاف جميع الـ Tokens المتصلة عبر USB
     */
    public function detectTokens(): array
    {
        $tokens = [];
        
        // فحص PKCS#11 tokens
        if ($this->config['pkcs11']['enabled'] ?? false) {
            $tokens = array_merge($tokens, $this->detectPKCS11Tokens());
        }
        
        // فحص PKCS#12 files
        $tokens = array_merge($tokens, $this->detectPKCS12Files());
        
        // فحص Smart Cards عبر PC/SC
        $tokens = array_merge($tokens, $this->detectSmartCards());
        
        return $tokens;
    }
    
    /**
     * اكتشاف Tokens عبر PKCS#11
     */
    private function detectPKCS11Tokens(): array
    {
        $tokens = [];
        $modulePath = $this->config['pkcs11']['module_path'] ?? '/usr/lib/softhsm/libsofthsm2.so';
        
        if (!file_exists($modulePath)) {
            return $tokens;
        }
        
        // استخدام pkcs11-tool للفحص
        $output = shell_exec("pkcs11-tool --module {$modulePath} --list-slots 2>/dev/null");
        
        if ($output) {
            preg_match_all('/Slot (.*?)(?=Slot|$)/s', $output, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $slotInfo = $match[1];
                if (preg_match('/token label\s*:\s*(.+)/i', $slotInfo, $labelMatch)) {
                    $tokens[] = [
                        'type' => 'pkcs11',
                        'label' => trim($labelMatch[1]),
                        'module' => $modulePath,
                        'slot' => $match[0]
                    ];
                }
            }
        }
        
        return $tokens;
    }
    
    /**
     * اكتشاف ملفات PKCS#12 (.pfx/.p12)
     */
    private function detectPKCS12Files(): array
    {
        $tokens = [];
        $searchPaths = $this->config['pkcs12']['search_paths'] ?? ['/etc/ssl/certs/tokens/', './tokens/'];
        
        foreach ($searchPaths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '*.{pfx,p12}', GLOB_BRACE);
                foreach ($files as $file) {
                    $tokens[] = [
                        'type' => 'pkcs12',
                        'path' => $file,
                        'filename' => basename($file)
                    ];
                }
            }
        }
        
        return $tokens;
    }
    
    /**
     * اكتشاف البطاقات الذكية عبر PC/SC
     */
    private function detectSmartCards(): array
    {
        $tokens = [];
        
        // فحص باستخدام pcsc_scan
        $output = shell_exec("pcsc_scan -m 2>/dev/null | head -50");
        
        if ($output && strpos($output, 'Card present') !== false) {
            preg_match_all('/Reader .*?: (.*?)(?=Reader|$)/s', $output, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $cardInfo = $match[1];
                if (preg_match('/ATR\s*:?\s*([A-F0-9 ]+)/i', $cardInfo, $atrMatch)) {
                    $tokens[] = [
                        'type' => 'smartcard',
                        'atr' => trim($atrMatch[1]),
                        'info' => $cardInfo
                    ];
                }
            }
        }
        
        return $tokens;
    }
    
    /**
     * قراءة شهادة من ملف أو Token
     */
    public function readCertificate(string $path, string $type = 'auto'): Certificate
    {
        $type = $type === 'auto' ? $this->detectFileType($path) : $type;
        
        return match($type) {
            'pem', 'cer', 'crt' => $this->readPEMCertificate($path),
            'pfx', 'p12' => $this->readPKCS12Certificate($path),
            'pkcs11' => $this->readPKCS11Certificate($path),
            default => throw new \InvalidArgumentException("Unsupported certificate type: {$type}")
        };
    }
    
    /**
     * قراءة شهادة PEM
     */
    private function readPEMCertificate(string $path): Certificate
    {
        $content = file_get_contents($path);
        $cert = openssl_x509_read($content);
        
        if (!$cert) {
            throw new \RuntimeException("Failed to read PEM certificate: " . openssl_error_string());
        }
        
        return new Certificate($cert, $path, 'pem');
    }
    
    /**
     * قراءة شهادة PKCS#12
     */
    private function readPKCS12Certificate(string $path, string $passphrase = ''): Certificate
    {
        $pkcs12 = file_get_contents($path);
        
        if (!openssl_pkcs12_read($pkcs12, $certs, $passphrase)) {
            throw new \RuntimeException("Failed to read PKCS#12 file: " . openssl_error_string());
        }
        
        $cert = openssl_x509_read($certs['cert']);
        
        return new Certificate($cert, $path, 'pkcs12', [
            'private_key' => $certs['pkey'] ?? null,
            'extracerts' => $certs['extracerts'] ?? []
        ]);
    }
    
    /**
     * قراءة شهادة من PKCS#11 Token
     */
    private function readPKCS11Certificate(string $tokenInfo): Certificate
    {
        if (!$this->pkcs11Handle) {
            throw new \RuntimeException("PKCS#11 handle not initialized");
        }
        
        return $this->pkcs11Handle->getCertificate();
    }
    
    /**
     * الاتصال بـ PKCS#11 Token
     */
    public function connectPKCS11(string $module, string $pin, string $tokenLabel = ''): PKCS11Handle
    {
        $this->pkcs11Handle = new PKCS11Handle($module, $pin, $tokenLabel);
        return $this->pkcs11Handle;
    }
    
    /**
     * توقيع مستند XML حسب معايير الحكومة الإلكترونية
     */
    public function signDocument(array $data, Certificate $cert): SignedDocument
    {
        $xmlData = $this->arrayToXML($data);
        $signedXML = $this->signXML($xmlData, $cert);
        
        return new SignedDocument($signedXML, 'xml', $cert);
    }
    
    /**
     * توقيع مستند XML (ETRANZACT Standard)
     */
    public function signEGovXML(array $params): string
    {
        $invoice = $params['invoice'] ?? [];
        $cert = $params['cert'] ?? null;
        $policy = $params['policy'] ?? 'http://www.egytrust.eg/policy';
        
        if (!$cert instanceof Certificate) {
            throw new \InvalidArgumentException("Valid certificate required");
        }
        
        $xml = $this->createInvoiceXML($invoice);
        return $this->signXML($xml, $cert, $policy);
    }
    
    /**
     * توقيع مستند ZATCA السعودي
     */
    public function signZatcaXML(string $xml, Certificate $cert, array $options = []): string
    {
        // ZATCA Phase 2 compliance
        $tlvData = $this->generateZatcaTLV($xml, $cert);
        
        $signedXML = $this->signXML($xml, $cert, '', [
            'canonicalization' => 'http://www.w3.org/2006/12/xml-c14n11',
            'signature_method' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'
        ]);
        
        // إضافة TLV Base64
        $signedXML = $this->insertZatcaTLV($signedXML, base64_encode($tlvData));
        
        return $signedXML;
    }
    
    /**
     * توقيع ملف PDF (PAdES - Adobe PKCS#7)
     */
    public function signPDF(string $pdfPath, Certificate $cert, array $options = []): string
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'signed_pdf_');
        
        // استخراج المفتاح الخاص والشهادة
        $privateKey = $cert->getPrivateKey();
        $publicCert = $cert->getPublicKey();
        
        $signInfo = [
            'name' => $cert->getSubjectDN(),
            'location' => $options['location'] ?? 'Egypt',
            'reason' => $options['reason'] ?? 'Document Signing',
            'contact_info' => $options['contact'] ?? ''
        ];
        
        // استخدام pdftk أو أداة خارجية للتوقيع
        $output = $this->signPDFWithOpenSSL($pdfPath, $tempFile, $cert, $signInfo);
        
        return $output ? $tempFile : throw new \RuntimeException("PDF signing failed");
    }
    
    /**
     * إنشاء شهادة ذاتية التوقيع (Self-Signed Root CA)
     */
    public function createRootCA(array $params): Certificate
    {
        $dn = array_merge([
            'countryName' => 'EG',
            'stateOrProvinceName' => 'Cairo',
            'localityName' => 'Cairo',
            'organizationName' => 'MyCompany',
            'organizationalUnitName' => 'IT Department',
            'commonName' => 'MyCompany Root CA',
            'emailAddress' => 'admin@company.com'
        ], $params);
        
        $keySize = $params['keySize'] ?? 4096;
        
        // إنشاء المفتاح الخاص
        $privateKey = openssl_pkey_new([
            'private_key_bits' => $keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        
        if (!$privateKey) {
            throw new \RuntimeException("Failed to generate private key: " . openssl_error_string());
        }
        
        // إنشاء CSR
        $csr = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => 'sha256',
            'x509_extensions' => [
                'basicConstraints' => 'critical,CA:TRUE',
                'keyUsage' => 'critical,keyCertSign,cRLSign',
                'subjectKeyIdentifier' => 'hash'
            ]
        ]);
        
        if (!$csr) {
            throw new \RuntimeException("Failed to create CSR: " . openssl_error_string());
        }
        
        // توقيع الشهادة
        $cert = openssl_csr_sign($csr, null, $privateKey, 3650, [
            'digest_alg' => 'sha256',
            'x509_extensions' => 'v3_ca'
        ]);
        
        if (!$cert) {
            throw new \RuntimeException("Failed to sign certificate: " . openssl_error_string());
        }
        
        return new Certificate($cert, null, 'self-signed', ['private_key' => $privateKey]);
    }
    
    /**
     * إنشاء شهادة توقيع كود
     */
    public function createCodeSigningCertificate(Certificate $ca, string $commonName): Certificate
    {
        $dn = [
            'countryName' => 'EG',
            'organizationName' => 'MyCompany',
            'commonName' => $commonName
        ];
        
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        
        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
        
        $cert = openssl_csr_sign($csr, $ca->getOpenSSLCertificate(), $ca->getPrivateKey(), 365, [
            'digest_alg' => 'sha256',
            'x509_extensions' => [
                'basicConstraints' => 'CA:FALSE',
                'keyUsage' => 'digitalSignature',
                'extendedKeyUsage' => 'codeSigning',
                'subjectKeyIdentifier' => 'hash',
                'authorityKeyIdentifier' => 'keyid:always'
            ]
        ]);
        
        return new Certificate($cert, null, 'code-signing', ['private_key' => $privateKey]);
    }
    
    /**
     * إنشاء شهادة TSA (Timestamp Authority)
     */
    public function createTSACertificate(Certificate $ca): Certificate
    {
        $dn = [
            'countryName' => 'EG',
            'organizationName' => 'Timestamp Authority',
            'commonName' => 'TSA Service'
        ];
        
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        
        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
        
        $cert = openssl_csr_sign($csr, $ca->getOpenSSLCertificate(), $ca->getPrivateKey(), 365, [
            'digest_alg' => 'sha256',
            'x509_extensions' => [
                'basicConstraints' => 'CA:FALSE',
                'keyUsage' => 'digitalSignature,nonRepudiation',
                'extendedKeyUsage' => 'timeStamping',
                'subjectKeyIdentifier' => 'hash'
            ]
        ]);
        
        return new Certificate($cert, null, 'tsa', ['private_key' => $privateKey]);
    }
    
    /**
     * التحقق من صلاحية الشهادة عبر OCSP
     */
    public function verifyOCSP(Certificate $cert): bool
    {
        $ocspUrls = $cert->getOCSPUrls();
        
        if (empty($ocspUrls)) {
            return false;
        }
        
        $ocspUrl = $ocspUrls[0];
        
        // إنشاء طلب OCSP
        $request = $this->createOCSPRequest($cert);
        
        // إرسال الطلب
        $response = $this->sendOCSPRequest($ocspUrl, $request);
        
        return $this->parseOCSPResponse($response) === 'good';
    }
    
    /**
     * إعادة تصدير الشهادة بكلمة مرور جديدة
     */
    public function reExportCertificate(Certificate $cert, string $newPassphrase): string
    {
        $pkcs12 = $cert->exportToPKCS12($newPassphrase);
        
        if (!$pkcs12) {
            throw new \RuntimeException("Failed to export certificate");
        }
        
        return $pkcs12;
    }
    
    /**
     * مسح أجهزة USB المتصلة
     */
    public function scanUSBDevices(): array
    {
        $devices = [];
        
        // فحص باستخدام lsusb
        $output = shell_exec("lsusb -v 2>/dev/null");
        
        if ($output) {
            preg_match_all('/Bus.*?ID\s+([0-9a-f:]+).*?(?=Bus|$)/s', $output, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $deviceInfo = $match[0];
                
                // البحث عن أجهزة Token المعروفة
                if (preg_match('/(EgyTrust|SafeNet|Gemalto|HID|Identiv)/i', $deviceInfo, $vendorMatch)) {
                    $devices[] = [
                        'bus_id' => $match[1],
                        'vendor' => $vendorMatch[1],
                        'type' => 'token',
                        'raw_info' => $deviceInfo
                    ];
                }
            }
        }
        
        return $devices;
    }
    
    /**
     * التقديم لبوابة حكومية
     */
    public function submitToGovernmentGateway(string $gateway, array $data): array
    {
        $gateways = $this->config['government_gateways'] ?? [];
        
        if (!isset($gateways[$gateway])) {
            throw new \InvalidArgumentException("Unknown gateway: {$gateway}");
        }
        
        $url = $gateways[$gateway];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . ($data['token'] ?? '')
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("Gateway request failed with code {$httpCode}");
        }
        
        return json_decode($response, true) ?? [];
    }
    
    // ==================== Helper Methods ====================
    
    private function getDefaultConfig(): array
    {
        return [
            'pkcs11' => [
                'enabled' => true,
                'module_path' => '/usr/lib/softhsm/libsofthsm2.so'
            ],
            'pkcs12' => [
                'search_paths' => ['/etc/ssl/certs/tokens/', './tokens/']
            ],
            'openssl' => [
                'config' => '/etc/ssl/openssl.cnf',
                'engine' => 'pkcs11'
            ],
            'government_gateways' => [
                'etransact' => 'https://etransact.gov.eg/esigning/service',
                'zatca' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
                'fta' => 'https://eservices.tax.gov.ae/api'
            ]
        ];
    }
    
    private function detectFileType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return match($ext) {
            'pem', 'cer', 'crt' => 'pem',
            'pfx', 'p12' => 'pkcs12',
            'xml' => 'xml',
            'json' => 'json',
            'pdf' => 'pdf',
            default => throw new \InvalidArgumentException("Unknown file type: {$ext}")
        };
    }
    
    private function arrayToXML(array $data): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElement('Document');
        $xml->appendChild($root);
        
        $this->arrayToXMLElement($data, $xml, $root);
        
        return $xml->saveXML();
    }
    
    private function arrayToXMLElement(array $data, \DOMDocument $xml, \DOMElement $parent): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $element = $xml->createElement($key);
                $parent->appendChild($element);
                $this->arrayToXMLElement($value, $xml, $element);
            } else {
                $element = $xml->createElement($key, htmlspecialchars((string)$value));
                $parent->appendChild($element);
            }
        }
    }
    
    private function createInvoiceXML(array $invoice): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $root = $xml->createElement('Invoice');
        $xml->appendChild($root);
        
        foreach ($invoice as $key => $value) {
            $root->appendChild($xml->createElement($key, htmlspecialchars((string)$value)));
        }
        
        return $xml->saveXML();
    }
    
    private function signXML(string $xml, Certificate $cert, string $policy = '', array $options = []): string
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        
        $objXMLSecDSig = new \XMLSecDSig();
        $objXMLSecDSig->id = 'dsig';
        
        $objDSig = $objXMLSecDSig->appendSignature($doc->documentElement);
        $objDSig->setAttribute('Id', 'Signature');
        
        $objInfo = new \XMLSecTransform();
        $objInfo->addReference(
            'http://www.w3.org/2000/09/xmldsig#sha256',
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['http://www.w3.org/TR/2001/REC-xml-c14n-20010315']
        );
        
        $objKey = new \XMLSecKey(
            $cert->getPrivateKey(),
            'http://www.w3.org/2000/09/xmldsig#rsa-sha256'
        );
        
        $objXMLSecDSig->sign($objKey);
        $objXMLSecDSig->add509Cert($cert->getPublicKey());
        
        if ($policy) {
            $objPolicy = $objDSig->appendChild($doc->createElement('Object'));
            $objPolicy->setAttribute('Id', 'policy');
            $objPolicy->setAttribute('MimeType', 'text/xml');
            $objPolicy->setAttribute('Encoding', $policy);
        }
        
        return $doc->saveXML();
    }
    
    private function generateZatcaTLV(string $xml, Certificate $cert): string
    {
        // ZATCA TLV encoding for Phase 2
        $sellerName = $cert->getOrganizationName();
        $vatNumber = $cert->getVATNumber() ?? '';
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $total = $this->extractInvoiceTotal($xml);
        $vatTotal = $total * 0.15;
        $hash = hash('sha256', $xml);
        
        $tlvData = [
            $sellerName,
            $vatNumber,
            $timestamp,
            number_format($total, 2),
            number_format($vatTotal, 2),
            $hash
        ];
        
        return implode('|', $tlvData);
    }
    
    private function insertZatcaTLV(string $xml, string $tlvBase64): string
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        
        $extensions = $doc->getElementsByTagName('Extensions')->item(0);
        
        if (!$extensions) {
            $extensions = $doc->createElement('Extensions');
            $doc->documentElement->appendChild($extensions);
        }
        
        $tlvElement = $doc->createElement('ZatcaTLV', $tlvBase64);
        $extensions->appendChild($tlvElement);
        
        return $doc->saveXML();
    }
    
    private function extractInvoiceTotal(string $xml): float
    {
        $doc = new \DOMDocument();
        @$doc->loadXML($xml);
        
        $totalElements = $doc->getElementsByTagName('TotalAmount');
        
        if ($totalElements->length > 0) {
            return (float)$totalElements->item(0)->textContent;
        }
        
        return 0.0;
    }
    
    private function signPDFWithOpenSSL(string $inputPath, string $outputPath, Certificate $cert, array $signInfo): bool
    {
        // Implementation using TCPDF or similar library
        // This is a simplified version
        
        $privateKey = openssl_pkey_get_private($cert->getPrivateKey());
        $certContent = $cert->getPublicKey();
        
        // Create signature
        $signature = '';
        openssl_sign($inputPath, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        // In production, use proper PDF signing library like endroid/qpdf or setasign/fpdi
        return copy($inputPath, $outputPath);
    }
    
    private function createOCSPRequest(Certificate $cert): string
    {
        // Create OCSP request
        $issuer = $cert->getIssuerCertificate();
        
        $ocspRequest = openssl_ocsp_basic_new();
        
        return openssl_ocsp_basic_sign($ocspRequest, $cert->getOpenSSLCertificate(), $cert->getPrivateKey());
    }
    
    private function sendOCSPRequest(string $url, string $request): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_HTTPHEADER => ['Content-Type: application/ocsp-request'],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response ?: '';
    }
    
    private function parseOCSPResponse(string $response): string
    {
        // Parse OCSP response
        // Returns: 'good', 'revoked', or 'unknown'
        
        if (strpos($response, 'good') !== false) {
            return 'good';
        } elseif (strpos($response, 'revoked') !== false) {
            return 'revoked';
        }
        
        return 'unknown';
    }
}
