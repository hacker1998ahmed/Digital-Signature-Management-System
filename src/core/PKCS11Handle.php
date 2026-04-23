<?php

namespace DigitalSignatureFactory\Core;

/**
 * PKCS11Handle - مقبض الاتصال بـ PKCS#11 Tokens
 * 
 * يدعم جميع أنواع الـ Tokens المصرية:
 * - EgyTrust Tokens
 * - MCB Tokens
 * - DeltaTrust Tokens
 * - C3 HSM/SmartCard
 */
class PKCS11Handle
{
    private string $modulePath;
    private string $pin;
    private string $tokenLabel;
    private ?resource $session = null;
    private array $slotInfo = [];
    private array $tokenInfo = [];
    
    public function __construct(string $modulePath, string $pin, string $tokenLabel = '')
    {
        $this->modulePath = $modulePath;
        $this->pin = $pin;
        $this->tokenLabel = $tokenLabel;
        
        $this->initialize();
    }
    
    /**
     * تهيئة اتصال PKCS#11
     */
    private function initialize(): void
    {
        if (!file_exists($this->modulePath)) {
            throw new \RuntimeException("PKCS#11 module not found: {$this->modulePath}");
        }
        
        // استخدام pkcs11-tool للاتصال
        $this->discoverSlots();
        $this->login();
    }
    
    /**
     * اكتشاف الـ Slots المتاحة
     */
    private function discoverSlots(): void
    {
        $output = shell_exec("pkcs11-tool --module {$this->modulePath} --list-slots 2>&1");
        
        if (!$output) {
            throw new \RuntimeException("Failed to discover PKCS#11 slots");
        }
        
        // تحليل المخرجات
        preg_match_all('/Slot (.*?)(?=Slot|$)/s', $output, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $slotData = $match[1];
            
            if (preg_match('/token label\\s*:\\s*(.+)/i', $slotData, $labelMatch)) {
                $label = trim($labelMatch[1]);
                
                if (empty($this->tokenLabel) || strpos($label, $this->tokenLabel) !== false) {
                    $this->slotInfo = [
                        'label' => $label,
                        'raw' => $slotData
                    ];
                    
                    // استخراج معلومات إضافية
                    if (preg_match('/token flags\\s*:\\s*(.+)/i', $slotData, $flagsMatch)) {
                        $this->tokenInfo['flags'] = trim($flagsMatch[1]);
                    }
                    
                    if (preg_match('/serial number\\s*:\\s*(.+)/i', $slotData, $serialMatch)) {
                        $this->tokenInfo['serial'] = trim($serialMatch[1]);
                    }
                    
                    if (preg_match('/manufacturer ID\\s*:\\s*(.+)/i', $slotData, $mfgMatch)) {
                        $this->tokenInfo['manufacturer'] = trim($mfgMatch[1]);
                    }
                    
                    if (preg_match('/model\\s*:\\s*(.+)/i', $slotData, $modelMatch)) {
                        $this->tokenInfo['model'] = trim($modelMatch[1]);
                    }
                    
                    break;
                }
            }
        }
        
        if (empty($this->slotInfo)) {
            throw new \RuntimeException("No suitable token found with label: {$this->tokenLabel}");
        }
    }
    
    /**
     * تسجيل الدخول إلى Token
     */
    private function login(): void
    {
        // اختبار الاتصال باستخدام pkcs11-tool
        $testCmd = "pkcs11-tool --module {$this->modulePath} --login --pin {$this->pin} --list-objects 2>&1";
        $output = shell_exec($testCmd);
        
        if ($output === null) {
            throw new \RuntimeException("Failed to login to token. Check PIN and module path.");
        }
        
        // حفظ حالة الجلسة
        $this->session = true;
    }
    
    /**
     * الحصول على الشهادة من Token
     */
    public function getCertificate(): Certificate
    {
        if (!$this->session) {
            throw new \RuntimeException("Not logged in to token");
        }
        
        // استخراج الشهادة باستخدام pkcs11-tool
        $output = shell_exec(
            "pkcs11-tool --module {$this->modulePath} " .
            "--login --pin {$this->pin} " .
            "--read-object --type cert 2>&1"
        );
        
        if (!$output) {
            throw new \RuntimeException("Failed to read certificate from token");
        }
        
        // تحليل الشهادة
        $cert = openssl_x509_read($output);
        
        if (!$cert) {
            throw new \RuntimeException("Invalid certificate format from token");
        }
        
        return new Certificate($cert, null, 'pkcs11', [
            'token_label' => $this->slotInfo['label'],
            'token_serial' => $this->tokenInfo['serial'] ?? ''
        ]);
    }
    
    /**
     * توقيع بيانات باستخدام Token
     */
    public function sign(string $data, string $algorithm = 'sha256'): string
    {
        if (!$this->session) {
            throw new \RuntimeException("Not logged in to token");
        }
        
        // إنشاء ملف مؤقت للبيانات
        $dataFile = tempnam(sys_get_temp_dir(), 'sign_data_');
        file_put_contents($dataFile, $data);
        
        // التوقيع باستخدام pkcs11-tool
        $signatureFile = tempnam(sys_get_temp_dir(), 'signature_');
        
        $cmd = sprintf(
            "pkcs11-tool --module %s --login --pin %s " .
            "--sign --mechanism RSA-PKCS-%s " .
            "--input-file %s --output-file %s 2>&1",
            escapeshellarg($this->modulePath),
            escapeshellarg($this->pin),
            strtoupper($algorithm),
            escapeshellarg($dataFile),
            escapeshellarg($signatureFile)
        );
        
        $output = shell_exec($cmd);
        
        unlink($dataFile);
        
        if (!file_exists($signatureFile)) {
            throw new \RuntimeException("Signing failed: " . ($output ?: 'Unknown error'));
        }
        
        $signature = file_get_contents($signatureFile);
        unlink($signatureFile);
        
        return $signature;
    }
    
    /**
     * الحصول على معلومات Token
     */
    public function getTokenInfo(): array
    {
        return array_merge($this->slotInfo, $this->tokenInfo);
    }
    
    /**
     * سرد جميع الكائنات في Token
     */
    public function listObjects(): array
    {
        if (!$this->session) {
            throw new \RuntimeException("Not logged in to token");
        }
        
        $output = shell_exec(
            "pkcs11-tool --module {$this->modulePath} " .
            "--login --pin {$this->pin} " .
            "--list-objects 2>&1"
        );
        
        if (!$output) {
            return [];
        }
        
        $objects = [];
        
        // تحليل قائمة الكائنات
        preg_match_all('/Object(?:\s+(?:\w+):\s*(.+))+/', $output, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $objects[] = [
                'raw' => $match[0],
                'details' => $match
            ];
        }
        
        return $objects;
    }
    
    /**
     * تغيير PIN
     */
    public function changePIN(string $newPin): bool
    {
        $cmd = sprintf(
            "pkcs11-tool --module %s --login --pin %s " .
            "--change-pin --new-pin %s 2>&1",
            escapeshellarg($this->modulePath),
            escapeshellarg($this->pin),
            escapeshellarg($newPin)
        );
        
        $output = shell_exec($cmd);
        
        if (strpos($output, 'success') !== false || empty($output)) {
            $this->pin = $newPin;
            return true;
        }
        
        return false;
    }
    
    /**
     * إغلاق الجلسة
     */
    public function close(): void
    {
        $this->session = null;
    }
    
    /**
     * التحقق مما إذا كانت الجلسة نشطة
     */
    public function isLoggedIn(): bool
    {
        return $this->session !== null;
    }
    
    /**
     * الحصول على مسار الوحدة
     */
    public function getModulePath(): string
    {
        return $this->modulePath;
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }
}
