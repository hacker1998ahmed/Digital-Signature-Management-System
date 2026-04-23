<?php

namespace DigitalSignatureFactory\Utils;

/**
 * USB Token Scanner - ماسح أجهزة Token USB
 * 
 * يكتشف ويفحص جميع أجهزة Token المتصلة عبر USB
 * يدعم: EgyTrust, MCB, DeltaTrust, C3, IDSigner
 */
class USBTokenScanner
{
    private array $supportedVendors = [
        '096e' => 'EgyTrust',      // Feitian
        '072f' => 'Advanced Card',  // MCB
        '1234' => 'DeltaTrust',     // SafeNet
        '08ff' => 'C3',             // OmniKey
        '04e6' => 'IDSigner',       // SCM
        '058f' => 'Generic SmartCard'
    ];
    
    private array $detectedTokens = [];
    
    /**
     * فحص جميع منافذ USB عن Tokens
     */
    public function scanUSBDevices(): array
    {
        $this->detectedTokens = [];
        
        // طريقة 1: استخدام lsusb (Linux)
        $this->scanWithLSUSB();
        
        // طريقة 2: استخدام PC/SC
        $this->scanWithPCSC();
        
        // طريقة 3: فحص مسارات الأجهزة المعروفة
        $this->scanDevicePaths();
        
        return $this->detectedTokens;
    }
    
    /**
     * الفحص باستخدام lsusb
     */
    private function scanWithLSUSB(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }
        
        $output = shell_exec('lsusb -v 2>/dev/null');
        
        if (!$output) {
            return;
        }
        
        // تحليل مخرجات lsusb
        preg_match_all('/Bus (\d+) Device (\d+): ID ([0-9a-fA-F]+):([0-9a-fA-F]+)(.*?)ID/s', $output, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $busId = $match[1];
            $deviceId = $match[2];
            $vendorId = strtolower($match[3]);
            $productId = strtolower($match[4]);
            $extraInfo = $match[5] ?? '';
            
            // التحقق إذا كان الجهاز مدعوم
            if (isset($this->supportedVendors[$vendorId])) {
                $provider = $this->supportedVendors[$vendorId];
                
                $token = [
                    'type' => 'usb',
                    'bus_id' => $busId,
                    'device_id' => $deviceId,
                    'vendor_id' => $vendorId,
                    'product_id' => $productId,
                    'provider' => $provider,
                    'device_path' => "/dev/bus/usb/{$busId}/{$deviceId}",
                    'is_present' => true,
                    'last_seen' => date('c')
                ];
                
                // محاولة استخراج معلومات إضافية
                $token['serial'] = $this->extractSerialFromLSUSB($extraInfo);
                $token['label'] = $this->detectLabelFromProduct($productId, $provider);
                
                $this->detectedTokens[] = $token;
            }
        }
    }
    
    /**
     * الفحص باستخدام PC/SC
     */
    private function scanWithPCSC(): void
    {
        if (!function_exists('pcsc_connect')) {
            // مكتبة pcsc غير متوفرة
            return;
        }
        
        try {
            // الاتصال بـ PC/SC
            $context = pcsc_establish_context(PCSC_SCOPE_SYSTEM);
            
            if (!$context) {
                return;
            }
            
            // الحصول على قائمة القراء
            $readers = pcsc_list_readers($context);
            
            if (is_array($readers)) {
                foreach ($readers as $reader) {
                    if (trim($reader)) {
                        $tokenInfo = $this->getPCSCReaderInfo($reader);
                        
                        if ($tokenInfo) {
                            $tokenInfo['type'] = 'pcsc';
                            $tokenInfo['reader_name'] = trim($reader);
                            $this->detectedTokens[] = $tokenInfo;
                        }
                    }
                }
            }
            
            pcsc_release_context($context);
            
        } catch (\Exception $e) {
            // PC/SC غير متوفر أو فشل
        }
    }
    
    /**
     * فحص مسارات الأجهزة المعروفة
     */
    private function scanDevicePaths(): void
    {
        $devicePaths = [
            '/dev/ttyUSB0',
            '/dev/ttyUSB1',
            '/dev/ttyACM0',
            '/dev/hidraw0',
            '/dev/hidraw1',
            '/etc/libnss3.db',  // NSS token
            '/var/lib/pkcs11/*'
        ];
        
        foreach ($devicePaths as $path) {
            if (strpos($path, '*') !== false) {
                // Glob pattern
                $files = glob($path);
                if ($files) {
                    foreach ($files as $file) {
                        $this->checkDevicePath($file);
                    }
                }
            } else {
                $this->checkDevicePath($path);
            }
        }
    }
    
    /**
     * التحقق من مسار جهاز
     */
    private function checkDevicePath(string $path): void
    {
        if (file_exists($path)) {
            // تحديد نوع الجهاز
            $provider = $this->identifyProviderByPath($path);
            
            if ($provider) {
                $this->detectedTokens[] = [
                    'type' => 'device',
                    'device_path' => $path,
                    'provider' => $provider,
                    'is_present' => true,
                    'last_seen' => date('c')
                ];
            }
        }
    }
    
    /**
     * الحصول على معلومات قارئ PC/SC
     */
    private function getPCSCReaderInfo(string $readerName): ?array
    {
        try {
            $card = pcsc_connect($readerName, PCSC_SHARE_SHARED);
            
            if (!$card) {
                return null;
            }
            
            // الحصول على ATR
            $atr = pcsc_get_atr($card);
            
            // تحديد المزود من ATR
            $provider = $this->identifyProviderFromATR($atr);
            
            // الحصول على معلومات البطاقة
            $info = pcsc_status($card);
            
            pcsc_disconnect($card, PCSC_UNPOWER_CARD);
            
            return [
                'atr' => bin2hex($atr),
                'provider' => $provider,
                'state' => $info['state'] ?? 'unknown',
                'protocol' => $info['protocol'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * تحديد المزود من ATR
     */
    private function identifyProviderFromATR(string $atr): string
    {
        $atrLower = strtolower($atr);
        
        // أنماط ATR المعروفة
        $patterns = [
            '3b9f' => 'EgyTrust',      // Feitian ePass
            '3b7d' => 'MCB',           // Advanced Card
            '3b65' => 'DeltaTrust',    // SafeNet
            '3bff' => 'C3',            // OmniKey
            '3b95' => 'IDSigner'       // SCM
        ];
        
        foreach ($patterns as $pattern => $provider) {
            if (strpos($atrLower, $pattern) === 0) {
                return $provider;
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * تحديد المزود من مسار الجهاز
     */
    private function identifyProviderByPath(string $path): ?string
    {
        if (strpos($path, 'egytrust') !== false || strpos($path, 'feitian') !== false) {
            return 'EgyTrust';
        }
        
        if (strpos($path, 'mcb') !== false || strpos($path, 'misr_clearing') !== false) {
            return 'MCB';
        }
        
        if (strpos($path, 'delta') !== false || strpos($path, 'safenet') !== false) {
            return 'DeltaTrust';
        }
        
        if (strpos($path, 'c3') !== false || strpos($path, 'omnikey') !== false) {
            return 'C3';
        }
        
        if (strpos($path, 'idsigner') !== false || strpos($path, 'scm') !== false) {
            return 'IDSigner';
        }
        
        return null;
    }
    
    /**
     * استخراج الرقم التسلسلي من lsusb
     */
    private function extractSerialFromLSUSB(string $extraInfo): ?string
    {
        if (preg_match('/iSerial\s+\d+\s+(\S+)/', $extraInfo, $match)) {
            return $match[1];
        }
        
        return null;
    }
    
    /**
     * اكتشاف التسمية من Product ID
     */
    private function detectLabelFromProduct(string $productId, string $provider): string
    {
        $labels = [
            'EgyTrust' => [
                '0801' => 'EgyTrust ePass2003',
                '0802' => 'EgyTrust ePass3000',
                'default' => 'EgyTrust Token'
            ],
            'MCB' => [
                '1234' => 'MCB SecureToken',
                'default' => 'MCB Token'
            ],
            'DeltaTrust' => [
                '5678' => 'DeltaTrust SafeNet',
                'default' => 'DeltaTrust Token'
            ],
            'C3' => [
                '9abc' => 'C3 HSM Module',
                'default' => 'C3 SmartCard'
            ]
        ];
        
        $providerLabels = $labels[$provider] ?? [];
        
        return $providerLabels[$productId] ?? $providerLabels['default'] ?? "{$provider} Token";
    }
    
    /**
     * الحصول على معلومات مفصلة عن Token
     */
    public function getTokenInfo(array $token): array
    {
        if (!isset($token['device_path'])) {
            return ['error' => 'No device path'];
        }
        
        $info = [
            'basic_info' => $token,
            'certificates' => [],
            'storage_info' => [],
            'security_status' => []
        ];
        
        // محاولة قراءة الشهادات من الـ Token
        if ($token['type'] === 'usb' || $token['type'] === 'device') {
            $info['certificates'] = $this->readCertificatesFromToken($token);
        }
        
        // الحصول على معلومات التخزين
        $info['storage_info'] = $this->getTokenStorageInfo($token);
        
        // حالة الأمان
        $info['security_status'] = [
            'pin_required' => true,
            'pin_attempts_remaining' => null,
            'is_locked' => false,
            'last_access' => $token['last_seen'] ?? null
        ];
        
        return $info;
    }
    
    /**
     * قراءة الشهادات من Token
     */
    private function readCertificatesFromToken(array $token): array
    {
        $certificates = [];
        
        // محاولة استخدام PKCS#11
        if ($token['type'] === 'usb' || $token['type'] === 'pcsc') {
            try {
                $pkcs11 = new PKCS11Handle([
                    'module_path' => $this->getPKCS11Module($token['provider']),
                    'slot' => $token['device_path'] ?? 0
                ]);
                
                if ($pkcs11->connect()) {
                    $certs = $pkcs11->getCertificates();
                    
                    foreach ($certs as $cert) {
                        $certificates[] = [
                            'subject' => openssl_x509_parse($cert)['subject'] ?? 'Unknown',
                            'issuer' => openssl_x509_parse($cert)['issuer'] ?? 'Unknown',
                            'serial' => openssl_x509_get($cert, 'serialNumber'),
                            'valid_from' => date('Y-m-d', openssl_x509_get($cert, 'validFrom_time_t')),
                            'valid_to' => date('Y-m-d', openssl_x509_get($cert, 'validTo_time_t'))
                        ];
                    }
                    
                    $pkcs11->disconnect();
                }
            } catch (\Exception $e) {
                // فشل القراءة
            }
        }
        
        return $certificates;
    }
    
    /**
     * الحصول على معلومات تخزين Token
     */
    private function getTokenStorageInfo(array $token): array
    {
        // معلومات تقديرية بناءً على نوع الـ Token
        $storageInfo = [
            'total_memory' => '64 KB',
            'free_memory' => 'Unknown',
            'max_certificates' => 10,
            'max_keys' => 5
        ];
        
        return $storageInfo;
    }
    
    /**
     * الحصول على وحدة PKCS#11 المناسبة
     */
    private function getPKCS11Module(string $provider): string
    {
        $modules = [
            'EgyTrust' => '/usr/lib/egytrust/pkcs11.so',
            'MCB' => '/usr/lib/mcb/pkcs11.so',
            'DeltaTrust' => '/usr/lib/deltatrust/pkcs11.so',
            'C3' => '/usr/lib/c3/pkcs11.so',
            'IDSigner' => '/usr/lib/idsigner/pkcs11.so',
            'default' => '/usr/lib/softhsm/libsofthsm2.so'
        ];
        
        return $modules[$provider] ?? $modules['default'];
    }
    
    /**
     * الانتظار حتى يتم توصيل Token
     */
    public function waitForToken(int $timeout = 30): ?array
    {
        $startTime = time();
        
        while ((time() - $startTime) < $timeout) {
            $tokens = $this->scanUSBDevices();
            
            if (!empty($tokens)) {
                return $tokens[0];
            }
            
            sleep(1);
        }
        
        return null;
    }
    
    /**
     * مراقبة تغييرات USB (Hotplug)
     */
    public function startMonitoring(callable $callback): void
    {
        declare(ticks=1);
        
        pcntl_signal(SIGUSR1, function() use ($callback) {
            $tokens = $this->scanUSBDevices();
            $callback($tokens);
        });
        
        // تشغيل مراقب في الخلفية
        $pid = pcntl_fork();
        
        if ($pid === -1) {
            throw new \RuntimeException('فشل إنشاء عملية المراقبة');
        } elseif ($pid === 0) {
            // عملية الطفل
            while (true) {
                usleep(1000000); // 1 ثانية
                posix_kill(posix_getppid(), SIGUSR1);
            }
        }
    }
}
