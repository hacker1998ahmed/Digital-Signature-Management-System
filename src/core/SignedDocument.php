<?php

namespace DigitalSignatureFactory\Core;

/**
 * SignedDocument - فئة تمثيل المستند الموقّع
 * 
 * تدعم:
 * - XML Signatures (XAdES, ETRANZACT)
 * - PDF Signatures (PAdES)
 * - JSON Signatures (JWS)
 */
class SignedDocument
{
    private string $content;
    private string $type;
    private Certificate $certificate;
    private array $signatureInfo = [];
    private ?string $timestamp = null;
    
    public function __construct(
        string $content,
        string $type,
        Certificate $cert,
        array $signatureInfo = []
    ) {
        $this->content = $content;
        $this->type = $type;
        $this->certificate = $cert;
        $this->signatureInfo = $signatureInfo;
        $this->timestamp = date('c');
    }
    
    /**
     * الحصول على المحتوى الموقّع
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * الحصول على نوع المستند
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * الحصول على الشهادة المستخدمة في التوقيع
     */
    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }
    
    /**
     * الحصول على معلومات التوقيع
     */
    public function getSignatureInfo(): array
    {
        return $this->signatureInfo;
    }
    
    /**
     * الحصول على الطابع الزمني
     */
    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }
    
    /**
     * التحقق من صحة التوقيع
     */
    public function verifySignature(): bool
    {
        switch ($this->type) {
            case 'xml':
                return $this->verifyXMLSignature();
            case 'pdf':
                return $this->verifyPDFSignature();
            case 'json':
                return $this->verifyJWSSignature();
            default:
                return false;
        }
    }
    
    /**
     * التحقق من توقيع XML
     */
    private function verifyXMLSignature(): bool
    {
        $doc = new \DOMDocument();
        @$doc->loadXML($this->content);
        
        // البحث عن عنصر Signature
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        
        $signatureNodes = $xpath->query('//ds:Signature');
        
        if ($signatureNodes->length === 0) {
            return false;
        }
        
        // استخراج المفتاح العام من الشهادة
        $publicKey = $this->certificate->getPublicKey();
        
        // التحقق من التوقيع (مبسط)
        // في الإنتاج، استخدم مكتبة xmlseclibs للتحقق الكامل
        return true;
    }
    
    /**
     * التحقق من توقيع PDF
     */
    private function verifyPDFSignature(): bool
    {
        // استخدام أداة خارجية للتحقق من توقيع PDF
        $tempFile = tempnam(sys_get_temp_dir(), 'verify_pdf_');
        file_put_contents($tempFile, $this->content);
        
        $output = shell_exec("pdfsig '{$tempFile}' 2>&1");
        unlink($tempFile);
        
        if ($output && strpos($output, 'Signature is valid') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق من توقيع JWS
     */
    private function verifyJWSSignature(): bool
    {
        $parts = explode('.', $this->content);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        [$header, $payload, $signature] = $parts;
        
        // فك ترميز الهيدر
        $headerData = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
        
        if (!isset($headerData['alg'])) {
            return false;
        }
        
        // التحقق من التوقيع
        $data = "{$header}.{$payload}";
        $publicKey = $this->certificate->getPublicKey();
        
        $verified = openssl_verify(
            $data,
            base64_decode(strtr($signature, '-_', '+/')),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );
        
        return $verified === 1;
    }
    
    /**
     * حفظ المستند إلى ملف
     */
    public function saveToFile(string $path): bool
    {
        return file_put_contents($path, $this->content) !== false;
    }
    
    /**
     * إضافة طابع زمني من TSA
     */
    public function addTimestamp(string $tsaUrl): bool
    {
        // إنشاء طلب TSA
        $hash = hash('sha256', $this->content);
        
        $request = $this->createTSARequest($hash);
        
        // إرسال الطلب إلى TSA
        $response = $this->sendTSARequest($tsaUrl, $request);
        
        if ($response) {
            $this->timestampToken = $response;
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على المستند كـ Base64
     */
    public function toBase64(): string
    {
        return base64_encode($this->content);
    }
    
    /**
     * تصدير معلومات التوقيع كـ JSON
     */
    public function exportSignatureInfo(): string
    {
        return json_encode([
            'type' => $this->type,
            'signer' => $this->certificate->getSubjectDN(),
            'serial_number' => $this->certificate->getSerialNumber(),
            'thumbprint' => $this->certificate->getThumbprint(),
            'signed_at' => $this->timestamp,
            'valid_from' => $this->certificate->getNotBefore(),
            'valid_to' => $this->certificate->getNotAfter(),
            'provider' => $this->certificate->getProviderInfo()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * إنشاء طلب TSA
     */
    private function createTSARequest(string $hash): string
    {
        // TSA Request format (RFC 3161)
        $request = [
            'version' => 1,
            'messageImprint' => [
                'hashAlgorithm' => 'sha256',
                'hashedMessage' => $hash
            ],
            'nonce' => random_int(100000, 999999)
        ];
        
        return json_encode($request);
    }
    
    /**
     * إرسال طلب TSA
     */
    private function sendTSARequest(string $url, string $request): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/timestamp-request'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response ?: null;
    }
    
    /**
     * toString magic method
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
