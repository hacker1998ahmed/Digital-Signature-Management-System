<?php
/**
 * Authentication & Authorization Manager
 * Handles user authentication, JWT tokens, roles, and permissions
 * 
 * @package DigitalSignatureFactory\Auth
 * @version 2.0.0
 */

namespace DigitalSignatureFactory\Auth;

use PDO;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateTime;

class AuthManager
{
    private PDO $db;
    private array $config;
    private string $jwtSecret;
    private int $jwtExpire;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->jwtSecret = $config['security']['jwt_secret'] ?? bin2hex(random_bytes(32));
        $this->jwtExpire = $config['security']['jwt_expire'] ?? 3600;
        
        // Database connection
        $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset=utf8mb4";
        $this->db = new PDO($dsn, $config['database']['user'], $config['database']['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }

    /**
     * Register new user
     */
    public function register(array $data): array
    {
        try {
            // Check if username exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$data['username'], $data['email']]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_ARGON2ID);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, full_name, password_hash, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $role = $data['role'] ?? 'user';
            $stmt->execute([
                $data['username'],
                $data['email'],
                $data['full_name'],
                $passwordHash,
                $role
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Get user data
            $user = $this->getUserById($userId);
            
            return [
                'success' => true,
                'message' => 'User registered successfully',
                'user' => $user
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     */
    public function login(string $username, string $password): array
    {
        try {
            // Find user by username or email
            $stmt = $this->db->prepare("
                SELECT * FROM users 
                WHERE (username = ? OR email = ?) 
                AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is locked
            if ($user['login_attempts'] >= 5) {
                $lockedUntil = strtotime($user['locked_until']);
                if ($lockedUntil > time()) {
                    return ['success' => false, 'message' => 'Account temporarily locked. Try again later.'];
                } else {
                    // Reset lock
                    $stmt = $this->db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
                    $stmt->execute([$user['id']]);
                }
            }
            
            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Generate JWT token
            $token = $this->generateToken($user);
            
            // Remove sensitive data
            unset($user['password_hash']);
            
            return [
                'success' => true,
                'token' => $token,
                'user' => $user,
                'expires_in' => $this->jwtExpire
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    /**
     * Logout user (invalidate token)
     */
    public function logout(string $token): bool
    {
        try {
            // Add token to blacklist
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            $stmt = $this->db->prepare("
                INSERT INTO token_blacklist (token, expires_at) 
                VALUES (?, ?)
            ");
            $stmt->execute([$token, date('Y-m-d H:i:s', $payload->exp)]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): bool
    {
        try {
            // Check if token is blacklisted
            $stmt = $this->db->prepare("SELECT id FROM token_blacklist WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            
            if ($stmt->fetch()) {
                return false; // Token is blacklisted
            }
            
            // Decode and validate token
            JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user from token
     */
    public function getUserFromToken(string $token): array
    {
        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return $this->getUserById($payload->sub);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate JWT token
     */
    private function generateToken(array $user): string
    {
        $issuedAt = time();
        $expireTime = $issuedAt + $this->jwtExpire;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expireTime,
            'iss' => 'DigitalSignatureFactory',
            'sub' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'permissions' => $this->getPermissions($user['role'])
        ];
        
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Refresh token
     */
    public function refreshToken(string $token): array
    {
        if (!$this->validateToken($token)) {
            return ['success' => false, 'message' => 'Invalid token'];
        }
        
        $user = $this->getUserFromToken($token);
        
        if (empty($user)) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Blacklist old token
        $this->logout($token);
        
        // Generate new token
        $newToken = $this->generateToken($user);
        
        return [
            'success' => true,
            'token' => $newToken,
            'expires_in' => $this->jwtExpire
        ];
    }

    /**
     * Change password
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        try {
            // Get user
            $user = $this->getUserById($userId);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Verify old password
            if (!password_verify($oldPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid current password'];
            }
            
            // Hash new password
            $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
            
            // Update password
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $userId]);
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Password change failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reset password request
     */
    public function requestPasswordReset(string $email): array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Don't reveal if email exists for security
                return ['success' => true, 'message' => 'If the email exists, a reset link will be sent'];
            }
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            $stmt = $this->db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $resetToken, $expiresAt]);
            
            // In production, send email with reset link
            // For now, just return success
            return [
                'success' => true,
                'message' => 'Password reset link sent to email',
                'debug_token' => $resetToken // Remove in production
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Reset request failed'];
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        try {
            // Find valid reset token
            $stmt = $this->db->prepare("
                SELECT user_id FROM password_resets 
                WHERE token = ? AND expires_at > NOW() AND used = 0
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if (!$reset) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            // Hash new password
            $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
            
            // Update password
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $reset['user_id']]);
            
            // Mark token as used
            $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Password reset failed'];
        }
    }

    /**
     * Check permission
     */
    public function hasPermission(array $user, string $permission): bool
    {
        $permissions = $this->getPermissions($user['role']);
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Get permissions by role
     */
    public function getPermissions(string $role): array
    {
        $permissionsMap = [
            'admin' => ['*'], // All permissions
            'manager' => [
                'view_tokens', 'manage_tokens',
                'view_certificates', 'create_certificates', 'manage_certificates',
                'sign_documents', 'bulk_sign',
                'view_audit_logs', 'export_audit_logs',
                'manage_users'
            ],
            'user' => [
                'view_tokens',
                'view_certificates',
                'sign_documents',
                'view_own_audit_logs'
            ],
            'auditor' => [
                'view_audit_logs', 'export_audit_logs',
                'view_certificates', 'view_tokens'
            ]
        ];
        
        return $permissionsMap[$role] ?? [];
    }

    /**
     * Get user by ID
     */
    private function getUserById(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password_hash']);
        }
        
        return $user ?: [];
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedLogin(string $username): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = login_attempts + 1,
                    locked_until = CASE 
                        WHEN login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE locked_until
                    END
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
        } catch (\Exception $e) {
            // Log error silently
        }
    }

    /**
     * Get all users (admin only)
     */
    public function getAllUsers(): array
    {
        $stmt = $this->db->query("
            SELECT id, username, email, full_name, role, status, 
                   created_at, last_login, login_attempts
            FROM users
            ORDER BY created_at DESC
        ");
        
        return $stmt->fetchAll();
    }

    /**
     * Update user role (admin only)
     */
    public function updateUserRole(int $userId, string $role): array
    {
        try {
            $validRoles = ['admin', 'manager', 'user', 'auditor'];
            
            if (!in_array($role, $validRoles)) {
                return ['success' => false, 'message' => 'Invalid role'];
            }
            
            $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $userId]);
            
            return ['success' => true, 'message' => 'User role updated'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    /**
     * Activate/Deactivate user (admin only)
     */
    public function setUserStatus(int $userId, string $status): array
    {
        try {
            if (!in_array($status, ['active', 'inactive', 'suspended'])) {
                return ['success' => false, 'message' => 'Invalid status'];
            }
            
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
            
            return ['success' => true, 'message' => 'User status updated'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }
}
