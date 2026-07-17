<?php
// app/services/OfferedServiceService.php
// Tenant business services with pricing, expenses, and commission settings.

class OfferedServiceService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listForTenant(int $tenantId, ?int $branchId = null): array
    {
        if (!$this->ready()) {
            return [];
        }
        try {
            $branchSql = '';
            $params = [$tenantId];
            if ($branchId !== null && $branchId > 0 && SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id')) {
                $branchSql = ' AND branch_id = ?';
                $params[] = $branchId;
            }
            $branchJoin = SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id')
                ? 'LEFT JOIN branches b ON b.id = tenant_services.branch_id'
                : '';
            $branchCol = SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id')
                ? ', b.title AS branch_title'
                : '';
            $stmt = $this->db->prepare(
                "SELECT tenant_services.*{$branchCol}
                   FROM tenant_services
                   {$branchJoin}
                  WHERE tenant_id = ?{$branchSql}
               ORDER BY name ASC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$row) {
                $row['expenses'] = $this->expensesForService((int) $row['id']);
            }
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->ready() || $id <= 0) {
            return null;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM tenant_services WHERE id = ? AND tenant_id = ? LIMIT 1'
            );
            $stmt->execute([$id, $tenantId]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            $row['expenses'] = $this->expensesForService((int) $row['id']);
            return $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function create(int $tenantId, array $in): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'id' => null, 'errors' => ['_' => 'Services table is not set up. Run database migrations first.']];
        }
        $errors = $this->validate($in);
        if ($errors) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        try {
            $cols = 'tenant_id, name, description, charge_amount, commission_type, commission_value, status';
            $vals = '?,?,?,?,?,?,?';
            $params = [
                $tenantId,
                trim($in['name']),
                trim($in['description'] ?? '') ?: null,
                (float) ($in['charge_amount'] ?? 0),
                $in['commission_type'] ?? 'percent',
                (float) ($in['commission_value'] ?? 0),
                ($in['status'] ?? 'active') === 'draft' ? 'draft' : 'active',
            ];
            if (SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id')) {
                $cols .= ', branch_id';
                $vals .= ',?';
                $params[] = (int) ($in['branch_id'] ?? 0) > 0 ? (int) $in['branch_id'] : null;
            }
            $stmt = $this->db->prepare("INSERT INTO tenant_services ({$cols}) VALUES ({$vals})");
            $stmt->execute($params);
            $id = (int) $this->db->lastInsertId();
            $this->saveExpenses($id, $in['expenses'] ?? []);
            return ['ok' => true, 'id' => $id, 'errors' => []];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'errors' => ['_' => 'Could not create service.']];
        }
    }

    public function update(int $tenantId, int $id, array $in): array
    {
        if (!$this->find($tenantId, $id)) {
            return ['ok' => false, 'errors' => ['_' => 'Service not found.']];
        }
        $errors = $this->validate($in);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $sets = 'name=?, description=?, charge_amount=?, commission_type=?, commission_value=?, status=?';
            $params = [
                trim($in['name']),
                trim($in['description'] ?? '') ?: null,
                (float) ($in['charge_amount'] ?? 0),
                $in['commission_type'] ?? 'percent',
                (float) ($in['commission_value'] ?? 0),
                ($in['status'] ?? 'active') === 'draft' ? 'draft' : 'active',
            ];
            if (SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id')) {
                $sets .= ', branch_id=?';
                $params[] = (int) ($in['branch_id'] ?? 0) > 0 ? (int) $in['branch_id'] : null;
            }
            $params[] = $id;
            $params[] = $tenantId;
            $stmt = $this->db->prepare(
                "UPDATE tenant_services SET {$sets} WHERE id=? AND tenant_id=?"
            );
            $stmt->execute($params);
            $this->db->prepare('DELETE FROM service_expenses WHERE service_id = ?')->execute([$id]);
            $this->saveExpenses($id, $in['expenses'] ?? []);
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['_' => 'Could not update service.']];
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        if (!$this->find($tenantId, $id)) {
            return false;
        }
        try {
            if (SchemaHelper::tableExists($this->db, 'commission_sales')) {
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM commission_sales WHERE service_id = ? AND tenant_id = ?'
                );
                $stmt->execute([$id, $tenantId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    return false;
                }
            }
            $this->db->prepare('DELETE FROM service_expenses WHERE service_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM tenant_services WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function activeForTenant(int $tenantId, ?int $branchId = null): array
    {
        if (!$this->ready()) {
            return [];
        }
        try {
            $branchSql = '';
            $params = [$tenantId];
            if ($branchId !== null && $branchId > 0 && SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id')) {
                $branchSql = ' AND (branch_id = ? OR branch_id IS NULL)';
                $params[] = $branchId;
            }
            $stmt = $this->db->prepare(
                "SELECT * FROM tenant_services WHERE tenant_id = ? AND status = 'active'{$branchSql} ORDER BY name ASC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$row) {
                $row['expenses'] = $this->expensesForService((int) $row['id']);
            }
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function ready(): bool
    {
        return SchemaHelper::tableExists($this->db, 'tenant_services');
    }

    private function validate(array $in): array
    {
        $errors = [];
        if (trim($in['name'] ?? '') === '') {
            $errors['name'] = 'Service name is required.';
        }
        if (!in_array($in['commission_type'] ?? 'percent', ['percent', 'fixed'], true)) {
            $errors['commission_type'] = 'Invalid commission type.';
        }
        $branchId = (int) ($in['branch_id'] ?? 0);
        if (SchemaHelper::columnExists($this->db, 'tenant_services', 'branch_id') && $branchId <= 0) {
            $errors['branch_id'] = 'Select which branch or shop offers this service.';
        }
        return $errors;
    }

    private function expensesForService(int $serviceId): array
    {
        if (!SchemaHelper::tableExists($this->db, 'service_expenses')) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT id, name, cost FROM service_expenses WHERE service_id = ? ORDER BY id ASC'
            );
            $stmt->execute([$serviceId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function saveExpenses(int $serviceId, array $expenses): void
    {
        if (!SchemaHelper::tableExists($this->db, 'service_expenses')) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO service_expenses (service_id, name, cost) VALUES (?,?,?)'
        );
        foreach ($expenses as $exp) {
            $name = trim($exp['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $stmt->execute([$serviceId, $name, (float) ($exp['cost'] ?? 0)]);
        }
    }
}
