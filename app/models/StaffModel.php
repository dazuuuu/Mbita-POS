<?php
// app/models/StaffModel.php
namespace Models;

use PDO;
use RuntimeException;

class StaffModel extends Model
{
    // Remove this line - it's already defined in parent Model class
    // private PDO $db;
    
    /**
     * Create staff member with auto-generated password
     */
    public function create(array $data): array
    {
        // Get database connection from parent
        $db = $this->db;
        
        // Validate
        if (empty($data['email']) || empty($data['tenant_id']) || empty($data['branch_id'])) {
            throw new RuntimeException('Email, tenant, and branch are required');
        }
        
        // Check if user already exists
        $existingUser = $this->findUserByEmail($data['email']);
        
        if ($existingUser) {
            // User exists, check if already staff for this tenant
            $existingStaff = $this->findStaffByUserAndTenant($existingUser['id'], $data['tenant_id']);
            if ($existingStaff) {
                throw new RuntimeException('This user is already staff for this tenant');
            }
            $userId = $existingUser['id'];
        } else {
            // Create new user
            $userId = $this->createUser($data['email'], $data['first_name'] ?? '', $data['last_name'] ?? '');
        }
        
        // Generate staff code
        $staffCode = $this->generateStaffCode($data['tenant_id']);
        
        // Create staff record
        $sql = "INSERT INTO staff (user_id, tenant_id, branch_id, staff_code, position, department, hire_date, salary, permissions) 
                VALUES (:user_id, :tenant_id, :branch_id, :staff_code, :position, :department, :hire_date, :salary, :permissions)";
        $stmt = $db->prepare($sql);
        
        $success = $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $data['tenant_id'],
            ':branch_id' => $data['branch_id'],
            ':staff_code' => $staffCode,
            ':position' => $data['position'] ?? null,
            ':department' => $data['department'] ?? null,
            ':hire_date' => $data['hire_date'] ?? null,
            ':salary' => $data['salary'] ?? null,
            ':permissions' => isset($data['permissions']) ? json_encode($data['permissions']) : null
        ]);
        
        if (!$success) {
            throw new RuntimeException('Failed to create staff member');
        }
        
        $staffId = $db->lastInsertId();
        
        // Generate and send password
        $password = $this->generatePassword();
        $this->updateUserPassword($userId, $password);
        
        // Send welcome email with password
        $this->sendWelcomeEmail($data['email'], $data['first_name'] ?? '', $password);
        
        return $this->getById($staffId);
    }
    
    /**
     * Create a new user account
     */
    private function createUser(string $email, string $firstName, string $lastName): int
    {
        $db = $this->db;
        $username = $this->generateUsername($email);
        $passwordHash = password_hash($this->generatePassword(), PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified) 
                VALUES (:username, :email, :password_hash, 3, 1, 1)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash
        ]);
        
        $userId = $db->lastInsertId();
        
        // Add profile
        $sql = "INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (:user_id, :first_name, :last_name)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':first_name' => $firstName,
            ':last_name' => $lastName
        ]);
        
        return $userId;
    }
    
    /**
     * Find user by email
     */
    private function findUserByEmail(string $email): ?array
    {
        $db = $this->db;
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Find staff by user ID and tenant ID
     */
    private function findStaffByUserAndTenant(int $userId, int $tenantId): ?array
    {
        $db = $this->db;
        $sql = "SELECT * FROM staff WHERE user_id = :user_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Update user password
     */
    private function updateUserPassword(int $userId, string $password): void
    {
        $db = $this->db;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = :hash WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':hash' => $hash, ':id' => $userId]);
    }
    
    /**
     * Generate unique staff code
     */
    private function generateStaffCode(int $tenantId): string
    {
        $db = $this->db;
        $code = 'STF-' . strtoupper(substr(uniqid(), -6));
        
        // Check uniqueness
        $stmt = $db->prepare("SELECT id FROM staff WHERE tenant_id = ? AND staff_code = ?");
        $stmt->execute([$tenantId, $code]);
        
        if ($stmt->fetch()) {
            return $this->generateStaffCode($tenantId);
        }
        
        return $code;
    }
    
    /**
     * Generate random password
     */
    private function generatePassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    /**
     * Generate username from email
     */
    private function generateUsername(string $email): string
    {
        $db = $this->db;
        $username = explode('@', $email)[0];
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        
        if ($stmt->fetch()) {
            $username .= '_' . random_int(100, 999);
        }
        
        return $username;
    }
    
    /**
     * Send welcome email with password
     */
    private function sendWelcomeEmail(string $email, string $name, string $password): void
    {
        // Use your existing MailService
        if (class_exists('\Services\MailService')) {
            $mailService = new \Services\MailService();
            
            $subject = "Welcome to Modern POS - Your Staff Account";
            $body = "Hello $name,\n\n";
            $body .= "Your staff account has been created.\n\n";
            $body .= "Email: $email\n";
            $body .= "Password: $password\n\n";
            $body .= "Please login at: " . $_SERVER['HTTP_HOST'] . "/Modern/public/auth/login.php\n\n";
            $body .= "Regards,\nModern POS Team";
            
            $mailService->send($email, $subject, $body);
        }
    }
    
    /**
     * Get staff by ID
     */
    public function getById(int $id): ?array
    {
        $db = $this->db;
        $sql = "SELECT s.*, u.email, u.username, u.is_active as user_active,
                       p.first_name, p.last_name, p.phone,
                       b.branch_name, b.location as branch_location,
                       t.name as tenant_name
                FROM staff s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN user_profiles p ON u.id = p.user_id
                JOIN branches b ON s.branch_id = b.id
                JOIN tenants t ON s.tenant_id = t.id
                WHERE s.id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Get all staff for a tenant
     */
    public function getByTenant(int $tenantId, bool $onlyActive = true): array
    {
        $db = $this->db;
        $sql = "SELECT s.*, u.email, u.username, 
                       p.first_name, p.last_name, p.phone,
                       b.branch_name, b.location as branch_location
                FROM staff s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN user_profiles p ON u.id = p.user_id
                JOIN branches b ON s.branch_id = b.id
                WHERE s.tenant_id = :tenant_id";
        
        if ($onlyActive) {
            $sql .= " AND s.is_active = 1 AND u.is_active = 1";
        }
        
        $sql .= " ORDER BY p.first_name ASC, p.last_name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get staff by branch
     */
    public function getByBranch(int $branchId, bool $onlyActive = true): array
    {
        $db = $this->db;
        $sql = "SELECT s.*, u.email, u.username, 
                       p.first_name, p.last_name, p.phone
                FROM staff s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE s.branch_id = :branch_id";
        
        if ($onlyActive) {
            $sql .= " AND s.is_active = 1 AND u.is_active = 1";
        }
        
        $sql .= " ORDER BY p.first_name ASC, p.last_name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':branch_id' => $branchId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update staff details
     */
    public function update(int $id, array $data): bool
    {
        $db = $this->db;
        $allowedFields = ['branch_id', 'position', 'department', 'hire_date', 'salary', 'permissions', 'is_active'];
        $setParts = [];
        $params = [':id' => $id];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = :$field";
                if ($field === 'permissions' && is_array($data[$field])) {
                    $data[$field] = json_encode($data[$field]);
                }
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $sql = "UPDATE staff SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete staff (soft delete)
     */
    public function delete(int $id): bool
    {
        $db = $this->db;
        $sql = "UPDATE staff SET is_active = 0 WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Log staff activity
     */
    public function logActivity(int $staffId, string $action, ?array $details = null, ?string $ip = null): void
    {
        $db = $this->db;
        $sql = "INSERT INTO staff_activity_log (staff_id, action, details, ip_address) 
                VALUES (:staff_id, :action, :details, :ip_address)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':staff_id' => $staffId,
            ':action' => $action,
            ':details' => $details ? json_encode($details) : null,
            ':ip_address' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    /**
     * Get staff by user ID and tenant ID
     */
    public function getByUserAndTenant(int $userId, int $tenantId): ?array
    {
        $db = $this->db;
        $sql = "SELECT s.*, b.branch_name, b.location as branch_location,
                       t.name as tenant_name
                FROM staff s
                JOIN branches b ON s.branch_id = b.id
                JOIN tenants t ON s.tenant_id = t.id
                WHERE s.user_id = :user_id AND s.tenant_id = :tenant_id AND s.is_active = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
        return $stmt->fetch() ?: null;
    }
}