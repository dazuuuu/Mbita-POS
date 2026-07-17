<?php
// app/services/AppointmentService.php

class AppointmentService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function ensureSchema(PDO $db): bool
    {
        Schema031Service::ensureApplied($db);
        return SchemaHelper::tableExists($db, 'appointments');
    }

    public function listForTenant(int $tenantId, ?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        if (!SchemaHelper::tableExists($this->db, 'appointments')) {
            return [];
        }
        $sql = "SELECT a.*, u.username AS agent_name, b.title AS branch_name
                  FROM appointments a
             LEFT JOIN users u ON u.id = a.agent_user_id
             LEFT JOIN branches b ON b.id = a.branch_id
                 WHERE a.tenant_id = ? AND a.status != 'cancelled'";
        $params = [$tenantId];
        if ($branchId) {
            $sql .= ' AND a.branch_id = ?';
            $params[] = $branchId;
        }
        if ($from) {
            $sql .= ' AND a.scheduled_at >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND a.scheduled_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY a.scheduled_at ASC LIMIT 500';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function create(int $tenantId, int $createdBy, array $in): array
    {
        if (!self::ensureSchema($this->db)) {
            return ['ok' => false, 'errors' => ['_' => 'Appointments are not set up. Run fix-all-schema.php once.']];
        }

        $name = trim($in['customer_name'] ?? '');
        $scheduledAt = trim($in['scheduled_at'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'errors' => ['customer_name' => 'Customer name is required.']];
        }
        if ($scheduledAt === '') {
            return ['ok' => false, 'errors' => ['scheduled_at' => 'Choose date and time.']];
        }

        $customerId = null;
        $phone = trim($in['customer_phone'] ?? '') ?: null;
        if ($name !== '') {
            $customerId = (new CustomerService($this->db))->resolve($tenantId, $name, $phone);
        }

        $serviceId = (int) ($in['service_id'] ?? 0) ?: null;
        $serviceName = trim($in['service_name'] ?? '') ?: null;
        if ($serviceId) {
            $svc = (new OfferedServiceService($this->db))->find($tenantId, $serviceId);
            if ($svc) {
                $serviceName = $svc['name'];
            }
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO appointments
                 (tenant_id, branch_id, customer_id, customer_name, customer_phone, service_id, service_name,
                  agent_user_id, scheduled_at, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $tenantId,
                !empty($in['branch_id']) ? (int) $in['branch_id'] : null,
                $customerId,
                $name,
                $phone,
                $serviceId,
                $serviceName,
                !empty($in['agent_user_id']) ? (int) $in['agent_user_id'] : null,
                date('Y-m-d H:i:s', strtotime($scheduledAt)),
                trim($in['notes'] ?? '') ?: null,
                $createdBy,
            ]);
            return ['ok' => true, 'id' => (int) $this->db->lastInsertId(), 'errors' => []];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['_' => 'Could not save appointment.']];
        }
    }

    public function find(int $tenantId, int $id): ?array
    {
        if (!SchemaHelper::tableExists($this->db, 'appointments')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM appointments WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markCheckedIn(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE appointments SET status = 'checked_in' WHERE id = ? AND tenant_id = ? AND status = 'scheduled'"
        );
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function cancel(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}
