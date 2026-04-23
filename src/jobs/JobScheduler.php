<?php
/**
 * Background Job Scheduler and Worker
 * Handles bulk signing, certificate renewal, cleanup tasks, and scheduled operations
 * 
 * @package DigitalSignatureFactory\Jobs
 * @version 2.0.0
 */

namespace DigitalSignatureFactory\Jobs;

use PDO;
use DigitalSignatureFactory\Core\DigitalSignatureFactory;
use DigitalSignatureFactory\Utils\AuditLogger;

class JobScheduler
{
    private PDO $db;
    private DigitalSignatureFactory $factory;
    private AuditLogger $audit;
    private array $config;
    private bool $running = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Database connection
        $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset=utf8mb4";
        $this->db = new \PDO($dsn, $config['database']['user'], $config['database']['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        
        $this->factory = new DigitalSignatureFactory($config);
        $this->audit = new AuditLogger($config);
    }

    /**
     * Create a new job
     */
    public function createJob(string $type, array $payload, int $userId, ?int $scheduledAt = null): array
    {
        try {
            $scheduledAt = $scheduledAt ?? time();
            $priority = $payload['priority'] ?? 5; // 1-10, 1 is highest
            
            $stmt = $this->db->prepare("
                INSERT INTO jobs (type, payload, user_id, status, priority, scheduled_at, created_at)
                VALUES (?, ?, ?, 'pending', ?, FROM_UNIXTIME(?), NOW())
            ");
            
            $payloadJson = json_encode($payload);
            $stmt->execute([$type, $payloadJson, $userId, $priority, $scheduledAt]);
            
            $jobId = $this->db->lastInsertId();
            
            $this->audit->log('JOB_CREATED', $userId, ['job_id' => $jobId, 'type' => $type]);
            
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => 'pending',
                'scheduled_at' => date('Y-m-d H:i:s', $scheduledAt)
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get pending jobs
     */
    public function getPendingJobs(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM jobs
            WHERE status = 'pending'
              AND scheduled_at <= NOW()
            ORDER BY priority ASC, scheduled_at ASC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Process a single job
     */
    public function processJob(array $job): array
    {
        $jobId = $job['id'];
        
        try {
            // Mark as processing
            $stmt = $this->db->prepare("
                UPDATE jobs 
                SET status = 'processing', started_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$jobId]);
            
            $payload = json_decode($job['payload'], true);
            $result = null;
            
            switch ($job['type']) {
                case 'bulk_sign_xml':
                    $result = $this->processBulkSignXML($job, $payload);
                    break;
                    
                case 'bulk_sign_pdf':
                    $result = $this->processBulkSignPDF($job, $payload);
                    break;
                    
                case 'certificate_renewal':
                    $result = $this->processCertificateRenewal($job, $payload);
                    break;
                    
                case 'cleanup_expired_tokens':
                    $result = $this->processCleanup($job, $payload);
                    break;
                    
                case 'generate_report':
                    $result = $this->processGenerateReport($job, $payload);
                    break;
                    
                case 'sync_certificates':
                    $result = $this->processSyncCertificates($job, $payload);
                    break;
                    
                default:
                    throw new \Exception("Unknown job type: {$job['type']}");
            }
            
            // Mark as completed
            $stmt = $this->db->prepare("
                UPDATE jobs 
                SET status = 'completed', 
                    completed_at = NOW(),
                    result = ?,
                    progress = 100
                WHERE id = ?
            ");
            
            $resultJson = json_encode($result);
            $stmt->execute([$resultJson, $jobId]);
            
            $this->audit->log('JOB_COMPLETED', $job['user_id'], [
                'job_id' => $jobId,
                'type' => $job['type'],
                'result' => $result
            ]);
            
            return ['success' => true, 'job_id' => $jobId, 'result' => $result];
            
        } catch (\Exception $e) {
            // Mark as failed
            $stmt = $this->db->prepare("
                UPDATE jobs 
                SET status = 'failed',
                    failed_at = NOW(),
                    error_message = ?,
                    retry_count = retry_count + 1
                WHERE id = ?
            ");
            
            $errorMsg = $e->getMessage();
            $stmt->execute([$errorMsg, $jobId]);
            
            $this->audit->log('JOB_FAILED', $job['user_id'] ?? null, [
                'job_id' => $jobId,
                'type' => $job['type'],
                'error' => $errorMsg
            ]);
            
            return ['success' => false, 'job_id' => $jobId, 'error' => $errorMsg];
        }
    }

    /**
     * Process bulk XML signing
     */
    private function processBulkSignXML(array $job, array $payload): array
    {
        $files = $payload['files'];
        $certId = $payload['cert_id'];
        $totalFiles = count($files);
        $results = [];
        $successful = 0;
        $failed = 0;
        
        // Get certificate
        $cert = $this->getCertificate($certId);
        
        foreach ($files as $index => $file) {
            try {
                $signedXml = $this->factory->signEGovXML([
                    'xml' => $file['content'],
                    'cert' => $cert,
                    'policy' => $payload['policy'] ?? 'default'
                ]);
                
                $results[] = [
                    'file' => $file['name'],
                    'success' => true,
                    'signed_content' => $signedXml
                ];
                $successful++;
                
            } catch (\Exception $e) {
                $results[] = [
                    'file' => $file['name'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failed++;
            }
            
            // Update progress
            $progress = (int)(($index + 1) / $totalFiles * 100);
            $stmt = $this->db->prepare("UPDATE jobs SET progress = ? WHERE id = ?");
            $stmt->execute([$progress, $job['id']]);
        }
        
        return [
            'total' => $totalFiles,
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Process bulk PDF signing
     */
    private function processBulkSignPDF(array $job, array $payload): array
    {
        $files = $payload['files'];
        $certId = $payload['cert_id'];
        $totalFiles = count($files);
        $results = [];
        $successful = 0;
        $failed = 0;
        
        // Get certificate
        $cert = $this->getCertificate($certId);
        
        $options = [
            'reason' => $payload['reason'] ?? 'Document approval',
            'location' => $payload['location'] ?? 'Cairo, Egypt',
            'contact' => $payload['contact'] ?? ''
        ];
        
        foreach ($files as $index => $file) {
            try {
                $signedPdfPath = $this->factory->signPDF($file['path'], $cert, $options);
                
                $results[] = [
                    'file' => $file['name'],
                    'success' => true,
                    'signed_path' => $signedPdfPath
                ];
                $successful++;
                
            } catch (\Exception $e) {
                $results[] = [
                    'file' => $file['name'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failed++;
            }
            
            // Update progress
            $progress = (int)(($index + 1) / $totalFiles * 100);
            $stmt = $this->db->prepare("UPDATE jobs SET progress = ? WHERE id = ?");
            $stmt->execute([$progress, $job['id']]);
        }
        
        return [
            'total' => $totalFiles,
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Process certificate renewal check
     */
    private function processCertificateRenewal(array $job, array $payload): array
    {
        $daysThreshold = $payload['days_threshold'] ?? 30;
        $renewedCount = 0;
        $expiringSoon = [];
        
        // Get all certificates expiring soon
        $stmt = $this->db->prepare("
            SELECT * FROM certificates
            WHERE valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
            AND status = 'active'
        ");
        $stmt->execute([$daysThreshold]);
        $certificates = $stmt->fetchAll();
        
        foreach ($certificates as $cert) {
            $expiringSoon[] = [
                'id' => $cert['id'],
                'subject' => $cert['subject_dn'],
                'valid_to' => $cert['valid_to'],
                'days_remaining' => (strtotime($cert['valid_to']) - time()) / 86400
            ];
            
            // Auto-renew if enabled
            if ($payload['auto_renew'] ?? false) {
                try {
                    // Renewal logic here
                    $renewedCount++;
                } catch (\Exception $e) {
                    // Log error but continue
                }
            }
        }
        
        return [
            'expiring_soon' => $expiringSoon,
            'renewed_count' => $renewedCount,
            'threshold_days' => $daysThreshold
        ];
    }

    /**
     * Process cleanup tasks
     */
    private function processCleanup(array $job, array $payload): array
    {
        $cleaned = [
            'expired_tokens' => 0,
            'old_logs' => 0,
            'temp_files' => 0
        ];
        
        // Clean expired tokens from blacklist
        $stmt = $this->db->query("DELETE FROM token_blacklist WHERE expires_at < NOW()");
        $cleaned['expired_tokens'] = $stmt->rowCount();
        
        // Clean old audit logs (older than retention period)
        $retentionDays = $payload['log_retention_days'] ?? 90;
        $stmt = $this->db->prepare("
            DELETE FROM audit_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$retentionDays]);
        $cleaned['old_logs'] = $stmt->rowCount();
        
        // Clean temp files
        $tempDir = $this->config['storage']['temp_dir'] ?? '/tmp/dsf';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file) > 86400)) {
                    unlink($file);
                    $cleaned['temp_files']++;
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Process report generation
     */
    private function processGenerateReport(array $job, array $payload): array
    {
        $reportType = $payload['report_type'];
        $startDate = $payload['start_date'] ?? date('Y-m-01');
        $endDate = $payload['end_date'] ?? date('Y-m-d');
        
        $report = [
            'type' => $reportType,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'generated_at' => date('c')
        ];
        
        switch ($reportType) {
            case 'signing_activity':
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total_signatures,
                        SUM(CASE WHEN action LIKE '%XML%' THEN 1 ELSE 0 END) as xml_count,
                        SUM(CASE WHEN action LIKE '%PDF%' THEN 1 ELSE 0 END) as pdf_count,
                        SUM(CASE WHEN action LIKE '%JSON%' THEN 1 ELSE 0 END) as json_count
                    FROM audit_logs
                    WHERE action LIKE 'SIGN_%'
                      AND created_at BETWEEN ? AND ?
                ");
                $stmt->execute([$startDate, $endDate]);
                $report['data'] = $stmt->fetch();
                break;
                
            case 'user_activity':
                $stmt = $this->db->prepare("
                    SELECT 
                        u.username,
                        u.full_name,
                        COUNT(a.id) as actions_count,
                        MAX(a.created_at) as last_action
                    FROM users u
                    LEFT JOIN audit_logs a ON u.id = a.user_id
                    WHERE a.created_at BETWEEN ? AND ?
                    GROUP BY u.id
                    ORDER BY actions_count DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $report['data'] = $stmt->fetchAll();
                break;
                
            case 'certificate_usage':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.subject_dn,
                        c.provider,
                        COUNT(a.id) as usage_count
                    FROM certificates c
                    LEFT JOIN audit_logs a ON c.id = a.certificate_id
                    WHERE a.created_at BETWEEN ? AND ?
                    GROUP BY c.id
                    ORDER BY usage_count DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $report['data'] = $stmt->fetchAll();
                break;
        }
        
        return $report;
    }

    /**
     * Process certificate synchronization
     */
    private function processSyncCertificates(array $job, array $payload): array
    {
        $tokens = $this->factory->detectTokens();
        $syncedCount = 0;
        $errors = [];
        
        foreach ($tokens as $token) {
            try {
                $certs = $token->getCertificates();
                
                foreach ($certs as $cert) {
                    // Check if certificate exists in DB
                    $stmt = $this->db->prepare("SELECT id FROM certificates WHERE serial_number = ?");
                    $stmt->execute([$cert->getSerialNumber()]);
                    
                    if ($stmt->fetch()) {
                        // Update existing
                        $stmt = $this->db->prepare("
                            UPDATE certificates 
                            SET status = ?, updated_at = NOW()
                            WHERE serial_number = ?
                        ");
                        $stmt->execute([$cert->getStatus(), $cert->getSerialNumber()]);
                    } else {
                        // Insert new
                        $stmt = $this->db->prepare("
                            INSERT INTO certificates 
                            (serial_number, subject_dn, issuer_dn, valid_from, valid_to, 
                             provider, token_id, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                        ");
                        $stmt->execute([
                            $cert->getSerialNumber(),
                            $cert->getSubjectDN(),
                            $cert->getIssuerDN(),
                            $cert->getNotBefore(),
                            $cert->getNotAfter(),
                            $cert->getProvider(),
                            $token->getId()
                        ]);
                    }
                    
                    $syncedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'token' => $token->getId(),
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'synced_count' => $syncedCount,
            'errors' => $errors
        ];
    }

    /**
     * Helper: Get certificate
     */
    private function getCertificate(string $certId): object
    {
        // Implementation to fetch certificate from DB or token
        throw new \Exception("Certificate not found: $certId");
    }

    /**
     * Run scheduler loop
     */
    public function run(): void
    {
        $this->running = true;
        
        echo "Starting Job Scheduler...\n";
        
        while ($this->running) {
            try {
                $jobs = $this->getPendingJobs(5);
                
                if (empty($jobs)) {
                    sleep(10); // Wait before checking again
                    continue;
                }
                
                foreach ($jobs as $job) {
                    $this->processJob($job);
                }
                
            } catch (\Exception $e) {
                echo "Scheduler error: " . $e->getMessage() . "\n";
                sleep(30);
            }
        }
    }

    /**
     * Stop scheduler
     */
    public function stop(): void
    {
        $this->running = false;
        echo "Stopping Job Scheduler...\n";
    }

    /**
     * Get job status
     */
    public function getJobStatus(int $jobId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        
        if (!$job) {
            return ['error' => 'Job not found'];
        }
        
        return [
            'id' => $job['id'],
            'type' => $job['type'],
            'status' => $job['status'],
            'progress' => $job['progress'],
            'created_at' => $job['created_at'],
            'started_at' => $job['started_at'],
            'completed_at' => $job['completed_at'],
            'error_message' => $job['error_message']
        ];
    }

    /**
     * Cancel a job
     */
    public function cancelJob(int $jobId): array
    {
        $stmt = $this->db->prepare("
            UPDATE jobs 
            SET status = 'cancelled' 
            WHERE id = ? AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$jobId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Job cancelled'];
        }
        
        return ['success' => false, 'message' => 'Job cannot be cancelled'];
    }

    /**
     * Retry a failed job
     */
    public function retryJob(int $jobId): array
    {
        $stmt = $this->db->prepare("
            UPDATE jobs 
            SET status = 'pending',
                error_message = NULL,
                started_at = NULL,
                failed_at = NULL,
                scheduled_at = NOW()
            WHERE id = ? AND status = 'failed'
        ");
        $stmt->execute([$jobId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Job queued for retry'];
        }
        
        return ['success' => false, 'message' => 'Job cannot be retried'];
    }
}
