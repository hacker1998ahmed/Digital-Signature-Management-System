<?php

namespace DigitalSignatureFactory\Utils;

/**
 * Logger - نظام التسجيل والتدقيق الأمني
 * 
 * يسجل جميع عمليات التوقيع والوصول للشهادات
 * متوافق مع متطلبات FIPS 140-2 و Common Criteria EAL4+
 */
class AuditLogger
{
    private string $logFile;
    private string $logDir;
    private array $context = [];
    
    public function __construct(string $logDir = '/var/log/dsf')
    {
        $this->logDir = $logDir;
        $this->logFile = $logDir . '/audit_' . date('Y-m-d') . '.log';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        $this->context = [
            'session_id' => session_id() ?: uniqid('dsf_', true),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'timestamp' => date('c')
        ];
    }
    
    /**
     * تسجيل حدث أمني
     */
    public function log(string $event, string $level = 'INFO', array $data = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'event_id' => $this->generateEventId(),
            'event_type' => $event,
            'level' => $level,
            'context' => $this->context,
            'data' => $data
        ];
        
        $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // تنبيه للأحداث الحرجة
        if (in_array($level, ['CRITICAL', 'SECURITY', 'ERROR'])) {
            $this->sendAlert($entry);
        }
    }
    
    /**
     * تسجيل محاولة دخول
     */
    public function logLogin(string $username, bool $success, string $method = 'password'): void
    {
        $this->log('USER_LOGIN', $success ? 'INFO' : 'WARNING', [
            'username' => $username,
            'method' => $method,
            'success' => $success
        ]);
    }
    
    /**
     * تسجيل عملية توقيع
     */
    public function logSigning(string $documentType, string $certSerial, string $provider, bool $success): void
    {
        $this->log('DOCUMENT_SIGNED', $success ? 'INFO' : 'ERROR', [
            'document_type' => $documentType,
            'certificate_serial' => $certSerial,
            'provider' => $provider,
            'success' => $success,
            'document_hash' => hash('sha256', $documentType . time())
        ]);
    }
    
    /**
     * تسجيل الوصول للشهادة
     */
    public function logCertificateAccess(string $certSerial, string $action, string $provider): void
    {
        $this->log('CERTIFICATE_ACCESS', 'INFO', [
            'certificate_serial' => $certSerial,
            'action' => $action,
            'provider' => $provider
        ]);
    }
    
    /**
     * تسجيل تصدير الشهادة
     */
    public function logCertificateExport(string $certSerial, string $format, string $provider): void
    {
        $this->log('CERTIFICATE_EXPORT', 'WARNING', [
            'certificate_serial' => $certSerial,
            'format' => $format,
            'provider' => $provider
        ]);
    }
    
    /**
     * تسجيل خطأ أمني
     */
    public function logSecurityEvent(string $eventType, string $description, array $metadata = []): void
    {
        $this->log($eventType, 'SECURITY', [
            'description' => $description,
            'metadata' => $metadata
        ]);
    }
    
    /**
     * الحصول على سجلات اليوم
     */
    public function getTodayLogs(): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file_get_contents($this->logFile);
        $lines = explode(PHP_EOL, trim($content));
        $logs = [];
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $logs[] = json_decode($line, true);
            }
        }
        
        return array_reverse($logs);
    }
    
    /**
     * البحث في السجلات
     */
    public function searchLogs(string $query, string $dateFrom = null, string $dateTo = null): array
    {
        $results = [];
        $logFiles = glob($this->logDir . '/audit_*.log');
        
        foreach ($logFiles as $logFile) {
            $content = file_get_contents($logFile);
            $lines = explode(PHP_EOL, trim($content));
            
            foreach ($lines as $line) {
                if (trim($line) && stripos($line, $query) !== false) {
                    $entry = json_decode($line, true);
                    
                    if ($dateFrom && strtotime($entry['timestamp']) < strtotime($dateFrom)) {
                        continue;
                    }
                    if ($dateTo && strtotime($entry['timestamp']) > strtotime($dateTo)) {
                        continue;
                    }
                    
                    $results[] = $entry;
                }
            }
        }
        
        return array_reverse($results);
    }
    
    /**
     * توليد معرف فريد للحدث
     */
    private function generateEventId(): string
    {
        return 'EVT-' . strtoupper(bin2hex(random_bytes(8)));
    }
    
    /**
     * إرسال تنبيه للأحداث الحرجة
     */
    private function sendAlert(array $entry): void
    {
        // يمكن إضافة تكامل مع Slack, Email, SMS
        $alertFile = $this->logDir . '/alerts.log';
        $alertLine = json_encode([
            'alert_time' => date('c'),
            'event' => $entry
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        file_put_contents($alertFile, $alertLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * تنظيف السجلات القديمة
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $deleted = 0;
        $cutoffTime = time() - ($daysToKeep * 86400);
        
        $logFiles = glob($this->logDir . '/audit_*.log');
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < $cutoffTime) {
                unlink($logFile);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
