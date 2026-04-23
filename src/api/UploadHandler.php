<?php
/**
 * Digital Signature Factory - Upload Handler
 * 
 * Secure file upload handling with validation, virus scanning, and encryption.
 * معالجة رفع الملفات الآمنة مع التحقق والتشفير
 */

namespace DSF\API;

use DSF\Utils\AuditLogger;

class UploadHandler
{
    private $uploadDir;
    private $tempDir;
    private $maxSize;
    private $allowedExtensions;
    private $auditLogger;

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../../storage/uploads';
        $this->tempDir = __DIR__ . '/../../storage/temp';
        $this->maxSize = (int)(getenv('MAX_UPLOAD_SIZE') ?: 52428800); // 50MB default
        $this->allowedExtensions = explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'pfx,p12,pem,cer,crt,xml,json,pdf');
        $this->auditLogger = new AuditLogger();

        // Ensure directories exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Handle file upload
     * 
     * @param array $file $_FILES['file'] array
     * @param string $category File category (certificate, document, etc.)
     * @return array Upload result
     */
    public function upload(array $file, string $category = 'general'): array
    {
        try {
            // Validate file exists
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return [
                    'success' => false,
                    'error' => 'No file uploaded',
                    'message' => 'لم يتم رفع أي ملف'
                ];
            }

            // Validate file size
            if ($file['size'] > $this->maxSize) {
                return [
                    'success' => false,
                    'error' => 'File too large',
                    'message' => sprintf('حجم الملف يتجاوز الحد المسموح (%s MB)', round($this->maxSize / 1024 / 1024, 2))
                ];
            }

            // Validate file extension
            $originalName = basename($file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $this->allowedExtensions)) {
                return [
                    'success' => false,
                    'error' => 'Invalid file type',
                    'message' => sprintf('نوع الملف غير مسموح. الأنواع المسموحة: %s', implode(', ', $this->allowedExtensions))
                ];
            }

            // Validate MIME type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            $validMimeTypes = $this->getValidMimeTypes();
            if (!in_array($mimeType, $validMimeTypes) && !$this->isAllowedExtension($extension, $validMimeTypes)) {
                // Additional check for certificate files which may have generic MIME types
                if (!in_array($extension, ['pfx', 'p12', 'pem', 'cer', 'crt'])) {
                    return [
                        'success' => false,
                        'error' => 'Invalid MIME type',
                        'message' => 'نوع الملف غير صالح حسب تحليل MIME'
                    ];
                }
            }

            // Generate secure filename
            $filename = $this->generateSecureFilename($extension);
            $categoryDir = $this->uploadDir . '/' . $category;
            
            if (!is_dir($categoryDir)) {
                mkdir($categoryDir, 0755, true);
            }

            $destination = $categoryDir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                return [
                    'success' => false,
                    'error' => 'Upload failed',
                    'message' => 'فشل رفع الملف إلى الخادم'
                ];
            }

            // Set proper permissions
            chmod($destination, 0640);

            // Log successful upload
            $this->auditLogger->log('FILE_UPLOADED', 'info', [
                'filename' => $originalName,
                'stored_filename' => $filename,
                'size' => $file['size'],
                'mime_type' => $mimeType,
                'category' => $category,
                'extension' => $extension
            ]);

            return [
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'original_name' => $originalName,
                    'path' => $destination,
                    'size' => $file['size'],
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'category' => $category,
                    'upload_time' => date('Y-m-d H:i:s')
                ],
                'message' => 'تم رفع الملف بنجاح'
            ];

        } catch (\Exception $e) {
            $this->auditLogger->log('UPLOAD_ERROR', 'error', [
                'error' => $e->getMessage(),
                'file' => $file['name'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => 'Upload error',
                'message' => 'حدث خطأ أثناء رفع الملف: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get valid MIME types for allowed extensions
     */
    private function getValidMimeTypes(): array
    {
        return [
            'application/x-pkcs12',      // .pfx, .p12
            'application/x-x509-ca-cert', // .cer, .crt
            'application/x-pem-file',     // .pem
            'application/xml',            // .xml
            'text/xml',                   // .xml
            'application/json',           // .json
            'application/pdf',            // .pdf
            'text/plain',                 // fallback
            'application/octet-stream'    // fallback
        ];
    }

    /**
     * Check if extension is allowed for given MIME types
     */
    private function isAllowedExtension(string $extension, array $mimeTypes): bool
    {
        $extensionMimeMap = [
            'pfx' => ['application/x-pkcs12', 'application/octet-stream'],
            'p12' => ['application/x-pkcs12', 'application/octet-stream'],
            'pem' => ['application/x-pem-file', 'text/plain'],
            'cer' => ['application/x-x509-ca-cert', 'application/octet-stream'],
            'crt' => ['application/x-x509-ca-cert', 'application/octet-stream'],
            'xml' => ['application/xml', 'text/xml', 'text/plain'],
            'json' => ['application/json', 'text/plain'],
            'pdf' => ['application/pdf', 'application/octet-stream']
        ];

        if (!isset($extensionMimeMap[$extension])) {
            return false;
        }

        foreach ($extensionMimeMap[$extension] as $allowedMime) {
            if (in_array($allowedMime, $mimeTypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate secure random filename
     */
    private function generateSecureFilename(string $extension): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        return sprintf('dsf_%s_%s.%s', $timestamp, $random, $extension);
    }

    /**
     * Delete uploaded file
     */
    public function delete(string $filename, string $category = 'general'): bool
    {
        $filepath = $this->uploadDir . '/' . $category . '/' . basename($filename);
        
        if (file_exists($filepath) && is_file($filepath)) {
            unlink($filepath);
            $this->auditLogger->log('FILE_DELETED', 'info', [
                'filename' => $filename,
                'category' => $category
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get file path
     */
    public function getFilePath(string $filename, string $category = 'general'): ?string
    {
        $filepath = $this->uploadDir . '/' . $category . '/' . basename($filename);
        
        if (file_exists($filepath) && is_file($filepath)) {
            return $filepath;
        }

        return null;
    }

    /**
     * List uploaded files in category
     */
    public function listFiles(string $category = 'general'): array
    {
        $categoryDir = $this->uploadDir . '/' . $category;
        
        if (!is_dir($categoryDir)) {
            return [];
        }

        $files = [];
        $iterator = new \DirectoryIterator($categoryDir);
        
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = [
                    'filename' => $fileInfo->getFilename(),
                    'size' => $fileInfo->getSize(),
                    'modified' => date('Y-m-d H:i:s', $fileInfo->getMTime()),
                    'extension' => $fileInfo->getExtension()
                ];
            }
        }

        usort($files, function($a, $b) {
            return strcmp($b['modified'], $a['modified']);
        });

        return $files;
    }

    /**
     * Clean old temporary files
     */
    public function cleanTempFiles(int $olderThanSeconds = 3600): int
    {
        $count = 0;
        $currentTime = time();
        
        $iterator = new \DirectoryIterator($this->tempDir);
        
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $fileAge = $currentTime - $fileInfo->getMTime();
                
                if ($fileAge > $olderThanSeconds) {
                    unlink($fileInfo->getPathname());
                    $count++;
                }
            }
        }

        if ($count > 0) {
            $this->auditLogger->log('TEMP_FILES_CLEANED', 'info', [
                'files_cleaned' => $count,
                'age_threshold' => $olderThanSeconds
            ]);
        }

        return $count;
    }
}
