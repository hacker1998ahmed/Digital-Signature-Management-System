<?php
/**
 * Digital Signature Factory - Main Entry Point (API)
 * 
 * This file handles all API requests and routes them to the appropriate handlers.
 * نقطة الدخول الرئيسية لواجهة برمجة التطبيقات
 */

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load Environment Variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Import Core Classes
use DSF\Core\DigitalSignatureFactory;
use DSF\API\APIHandler;
use DSF\Auth\AuthManager;
use DSF\Utils\AuditLogger;

// Set Headers for API Response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Powered-By: Digital Signature Factory v2.0');

// Handle CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize Audit Logger
$auditLogger = new AuditLogger();

// Get Request Method and URI
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/api';

// Remove base path from URI
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Initialize API Handler
$apiHandler = new APIHandler();

// Initialize Auth Manager
$authManager = new AuthManager();

// Try to authenticate user from JWT token
$currentUser = null;
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $currentUser = $authManager->verifyToken($token);
}

// Route the request
try {
    // Public routes (no authentication required)
    $publicRoutes = [
        ['POST', '/auth/login'],
        ['POST', '/auth/register'],
        ['GET', '/health'],
        ['GET', '/version']
    ];

    $isPublicRoute = false;
    foreach ($publicRoutes as $route) {
        if ($route[0] === $requestMethod && $route[1] === $requestUri) {
            $isPublicRoute = true;
            break;
        }
    }

    // Check authentication for protected routes
    if (!$isPublicRoute && !$currentUser) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Authentication required. Please provide a valid JWT token.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $auditLogger->log('API_ACCESS_DENIED', 'warning', ['uri' => $requestUri, 'ip' => $_SERVER['REMOTE_ADDR']]);
        exit();
    }

    // Log API access
    if ($currentUser) {
        $auditLogger->log('API_ACCESS', 'info', [
            'user_id' => $currentUser['id'],
            'username' => $currentUser['username'],
            'uri' => $requestUri,
            'method' => $requestMethod,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }

    // Handle the request
    $response = $apiHandler->handle($requestMethod, $requestUri, $currentUser);
    
    // Send response
    http_response_code($response['status'] ?? 200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log error
    $auditLogger->log('API_ERROR', 'error', [
        'uri' => $requestUri,
        'method' => $requestMethod,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'An unexpected error occurred.',
        'debug' => getenv('APP_DEBUG') === 'true' ? $e->getTraceAsString() : null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
