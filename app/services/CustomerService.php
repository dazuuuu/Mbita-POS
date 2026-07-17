<?php
// app/services/CustomerService.php

class CustomerService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM customers WHERE tenant_id = ? ORDER BY name ASC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $tenantId, array $in): array
    {
        $name = trim($in['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'id' => null, 'errors' => ['name' => 'Customer name is required.']];
        }
        $stmt = $this->db->prepare(
            'INSERT INTO customers (tenant_id, name, phone, email, notes, credit_limit) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $tenantId, $name,
            trim($in['phone'] ?? '') ?: null,
            trim($in['email'] ?? '') ?: null,
            trim($in['notes'] ?? '') ?: null,
            isset($in['credit_limit']) && $in['credit_limit'] !== '' && $in['credit_limit'] !== null
                ? (float) $in['credit_limit'] : null,
        ]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId(), 'errors' => []];
    }

    public function update(int $tenantId, int $id, array $in): array
    {
        if (!$this->find($tenantId, $id)) {
            return ['ok' => false, 'errors' => ['_' => 'Customer not found.']];
        }
        $name = trim($in['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'errors' => ['name' => 'Customer name is required.']];
        }
        $stmt = $this->db->prepare(
            'UPDATE customers SET name=?, phone=?, email=?, notes=?, credit_limit=? WHERE id=? AND tenant_id=?'
        );
        $stmt->execute([
            $name,
            trim($in['phone'] ?? '') ?: null,
            trim($in['email'] ?? '') ?: null,
            trim($in['notes'] ?? '') ?: null,
            isset($in['credit_limit']) && $in['credit_limit'] !== '' && $in['credit_limit'] !== null
                ? (float) $in['credit_limit'] : null,
            $id, $tenantId,
        ]);
        return ['ok' => true, 'errors' => []];
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM customers WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /** Find or create customer by name + optional phone. */
    public function resolve(int $tenantId, string $name, ?string $phone = null): ?int
    {
        $name = trim($name);
        if ($name === '') { return null; }
        $phone = trim($phone ?? '') ?: null;

        if ($phone) {
            $stmt = $this->db->prepare(
                'SELECT id FROM customers WHERE tenant_id = ? AND phone = ? LIMIT 1'
            );
            $stmt->execute([$tenantId, $phone]);
            $id = $stmt->fetchColumn();
            if ($id) { return (int) $id; }
        }

        $res = $this->create($tenantId, ['name' => $name, 'phone' => $phone]);
        return $res['ok'] ? $res['id'] : null;
    }

    public function addCredit(int $tenantId, int $customerId, float $amount): void
    {
        if ($amount <= 0) { return; }
        $stmt = $this->db->prepare(
            'UPDATE customers SET credit_balance = credit_balance + ? WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$amount, $customerId, $tenantId]);
    }

    /** Credit summary for a customer (limit, used, available). */
    public function creditSummary(int $tenantId, int $customerId, ?array $tenant = null): array
    {
        $cust = $this->find($tenantId, $customerId);
        if (!$cust) {
            return ['limit' => 0.0, 'used' => 0.0, 'available' => 0.0];
        }
        $tenant = $tenant ?? $this->loadTenant($tenantId);
        $limit = $cust['credit_limit'] ?? null;
        if ($limit === null || $limit === '') {
            $limit = (float) ($tenant['default_credit_limit'] ?? 0);
        } else {
            $limit = (float) $limit;
        }
        $used = (float) ($cust['credit_balance'] ?? 0);
        return [
            'limit'     => $limit,
            'used'      => $used,
            'available' => max(0, round($limit - $used, 2)),
            'name'      => $cust['name'],
            'phone'     => $cust['phone'] ?? '',
        ];
    }

    /** Lookup by phone or exact name for credit display at check-in/till. */
    public function lookup(int $tenantId, ?string $phone, ?string $name): ?array
    {
        if ($phone) {
            $stmt = $this->db->prepare(
                'SELECT * FROM customers WHERE tenant_id = ? AND phone = ? LIMIT 1'
            );
            $stmt->execute([$tenantId, trim($phone)]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }
        if ($name && trim($name) !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM customers WHERE tenant_id = ? AND name = ? LIMIT 1'
            );
            $stmt->execute([$tenantId, trim($name)]);
            $row = $stmt->fetch();
            return $row ?: null;
        }
        return null;
    }

    public function creditSummaryForLookup(int $tenantId, ?string $phone, ?string $name, ?array $tenant = null): ?array
    {
        $cust = $this->lookup($tenantId, $phone, $name);
        if (!$cust) {
            return null;
        }
        return $this->creditSummary($tenantId, (int) $cust['id'], $tenant);
    }

    private function loadTenant(int $tenantId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
