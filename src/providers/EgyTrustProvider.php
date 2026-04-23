<?php

namespace DigitalSignatureFactory\Providers;

use DigitalSignatureFactory\Core\Certificate;
use DigitalSignatureFactory\Core\DigitalSignatureFactory;

/**
 * EgyTrustProvider - مزود خدمة إيجي تراست للتوقيع الإلكتروني
 * 
 * يدعم:
 * - ملفات .pfx/.p12
 * - Tokens USB
 * - البوابة الحكومية المصرية ETRANZACT
 */
class EgyTrustProvider
{
    private array $config;
    private ?Certificate $currentCert = null;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * تحميل شهادة من ملف PFX/P12
     */
    public function loadFromPFX(string $filePath, string $passphrase): Certificate
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("PFX file not found: {$filePath}");
        }
        
        $pkcs12 = file_get_contents($filePath);
        
        if (!openssl_pkcs12_read($pkcs12, $certs, $passphrase)) {
            throw new \RuntimeException("Failed to read PFX file. Check passphrase.");
        }
        
        $cert = openssl_x509_read($certs['cert']);
        
        $this->currentCert = new Certificate($cert, $filePath, 'pkcs12', [
            'private_key' => $certs['pkey'] ?? null,
            'extracerts' => $certs['extracerts'] ?? [],
            'provider' => 'EgyTrust'
        ]);
        
        return $this->currentCert;
    }
    
    /**
     * الاتصال بـ Token USB
     */
    public function connectToken(string $pin, string $tokenPath = ''): Certificate
    {
        // استخدام PKCS#11 للاتصال بـ Token
        $modulePath = $this->config['pkcs11']['module_path'] ?? '/usr/lib/softhsm/libsofthsm2.so';
        
        $pkcs11Handle = new \DigitalSignatureFactory\Core\PKCS11Handle($modulePath, $pin, 'EgyTrust');
        
        $this->currentCert = $pkcs11Handle->getCertificate();
        
        return $this->currentCert;
    }
    
    /**
     * توقيع فاتورة إلكترونية حسب معيار ETRANZACT
     */
    public function signInvoice(array $invoiceData): string
    {
        if (!$this->currentCert) {
            throw new \RuntimeException("No certificate loaded. Call loadFromPFX or connectToken first.");
        }
        
        // إنشاء XML الفاتورة
        $xml = $this->createInvoiceXML($invoiceData);
        
        // إضافة التوقيع الرقمي
        $signedXML = $this->signXML($xml);
        
        return $signedXML;
    }
    
    /**
     * التحقق من الشهادة مع OCSP الخاص بـ EgyTrust
     */
    public function verifyWithOCSP(): bool
    {
        if (!$this->currentCert) {
            return false;
        }
        
        $ocspUrls = $this->currentCert->getOCSPUrls();
        
        // استخدام OCSP الخاص بـ EgyTrust إذا لم يوجد في الشهادة
        if (empty($ocspUrls)) {
            $ocspUrls[] = 'http://ocsp.egytrust.eg';
        }
        
        foreach ($ocspUrls as $url) {
            if ($this->checkOCSP($url)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * التقديم للبوابة الحكومية ETRANZACT
     */
    public function submitToETransact(string $signedDocument): array
    {
        $gatewayUrl = $this->config['government_gateways']['etransact'] 
            ?? 'https://etransact.gov.eg/esigning/service';
        
        $ch = curl_init($gatewayUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $signedDocument,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml',
                'X-Certificate-Serial' => $this->currentCert->getSerialNumber()
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("ETransact submission failed with code {$httpCode}");
        }
        
        return json_decode($response, true) ?? [];
    }
    
    /**
     * الحصول على معلومات المزود
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => 'EgyTrust',
            'fullName' => 'Egyptian Trust for Digital Signatures',
            'website' => 'https://www.egytrust.eg',
            'supportEmail' => 'support@egytrust.eg',
            'ocspUrl' => 'http://ocsp.egytrust.eg',
            'crlUrl' => 'http://crl.egytrust.eg',
            'supportedFormats' => ['pfx', 'p12', 'pem'],
            'tokenSupport' => true,
            'hsmSupport' => false
        ];
    }
    
    /**
     * إنشاء XML الفاتورة
     */
    private function createInvoiceXML(array $data): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $invoice = $xml->createElement('Invoice');
        $invoice->setAttribute('xmlns', 'http://www.etransact.gov.eg/invoice/v1');
        $xml->appendChild($invoice);
        
        // إضافة بيانات الفاتورة
        $fields = [
            'InvoiceNumber', 'IssueDate', 'DueDate',
            'SellerName', 'SellerVATNumber', 'SellerAddress',
            'BuyerName', 'BuyerVATNumber', 'BuyerAddress',
            'TotalAmount', 'VATAmount', 'GrandTotal'
        ];
        
        foreach ($fields as $field) {
            $value = $data[$field] ?? $data[strtolower($field)] ?? '';
            $element = $xml->createElement($field, htmlspecialchars((string)$value));
            $invoice->appendChild($element);
        }
        
        // إضافة البنود
        if (isset($data['items']) && is_array($data['items'])) {
            $itemsElement = $xml->createElement('Items');
            
            foreach ($data['items'] as $item) {
                $itemElement = $xml->createElement('Item');
                
                foreach ($item as $key => $value) {
                    $itemElement->appendChild(
                        $xml->createElement($key, htmlspecialchars((string)$value))
                    );
                }
                
                $itemsElement->appendChild($itemElement);
            }
            
            $invoice->appendChild($itemsElement);
        }
        
        return $xml->saveXML();
    }
    
    /**
     * توقيع XML
     */
    private function signXML(string $xml): string
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        
        // إضافة عنصر Signature
        $signatureNS = 'http://www.w3.org/2000/09/xmldsig#';
        $signature = $doc->createElementNS($signatureNS, 'ds:Signature');
        $signature->setAttribute('xmlns:ds', $signatureNS);
        
        $signedInfo = $doc->createElementNS($signatureNS, 'ds:SignedInfo');
        
        // Canonicalization Method
        $canonMethod = $doc->createElementNS($signatureNS, 'ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);
        
        // Signature Method
        $sigMethod = $doc->createElementNS($signatureNS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sigMethod);
        
        // Reference
        $reference = $doc->createElementNS($signatureNS, 'ds:Reference');
        $reference->setAttribute('URI', '');
        
        $transforms = $doc->createElementNS($signatureNS, 'ds:Transforms');
        $transform = $doc->createElementNS($signatureNS, 'ds:Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);
        $reference->appendChild($transforms);
        
        $digestMethod = $doc->createElementNS($signatureNS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);
        
        $digestValue = $doc->createElementNS($signatureNS, 'ds:DigestValue');
        $digestValue->textContent = base64_encode(hash('sha256', $xml, true));
        $reference->appendChild($digestValue);
        
        $signedInfo->appendChild($reference);
        $signature->appendChild($signedInfo);
        
        // Signature Value
        $signatureValue = $doc->createElementNS($signatureNS, 'ds:SignatureValue');
        $signatureValue->textContent = $this->calculateSignature($signedInfo->C14N());
        $signature->appendChild($signatureValue);
        
        // Key Info
        $keyInfo = $doc->createElementNS($signatureNS, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS($signatureNS, 'ds:X509Data');
        
        $x509Cert = $doc->createElementNS($signatureNS, 'ds:X509Certificate');
        preg_match('/-----BEGIN CERTIFICATE-----(.*)-----END CERTIFICATE-----/s', 
            $this->currentCert->getPublicKey(), $matches);
        $x509Cert->textContent = trim($matches[1] ?? '');
        
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);
        
        // إضافة التوقيع للمستند
        $doc->documentElement->appendChild($signature);
        
        return $doc->saveXML();
    }
    
    /**
     * حساب قيمة التوقيع
     */
    private function calculateSignature(string $data): string
    {
        $privateKey = $this->currentCert->getPrivateKey();
        
        if (!$privateKey) {
            throw new \RuntimeException("Private key not available for signing");
        }
        
        $signature = '';
        
        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException("Signing failed: " . openssl_error_string());
        }
        
        return base64_encode($signature);
    }
    
    /**
     * التحقق من OCSP
     */
    private function checkOCSP(string $url): bool
    {
        $request = $this->createOCSPRequest();
        
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
        
        return $response && strpos(strtolower($response), 'good') !== false;
    }
    
    /**
     * إنشاء طلب OCSP
     */
    private function createOCSPRequest(): string
    {
        // مبسط - في الإنتاج استخدم مكتبة OCSP كاملة
        return '';
    }
}
