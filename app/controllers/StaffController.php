<?php
// app/controllers/StaffController.php
namespace Controllers;

use Models\StaffModel;
use Models\BranchModel;
use Helpers\TenantContext;

class StaffController
{
    private StaffModel $staffModel;
    private BranchModel $branchModel;
    
    public function __construct(\PDO $db)
    {
        $this->staffModel = new StaffModel($db);
        $this->branchModel = new BranchModel($db);
    }
    
    /**
     * Create staff member
     */
    public function create(array $data): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            // Validate
            if (empty($data['email']) || empty($data['branch_id'])) {
                return ['success' => false, 'message' => 'Email and branch are required'];
            }
            
            // Verify branch belongs to tenant
            if (!$this->branchModel->existsForTenant($tenant['id'], $data['branch_id'])) {
                return ['success' => false, 'message' => 'Invalid branch selected'];
            }
            
            // Check staff limit
            if (!$this->canAddStaff($tenant['id'])) {
                return ['success' => false, 'message' => 'Staff limit reached for your subscription'];
            }
            
            $data['tenant_id'] = $tenant['id'];
            $staff = $this->staffModel->create($data);
            
            return [
                'success' => true,
                'message' => 'Staff member created successfully. Password has been sent to their email.',
                'data' => $staff
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get all staff for tenant
     */
    public function getAll(): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            $staff = $this->staffModel->getByTenant($tenant['id']);
            
            return [
                'success' => true,
                'data' => $staff
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get staff by ID
     */
    public function get(int $id): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            $staff = $this->staffModel->getById($id);
            if (!$staff || $staff['tenant_id'] != $tenant['id']) {
                return ['success' => false, 'message' => 'Staff not found'];
            }
            
            return [
                'success' => true,
                'data' => $staff
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update staff
     */
    public function update(int $id, array $data): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            // Verify staff belongs to tenant
            $staff = $this->staffModel->getById($id);
            if (!$staff || $staff['tenant_id'] != $tenant['id']) {
                return ['success' => false, 'message' => 'Staff not found'];
            }
            
            // If updating branch, verify it belongs to tenant
            if (isset($data['branch_id'])) {
                if (!$this->branchModel->existsForTenant($tenant['id'], $data['branch_id'])) {
                    return ['success' => false, 'message' => 'Invalid branch selected'];
                }
            }
            
            $this->staffModel->update($id, $data);
            
            return [
                'success' => true,
                'message' => 'Staff updated successfully'
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete staff
     */
    public function delete(int $id): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            // Verify staff belongs to tenant
            $staff = $this->staffModel->getById($id);
            if (!$staff || $staff['tenant_id'] != $tenant['id']) {
                return ['success' => false, 'message' => 'Staff not found'];
            }
            
            $this->staffModel->delete($id);
            
            return [
                'success' => true,
                'message' => 'Staff deleted successfully'
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if tenant can add more staff
     */
    private function canAddStaff(int $tenantId): bool
    {
        // Get subscription plan limits
        $sql = "SELECT sp.max_staff 
                FROM subscriptions s
                JOIN subscription_plans sp ON s.plan_id = sp.id
                WHERE s.tenant_id = :tenant_id AND s.status = 'active'
                ORDER BY s.created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            return false;
        }
        
        // Get current staff count
        $staff = $this->staffModel->getByTenant($tenantId);
        $count = count($staff);
        
        // If max_staff is null, unlimited
        if ($plan['max_staff'] === null) {
            return true;
        }
        
        return $count < $plan['max_staff'];
    }
}