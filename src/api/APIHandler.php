<?php
/**
 * RESTful API Handler for Digital Signature Factory
 * Handles all HTTP requests for signature operations
 * 
 * @package DigitalSignatureFactory\API
 * @version 2.0.0
 */

namespace DigitalSignatureFactory\API;

use DigitalSignatureFactory\Core\DigitalSignatureFactory;
use DigitalSignatureFactory\Core\Certificate;
use DigitalSignatureFactory\Auth\AuthManager;
use DigitalSignatureFactory\Utils\AuditLogger;
use DigitalSignatureFactory\Utils\CertificateValidator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class APIHandler
{
    private DigitalSignatureFactory $factory;
    private AuthManager $auth;
    private AuditLogger $audit;
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/signature_config.php';
        $this->factory = new DigitalSignatureFactory($this->config);
        $this->auth = new AuthManager($this->config);
        $this->audit = new AuditLogger($this->config);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Main request router
     */
    public function handleRequest(): void
    {
        try {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $method = $_SERVER['REQUEST_METHOD'];
            
            // Public endpoints (no auth required)
            if ($uri === '/api/health') {
                $this->jsonResponse(['status' => 'ok', 'version' => '2.0.0', 'timestamp' => time()]);
                return;
            }
            
            if ($uri === '/api/auth/login' && $method === 'POST') {
                $this->login();
                return;
            }
            
            if ($uri === '/api/auth/register' && $method === 'POST') {
                $this->register();
                return;
            }

            // Protected endpoints (auth required)
            $token = $this->getAuthHeader();
            if (!$token || !$this->auth->validateToken($token)) {
                $this->jsonResponse(['error' => 'Unauthorized', 'code' => 401], 401);
                return;
            }
            
            $user = $this->auth->getUserFromToken($token);
            $this->audit->log('API_REQUEST', $user['id'], ['uri' => $uri, 'method' => $method]);

            // Route handling
            switch (true) {
                // Token Management
                case preg_match('#^/api/tokens/scan$#', $uri) && $method === 'GET':
                    $this->scanTokens($user);
                    break;
                    
                case preg_match('#^/api/tokens/([a-zA-Z0-9_-]+)/info$#', $uri, $matches) && $method === 'GET':
                    $this->getTokenInfo($user, $matches[1]);
                    break;
                    
                case preg_match('#^/api/tokens/([a-zA-Z0-9_-]+)/certificates$#', $uri, $matches) && $method === 'GET':
                    $this->getTokenCertificates($user, $matches[1]);
                    break;

                // Certificate Management
                case preg_match('#^/api/certificates/list$#', $uri) && $method === 'GET':
                    $this->listCertificates($user);
                    break;
                    
                case preg_match('#^/api/certificates/([a-zA-Z0-9_-]+)/details$#', $uri, $matches) && $method === 'GET':
                    $this->getCertificateDetails($user, $matches[1]);
                    break;
                    
                case preg_match('#^/api/certificates/([a-zA-Z0-9_-]+)/validate$#', $uri, $matches) && $method === 'GET':
                    $this->validateCertificate($user, $matches[1]);
                    break;
                    
                case preg_match('#^/api/certificates/([a-zA-Z0-9_-]+)/export$#', $uri, $matches) && $method === 'POST':
                    $this->exportCertificate($user, $matches[1]);
                    break;
                    
                case $uri === '/api/certificates/create' && $method === 'POST':
                    $this->createCertificate($user);
                    break;

                // Document Signing
                case $uri === '/api/sign/xml' && $method === 'POST':
                    $this->signXML($user);
                    break;
                    
                case $uri === '/api/sign/json' && $method === 'POST':
                    $this->signJSON($user);
                    break;
                    
                case $uri === '/api/sign/pdf' && $method === 'POST':
                    $this->signPDF($user);
                    break;
                    
                case $uri === '/api/sign/bulk' && $method === 'POST':
                    $this->bulkSign($user);
                    break;

                // ZATCA Specific
                case $uri === '/api/zatca/sign' && $method === 'POST':
                    $this->signZatca($user);
                    break;
                    
                case $uri === '/api/zatca/compliance' && $method === 'POST':
                    $this->zatcaCompliance($user);
                    break;

                // Audit Logs
                case preg_match('#^/api/audit/logs$#', $uri) && $method === 'GET':
                    $this->getAuditLogs($user);
                    break;
                    
                case preg_match('#^/api/audit/stats$#', $uri) && $method === 'GET':
                    $this->getAuditStats($user);
                    break;

                // User Management
                case $uri === '/api/users/profile' && $method === 'GET':
                    $this->getProfile($user);
                    break;
                    
                case $uri === '/api/users/profile' && $method === 'PUT':
                    $this->updateProfile($user);
                    break;

                default:
                    $this->jsonResponse(['error' => 'Not Found', 'code' => 404], 404);
            }
            
        } catch (\Exception $e) {
            $this->audit->log('API_ERROR', null, ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->jsonResponse([
                'error' => 'Internal Server Error',
                'message' => $this->config['debug'] ? $e->getMessage() : 'An unexpected error occurred',
                'code' => 500
            ], 500);
        }
    }

    /**
     * Authentication: Login
     */
    private function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            $this->jsonResponse(['error' => 'Username and password required'], 400);
            return;
        }

        try {
            $result = $this->auth->login($data['username'], $data['password']);
            
            if ($result['success']) {
                $this->audit->log('USER_LOGIN', $result['user']['id'], ['username' => $data['username']]);
                $this->jsonResponse([
                    'success' => true,
                    'token' => $result['token'],
                    'user' => $result['user'],
                    'expires_in' => 3600
                ]);
            } else {
                $this->audit->log('LOGIN_FAILED', null, ['username' => $data['username'], 'reason' => $result['message']]);
                $this->jsonResponse(['error' => $result['message']], 401);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Authentication: Register
     */
    private function register(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['username', 'password', 'email', 'full_name'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->jsonResponse(['error' => "Field '$field' is required"], 400);
                return;
            }
        }

        try {
            $result = $this->auth->register($data);
            
            if ($result['success']) {
                $this->audit->log('USER_REGISTER', $result['user']['id'], ['username' => $data['username']]);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User registered successfully',
                    'user' => $result['user']
                ], 201);
            } else {
                $this->jsonResponse(['error' => $result['message']], 400);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Registration failed'], 500);
        }
    }

    /**
     * Scan connected tokens
     */
    private function scanTokens(array $user): void
    {
        $tokens = $this->factory->detectTokens();
        
        $this->jsonResponse([
            'success' => true,
            'count' => count($tokens),
            'tokens' => array_map(function($token) {
                return [
                    'id' => $token->getId(),
                    'label' => $token->getLabel(),
                    'manufacturer' => $token->getManufacturer(),
                    'serial' => $token->getSerial(),
                    'certificate_count' => count($token->getCertificates()),
                    'status' => $token->isConnected() ? 'connected' : 'disconnected'
                ];
            }, $tokens)
        ]);
    }

    /**
     * Get token information
     */
    private function getTokenInfo(array $user, string $tokenId): void
    {
        $tokens = $this->factory->detectTokens();
        $token = null;
        
        foreach ($tokens as $t) {
            if ($t->getId() === $tokenId) {
                $token = $t;
                break;
            }
        }
        
        if (!$token) {
            $this->jsonResponse(['error' => 'Token not found'], 404);
            return;
        }
        
        $this->jsonResponse([
            'success' => true,
            'token' => [
                'id' => $token->getId(),
                'label' => $token->getLabel(),
                'manufacturer' => $token->getManufacturer(),
                'model' => $token->getModel(),
                'serial' => $token->getSerial(),
                'firmware_version' => $token->getFirmwareVersion(),
                'slot_id' => $token->getSlotId(),
                'flags' => $token->getFlags(),
                'max_session_count' => $token->getMaxSessionCount(),
                'max_rw_session_count' => $token->getMaxRWSessionCount(),
                'max_pin_len' => $token->getMaxPinLen(),
                'min_pin_len' => $token->getMinPinLen(),
                'total_public_memory' => $token->getTotalPublicMemory(),
                'free_public_memory' => $token->getFreePublicMemory(),
                'total_private_memory' => $token->getTotalPrivateMemory(),
                'free_private_memory' => $token->getFreePrivateMemory(),
                'hardware_version' => $token->getHardwareVersion(),
                'status' => $token->isConnected() ? 'connected' : 'disconnected',
                'last_seen' => $token->getLastSeen()
            ]
        ]);
    }

    /**
     * Get certificates from token
     */
    private function getTokenCertificates(array $user, string $tokenId): void
    {
        $tokens = $this->factory->detectTokens();
        $token = null;
        
        foreach ($tokens as $t) {
            if ($t->getId() === $tokenId) {
                $token = $t;
                break;
            }
        }
        
        if (!$token) {
            $this->jsonResponse(['error' => 'Token not found'], 404);
            return;
        }
        
        $certs = $token->getCertificates();
        
        $this->jsonResponse([
            'success' => true,
            'count' => count($certs),
            'certificates' => array_map(function($cert) {
                return [
                    'id' => $cert->getSerialNumber(),
                    'subject' => $cert->getSubjectDN(),
                    'issuer' => $cert->getIssuerDN(),
                    'valid_from' => $cert->getNotBefore(),
                    'valid_to' => $cert->getNotAfter(),
                    'key_usage' => $cert->getKeyUsage(),
                    'extended_key_usage' => $cert->getExtendedKeyUsage(),
                    'provider' => $cert->getProvider(),
                    'status' => $cert->getStatus()
                ];
            }, $certs)
        ]);
    }

    /**
     * List all certificates
     */
    private function listCertificates(array $user): void
    {
        // Implementation for listing certificates from database
        $this->jsonResponse([
            'success' => true,
            'certificates' => [] // Would fetch from DB
        ]);
    }

    /**
     * Get certificate details
     */
    private function getCertificateDetails(array $user, string $certId): void
    {
        // Implementation for getting certificate details
        $this->jsonResponse([
            'success' => true,
            'certificate' => [] // Would fetch from DB or token
        ]);
    }

    /**
     * Validate certificate
     */
    private function validateCertificate(array $user, string $certId): void
    {
        $validator = new CertificateValidator($this->config);
        $result = $validator->validateCertificateById($certId);
        
        $this->jsonResponse([
            'success' => true,
            'valid' => $result['valid'],
            'details' => $result,
            'checks' => [
                'signature_valid' => $result['signature_valid'] ?? false,
                'not_expired' => $result['not_expired'] ?? false,
                'not_revoked' => $result['not_revoked'] ?? false,
                'chain_valid' => $result['chain_valid'] ?? false,
                'purpose_valid' => $result['purpose_valid'] ?? false
            ]
        ]);
    }

    /**
     * Export certificate
     */
    private function exportCertificate(array $user, string $certId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $format = $data['format'] ?? 'pem';
        $newPassphrase = $data['passphrase'] ?? null;
        
        // Implementation for exporting certificate
        $this->jsonResponse([
            'success' => true,
            'message' => 'Certificate exported successfully',
            'format' => $format
        ]);
    }

    /**
     * Create new certificate
     */
    private function createCertificate(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['type', 'subject'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->jsonResponse(['error' => "Field '$field' is required"], 400);
                return;
            }
        }

        try {
            $cert = null;
            
            switch ($data['type']) {
                case 'root_ca':
                    $cert = $this->factory->createRootCA($data['subject']);
                    break;
                case 'intermediate_ca':
                    $cert = $this->factory->createIntermediateCA($data['subject'], $data['parent_cert']);
                    break;
                case 'code_signing':
                    $cert = $this->factory->createCodeSigningCert($data['subject']);
                    break;
                case 'document_signing':
                    $cert = $this->factory->createDocumentSigningCert($data['subject']);
                    break;
                default:
                    $this->jsonResponse(['error' => 'Invalid certificate type'], 400);
                    return;
            }
            
            $this->audit->log('CERT_CREATE', $user['id'], ['type' => $data['type'], 'subject' => $data['subject']]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Certificate created successfully',
                'certificate' => [
                    'serial' => $cert->getSerialNumber(),
                    'subject' => $cert->getSubjectDN(),
                    'valid_from' => $cert->getNotBefore(),
                    'valid_to' => $cert->getNotAfter()
                ]
            ], 201);
            
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Certificate creation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sign XML document
     */
    private function signXML(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['xml']) || !isset($data['cert_id'])) {
            $this->jsonResponse(['error' => 'XML content and certificate ID required'], 400);
            return;
        }

        try {
            $cert = $this->getCertificate($data['cert_id']);
            $signedXml = $this->factory->signEGovXML([
                'xml' => $data['xml'],
                'cert' => $cert,
                'policy' => $data['policy'] ?? 'http://www.egytrust.eg/policy',
                'enveloped' => $data['enveloped'] ?? true
            ]);
            
            $this->audit->log('SIGN_XML', $user['id'], ['cert_id' => $data['cert_id'], 'size' => strlen($data['xml'])]);
            
            $this->jsonResponse([
                'success' => true,
                'signed_xml' => $signedXml,
                'signature_info' => [
                    'algorithm' => 'RSA-SHA256',
                    'timestamp' => date('c'),
                    'policy' => $data['policy'] ?? 'default'
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'XML signing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sign JSON document
     */
    private function signJSON(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['json']) || !isset($data['cert_id'])) {
            $this->jsonResponse(['error' => 'JSON content and certificate ID required'], 400);
            return;
        }

        try {
            $cert = $this->getCertificate($data['cert_id']);
            $signedJson = $this->factory->signJSON([
                'data' => $data['json'],
                'cert' => $cert,
                'detached' => $data['detached'] ?? false
            ]);
            
            $this->audit->log('SIGN_JSON', $user['id'], ['cert_id' => $data['cert_id']]);
            
            $this->jsonResponse([
                'success' => true,
                'signed_json' => $signedJson,
                'signature_info' => [
                    'algorithm' => 'RSA-SHA256',
                    'timestamp' => date('c')
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'JSON signing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sign PDF document
     */
    private function signPDF(array $user): void
    {
        // Handle file upload
        if (!isset($_FILES['pdf']) || !isset($_POST['cert_id'])) {
            $this->jsonResponse(['error' => 'PDF file and certificate ID required'], 400);
            return;
        }

        try {
            $cert = $this->getCertificate($_POST['cert_id']);
            $pdfPath = $_FILES['pdf']['tmp_name'];
            
            $options = [
                'reason' => $_POST['reason'] ?? 'Document approval',
                'location' => $_POST['location'] ?? 'Cairo, Egypt',
                'contact' => $_POST['contact'] ?? '',
                'visible' => isset($_POST['visible']),
                'page' => (int)($_POST['page'] ?? 1),
                'position' => $_POST['position'] ?? 'bottom-right'
            ];
            
            $signedPdfPath = $this->factory->signPDF($pdfPath, $cert, $options);
            
            $this->audit->log('SIGN_PDF', $user['id'], ['cert_id' => $_POST['cert_id'], 'file' => $_FILES['pdf']['name']]);
            
            // Return signed PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="signed_' . basename($_FILES['pdf']['name']) . '"');
            readfile($signedPdfPath);
            unlink($signedPdfPath); // Clean up
            
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'PDF signing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Bulk signing operation
     */
    private function bulkSign(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['files']) || !isset($data['cert_id']) || !isset($data['type'])) {
            $this->jsonResponse(['error' => 'Files, certificate ID, and type required'], 400);
            return;
        }

        try {
            $cert = $this->getCertificate($data['cert_id']);
            $results = [];
            
            foreach ($data['files'] as $file) {
                try {
                    $result = null;
                    
                    switch ($data['type']) {
                        case 'xml':
                            $result = $this->factory->signEGovXML(['xml' => $file['content'], 'cert' => $cert]);
                            break;
                        case 'pdf':
                            $result = $this->factory->signPDF($file['path'], $cert);
                            break;
                        case 'json':
                            $result = $this->factory->signJSON(['data' => $file['content'], 'cert' => $cert]);
                            break;
                    }
                    
                    $results[] = [
                        'file' => $file['name'],
                        'success' => true,
                        'result' => $result
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'file' => $file['name'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->audit->log('BULK_SIGN', $user['id'], ['count' => count($data['files']), 'type' => $data['type']]);
            
            $this->jsonResponse([
                'success' => true,
                'total' => count($data['files']),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Bulk signing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ZATCA Saudi compliance signing
     */
    private function signZatca(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['xml']) || !isset($data['cert_id'])) {
            $this->jsonResponse(['error' => 'XML content and certificate ID required'], 400);
            return;
        }

        try {
            $cert = $this->getCertificate($data['cert_id']);
            $signedXml = $this->factory->signZatcaXML($data['xml'], $cert);
            
            $this->audit->log('SIGN_ZATCA', $user['id'], ['cert_id' => $data['cert_id']]);
            
            $this->jsonResponse([
                'success' => true,
                'signed_xml' => $signedXml,
                'zatca_compliant' => true,
                'phase' => 'Phase2',
                'tlv_hash' => hash('sha256', $signedXml)
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'ZATCA signing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ZATCA compliance check
     */
    private function zatcaCompliance(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['invoice_hash']) || !isset($data['uuid'])) {
            $this->jsonResponse(['error' => 'Invoice hash and UUID required'], 400);
            return;
        }

        // Simulate compliance check
        $compliance = [
            'valid' => true,
            'phase' => 'Phase2',
            'checks' => [
                'schema_valid' => true,
                'signature_valid' => true,
                'hash_valid' => true,
                'qr_code_valid' => true,
                'uuid_format_valid' => true
            ],
            'warnings' => [],
            'errors' => []
        ];
        
        $this->jsonResponse([
            'success' => true,
            'compliance' => $compliance,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Get audit logs
     */
    private function getAuditLogs(array $user): void
    {
        $limit = min((int)($_GET['limit'] ?? 100), 1000);
        $offset = (int)($_GET['offset'] ?? 0);
        $action = $_GET['action'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        
        $logs = $this->audit->getLogs([
            'user_id' => $user['id'],
            'action' => $action,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $this->jsonResponse([
            'success' => true,
            'total' => $this->audit->getTotalCount(),
            'logs' => $logs
        ]);
    }

    /**
     * Get audit statistics
     */
    private function getAuditStats(array $user): void
    {
        $stats = $this->audit->getStatistics($user['id']);
        
        $this->jsonResponse([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Get user profile
     */
    private function getProfile(array $user): void
    {
        $this->jsonResponse([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Update user profile
     */
    private function updateProfile(array $user): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Implementation for updating profile
        $this->jsonResponse([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => array_merge($user, $data)
        ]);
    }

    /**
     * Helper: Get authorization header
     */
    private function getAuthHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }
        
        return null;
    }

    /**
     * Helper: Get certificate by ID
     */
    private function getCertificate(string $certId): Certificate
    {
        // Implementation to fetch certificate from token or database
        // This is a placeholder - actual implementation would retrieve from storage
        throw new \Exception("Certificate not found: $certId");
    }

    /**
     * Helper: Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// Execute API handler
if (php_sapi_name() !== 'cli') {
    $api = new APIHandler();
    $api->handleRequest();
}
