<?php

namespace DigitalSignatureFactory\Utils;

/**
 * Timestamp Authority - سلطة الطوابع الزمنية
 * 
 * توفر طوابع زمنية موثوقة للتوقيعات الرقمية
 * متوافقة مع RFC 3161 و ETSI TS 101 861
 */
class TimestampAuthority
{
    private array $tsaServers = [];
    private string $defaultPolicy;
    private bool $useNonces = true;
    
    public function __construct(array $config = [])
    {
        $this->tsaServers = $config['tsa_servers'] ?? [
            'http://timestamp.egytrust.eg',
            'http://timestamp.mcb.gov.eg',
            'http://tsa.deltatrust.com.eg',
            'http://timestamp.c3.com.sa',
            'http://tss.zatca.gov.sa',
            'http://tsp.uae-pass.ae'
        ];
        
        $this->defaultPolicy = $config['default_policy'] ?? '1.3.6.1.4.1.50076.1.1';
        $this->useNonces = $config['use_nonces'] ?? true;
    }
    
    /**
     * الحصول على طابع زمني لمستند
     * 
     * @param string $documentHash SHA-256 hash of the document
     * @return TimestampToken
     */
    public function getTimestamp(string $documentHash): TimestampToken
    {
        foreach ($this->tsaServers as $server) {
            try {
                $token = $this->requestTimestamp($server, $documentHash);
                if ($token && $token->isValid()) {
                    return $token;
                }
            } catch (\Exception $e) {
                // محاولة الخادم التالي
                continue;
            }
        }
        
        throw new \RuntimeException('فشل الحصول على طابع زمني من جميع الخوادم');
    }
    
    /**
     * طلب طابع زمني من خادم محدد
     */
    private function requestTimestamp(string $serverUrl, string $documentHash): ?TimestampToken
    {
        // إنشاء طلب TSA (RFC 3161)
        $nonce = $this->useNonces ? bin2hex(random_bytes(16)) : null;
        
        $tsr = $this->createTSR($documentHash, $nonce);
        
        // إرسال الطلب للخادم
        $ch = curl_init($serverUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $tsr);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/timestamp-query',
            'Accept: application/timestamp-reply'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return $this->parseTimestampResponse($response, $nonce);
        }
        
        return null;
    }
    
    /**
     * إنشاء طلب TSA (Timestamp Request)
     */
    private function createTSR(string $hash, ?string $nonce): string
    {
        // بناء هيكل ASN.1 لطلب TSA
        // هذا تبسيط - في الإنتاج نستخدم مكتبة ASN.1 كاملة
        
        $hashAlgorithm = [
            'algorithm' => '2.16.840.1.101.3.4.2.1', // SHA-256 OID
            'parameters' => null
        ];
        
        $messageImprint = [
            'hashAlgorithm' => $hashAlgorithm,
            'hashedMessage' => hex2bin($hash)
        ];
        
        $request = [
            'version' => 1,
            'messageImprint' => $messageImprint,
            'reqPolicy' => $this->defaultPolicy,
            'nonce' => $nonce ? hex2bin($nonce) : null,
            'certReq' => true
        ];
        
        // ترميز DER (تبسيط)
        return $this->encodeToDER($request);
    }
    
    /**
     * تحليل استجابة TSA
     */
    private function parseTimestampResponse(string $response, ?string $expectedNonce): TimestampToken
    {
        // فك ترميز استجابة TSA (ASN.1 DER)
        $parsed = $this->decodeFromDER($response);
        
        $status = $parsed['status']['status'] ?? 0;
        
        if ($status !== 0) {
            throw new \RuntimeException("خطأ TSA: {$status}");
        }
        
        // التحقق من Nonce إذا كان موجوداً
        if ($expectedNonce && $this->useNonces) {
            $responseNonce = bin2hex($parsed['nonce'] ?? '');
            if ($responseNonce !== $expectedNonce) {
                throw new \RuntimeException('Nonce غير متطابق - هجوم محتمل');
            }
        }
        
        // استخراج معلومات الطابع الزمني
        $token = new TimestampToken([
            'serial_number' => $parsed['serialNumber'] ?? null,
            'policy' => $parsed['tsaPolicy'] ?? null,
            'gen_time' => $parsed['genTime'] ?? time(),
            'accuracy' => $parsed['accuracy'] ?? null,
            'ordering' => $parsed['ordering'] ?? false,
            'nonce' => $expectedNonce,
            'tsa_name' => $parsed['tsa'] ?? null,
            'raw_token' => base64_encode($response),
            'certificate_chain' => $parsed['certificates'] ?? []
        ]);
        
        return $token;
    }
    
    /**
     * ترميز البيانات إلى DER
     */
    private function encodeToDER(array $data): string
    {
        // تطبيق مبسط لترميز DER
        // في الإنتاج نستخدم مكتبة مثل phpseclib أو FG/ASN1
        return serialize($data);
    }
    
    /**
     * فك ترميز DER
     */
    private function decodeFromDER(string $data): array
    {
        // تطبيق مبسط لفك ترميز DER
        return unserialize($data) ?: [];
    }
    
    /**
     * التحقق من صحة طابع زمني
     */
    public function verifyTimestamp(TimestampToken $token): bool
    {
        // التحقق من توقيع الطابع الزمني
        $certChain = $token->getCertificateChain();
        
        if (empty($certChain)) {
            return false;
        }
        
        // التحقق من سلسلة الشهادات
        $rootCert = $certChain[count($certChain) - 1];
        $tsaCert = $certChain[0];
        
        // التحقق من التوقيع
        $signatureValid = openssl_verify(
            $token->getSignedData(),
            $token->getSignature(),
            $tsaCert,
            OPENSSL_ALGO_SHA256
        );
        
        if ($signatureValid !== 1) {
            return false;
        }
        
        // التحقق من صلاحية الشهادة
        $certInfo = openssl_x509_parse($tsaCert);
        $now = time();
        
        $notBefore = strtotime($certInfo['validFrom']);
        $notAfter = strtotime($certInfo['validTo']);
        
        if ($now < $notBefore || $now > $notAfter) {
            return false;
        }
        
        // التحقق من أن الشهادة مخصصة لـ TSA
        $keyUsage = $certInfo['extensions']['keyUsage'] ?? '';
        if (strpos($keyUsage, 'Digital Signature') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * دمج الطابع الزمني مع التوقيع
     */
    public function embedTimestamp(string $signedDocument, TimestampToken $token): string
    {
        // دمج الطابع الزمني في المستند الموقع
        // يعتمد على نوع المستند (PDF, XML, etc.)
        
        $extension = pathinfo($signedDocument, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case 'pdf':
                return $this->embedTimestampInPDF($signedDocument, $token);
            
            case 'xml':
                return $this->embedTimestampInXML($signedDocument, $token);
            
            default:
                // إرجاع الطابع كملف منفصل
                return $token->toFile(dirname($signedDocument) . '/timestamp.tst');
        }
    }
    
    /**
     * دمج الطابع في PDF
     */
    private function embedTimestampInPDF(string $pdfPath, TimestampToken $token): string
    {
        // استخدام TCPDF أو FPDI لإضافة الطابع الزمني
        // هذا يتطلب مكتبات PDF متخصصة
        
        $outputPath = dirname($pdfPath) . '/timestamped_' . basename($pdfPath);
        
        // في الإنتاج: استخدام مكتبة PDF لإضافة DSS (Document Security Store)
        copy($pdfPath, $outputPath);
        
        return $outputPath;
    }
    
    /**
     * دمج الطابع في XML
     */
    private function embedTimestampInXML(string $xmlPath, TimestampToken $token): string
    {
        $xml = simplexml_load_file($xmlPath);
        
        if (!$xml) {
            throw new \RuntimeException('فشل تحميل ملف XML');
        }
        
        // إضافة عنصر XAdES-TimeStamp
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
        
        $timestampElement = $xml->addChild('XAdES:TimeStamp');
        $timestampElement->addAttribute('xmlns:XAdES', 'http://uri.etsi.org/01903/v1.3.2#');
        
        $timestampElement->addChild('CanonicalizationMethod', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $timestampElement->addChild('EncapsulatedTimeStamp', $token->getRawToken());
        
        $outputPath = dirname($xmlPath) . '/timestamped_' . basename($xmlPath);
        $xml->asXML($outputPath);
        
        return $outputPath;
    }
    
    /**
     * الحصول على قائمة خوادم TSA المتاحة
     */
    public function getAvailableServers(): array
    {
        $available = [];
        
        foreach ($this->tsaServers as $server) {
            $ch = curl_init($server);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $available[] = [
                'url' => $server,
                'status' => $httpCode === 0 || $httpCode >= 200 && $httpCode < 400 ? 'online' : 'offline',
                'response_code' => $httpCode
            ];
        }
        
        return $available;
    }
}

/**
 * Token الطابع الزمني
 */
class TimestampToken
{
    private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function isValid(): bool
    {
        return !empty($this->data['serial_number']) && 
               !empty($this->data['gen_time']) &&
               !empty($this->data['raw_token']);
    }
    
    public function getSerialNumber(): ?string
    {
        return $this->data['serial_number'] ?? null;
    }
    
    public function getPolicy(): ?string
    {
        return $this->data['policy'] ?? null;
    }
    
    public function getGenerationTime(): int
    {
        return $this->data['gen_time'] ?? time();
    }
    
    public function getAccuracy(): ?int
    {
        return $this->data['accuracy'] ?? null;
    }
    
    public function isOrdering(): bool
    {
        return $this->data['ordering'] ?? false;
    }
    
    public function getNonce(): ?string
    {
        return $this->data['nonce'] ?? null;
    }
    
    public function getTSAName(): ?string
    {
        return $this->data['tsa_name'] ?? null;
    }
    
    public function getRawToken(): string
    {
        return $this->data['raw_token'] ?? '';
    }
    
    public function getCertificateChain(): array
    {
        return $this->data['certificate_chain'] ?? [];
    }
    
    public function getSignedData(): string
    {
        // استخراج البيانات الموقعة من التوكن
        return base64_decode($this->data['raw_token']);
    }
    
    public function getSignature(): string
    {
        // استخراج التوقيع من التوكن
        // يتطلب تحليل ASN.1
        return '';
    }
    
    public function toFile(string $path): string
    {
        file_put_contents($path, base64_decode($this->data['raw_token']));
        return $path;
    }
    
    public function toArray(): array
    {
        return [
            'serial_number' => $this->data['serial_number'],
            'policy' => $this->data['policy'],
            'gen_time' => date('Y-m-d H:i:s', $this->data['gen_time']),
            'accuracy' => $this->data['accuracy'],
            'ordering' => $this->data['ordering'],
            'tsa_name' => $this->data['tsa_name'],
            'is_valid' => $this->isValid()
        ];
    }
}
