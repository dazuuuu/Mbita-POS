<?php
// app/controllers/BranchController.php
namespace Controllers;

use Models\BranchModel;
use Models\TenantModel;
use Helpers\TenantContext;

class BranchController
{
    private BranchModel $branchModel;
    private TenantModel $tenantModel;
    
    public function __construct(\PDO $db)
    {
        $this->branchModel = new BranchModel($db);
        $this->tenantModel = new TenantModel($db);
    }
    
    /**
     * Create a new branch
     */
    public function create(array $data): array
    {
        try {
            // Get current tenant
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            // Validate
            if (empty($data['branch_name']) || empty($data['location'])) {
                return ['success' => false, 'message' => 'Branch name and location are required'];
            }
            
            // Check branch limit based on subscription
            if (!$this->canAddBranch($tenant['id'])) {
                return ['success' => false, 'message' => 'Branch limit reached for your subscription'];
            }
            
            $branch = $this->branchModel->create(
                $tenant['id'],
                $data['branch_name'],
                $data['location'],
                $data['phone'] ?? null,
                $data['email'] ?? null
            );
            
            // Log activity
            $this->logActivity($tenant['id'], 'branch_created', $branch);
            
            return [
                'success' => true,
                'message' => 'Branch created successfully',
                'data' => $branch
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get all branches for tenant
     */
    public function getAll(): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            $branches = $this->branchModel->getByTenant($tenant['id']);
            
            // Add staff count for each branch
            foreach ($branches as &$branch) {
                $branch['staff_count'] = $this->branchModel->getStaffCount($branch['id']);
            }
            
            return [
                'success' => true,
                'data' => $branches
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get branch by ID
     */
    public function get(int $id): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            $branch = $this->branchModel->getById($id);
            if (!$branch || $branch['tenant_id'] != $tenant['id']) {
                return ['success' => false, 'message' => 'Branch not found'];
            }
            
            $branch['staff_count'] = $this->branchModel->getStaffCount($branch['id']);
            
            return [
                'success' => true,
                'data' => $branch
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update branch
     */
    public function update(int $id, array $data): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            // Verify branch belongs to tenant
            if (!$this->branchModel->existsForTenant($tenant['id'], $id)) {
                return ['success' => false, 'message' => 'Branch not found'];
            }
            
            $this->branchModel->update($id, $data);
            
            // Log activity
            $this->logActivity($tenant['id'], 'branch_updated', ['branch_id' => $id]);
            
            return [
                'success' => true,
                'message' => 'Branch updated successfully'
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete branch
     */
    public function delete(int $id): array
    {
        try {
            $tenant = TenantContext::getCurrentTenant();
            if (!$tenant) {
                return ['success' => false, 'message' => 'Tenant not found'];
            }
            
            // Verify branch belongs to tenant
            if (!$this->branchModel->existsForTenant($tenant['id'], $id)) {
                return ['success' => false, 'message' => 'Branch not found'];
            }
            
            // Check if branch has staff
            $staffCount = $this->branchModel->getStaffCount($id);
            if ($staffCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete branch with active staff. Reassign or deactivate staff first.'];
            }
            
            $this->branchModel->delete($id);
            
            // Log activity
            $this->logActivity($tenant['id'], 'branch_deleted', ['branch_id' => $id]);
            
            return [
                'success' => true,
                'message' => 'Branch deleted successfully'
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if tenant can add more branches
     */
    private function canAddBranch(int $tenantId): bool
    {
        // Get subscription plan limits
        $sql = "SELECT sp.max_staff, sp.max_products 
                FROM subscriptions s
                JOIN subscription_plans sp ON s.plan_id = sp.id
                WHERE s.tenant_id = :tenant_id AND s.status = 'active'
                ORDER BY s.created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            // No active subscription
            return false;
        }
        
        // Get current branch count
        $branches = $this->branchModel->getByTenant($tenantId);
        
        // If max_staff is null, unlimited branches (using staff limit as proxy)
        if ($plan['max_staff'] === null) {
            return true;
        }
        
        return count($branches) < ($plan['max_staff'] ?? 5);
    }
    
    /**
     * Log activity
     */
    private function logActivity(int $tenantId, string $action, $details): void
    {
        // Log to tenant activity log (assuming you have this table)
        // Or use your existing logging system
        // This is a placeholder - implement based on your logging system
    }
}