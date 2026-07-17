<?php
// app/models/BranchModel.php
namespace Models;

class BranchModel extends Model
{
    protected string $table = 'branches';

    /**
     * Create a branch for the current tenant. Title is unique within the tenant.
     * @return array ['ok'=>bool, 'id'=>?int, 'error'=>?string]
     */
    public function create(string $title, ?string $location, string $branchType, ?array $modules = null): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'id' => null, 'error' => 'Name is required.'];
        }
        if (strlen($title) > 120) {
            return ['ok' => false, 'id' => null, 'error' => 'Name is too long.'];
        }

        $validTypes = array_merge(['shop'], array_keys(\TenantModules::branchTypeLabels()));
        if (!in_array($branchType, $validTypes, true)) {
            return ['ok' => false, 'id' => null, 'error' => 'Choose a valid type.'];
        }
        if ($this->titleTaken($title)) {
            return ['ok' => false, 'id' => null, 'error' => 'You already have a location with that name.'];
        }

        $modules = $modules ?? \TenantModules::defaultsForLocationType($branchType);

        try {
            $row = [
                'title'     => $title,
                'location'  => ($location !== null && trim($location) !== '') ? trim($location) : null,
                'is_active' => 1,
            ];
            if (\SchemaHelper::columnExists($this->db, $this->table, 'branch_type')) {
                $row['branch_type'] = $branchType;
            }
            if (\SchemaHelper::columnExists($this->db, $this->table, 'modules')) {
                $row['modules'] = json_encode(\TenantModules::sanitizePosted($modules));
            }
            $row = \SchemaHelper::filterColumns($this->db, $this->table, $row);
            $id = $this->insert($row);
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'id' => null, 'error' => 'You already have a location with that name.'];
            }
            throw $e;
        }
    }

    public function updateModules(int $branchId, array $modules): bool
    {
        if (!\SchemaHelper::columnExists($this->db, $this->table, 'modules')) {
            return false;
        }
        $branch = $this->find($branchId);
        if (!$branch) {
            return false;
        }
        return $this->update($branchId, [
            'modules' => json_encode(\TenantModules::sanitizePosted($modules)),
        ]);
    }

    /**
     * @return array ['ok'=>bool, 'error'=>?string]
     */
    public function updateLocation(int $id, string $title, ?string $location, ?string $branchType = null): array
    {
        $branch = $this->find($id);
        if (!$branch) {
            return ['ok' => false, 'error' => 'Location not found.'];
        }
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'Name is required.'];
        }
        if ($this->titleTaken($title, $id)) {
            return ['ok' => false, 'error' => 'You already have a location with that name.'];
        }

        $row = [
            'title'    => $title,
            'location' => ($location !== null && trim($location) !== '') ? trim($location) : null,
        ];
        if ($branchType !== null) {
            $validTypes = array_merge(['shop'], array_keys(\TenantModules::branchTypeLabels()));
            if (!in_array($branchType, $validTypes, true)) {
                return ['ok' => false, 'error' => 'Choose a valid type.'];
            }
            if (\SchemaHelper::columnExists($this->db, $this->table, 'branch_type')) {
                $row['branch_type'] = $branchType;
            }
        }
        $row = \SchemaHelper::filterColumns($this->db, $this->table, $row);
        return $this->update($id, $row) ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'Could not save changes.'];
    }

    /**
     * @return array ['ok'=>bool, 'error'=>?string]
     */
    public function deleteSafe(int $id): array
    {
        $branch = $this->find($id);
        if (!$branch) {
            return ['ok' => false, 'error' => 'Location not found.'];
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE branch_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Reassign or remove staff from this location first.'];
        }
        return $this->delete($id) ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'Could not delete location.'];
    }

    /** Is this title already used by the current tenant? */
    public function titleTaken(string $title, ?int $exceptId = null): bool
    {
        $title = trim($title);
        foreach ($this->all([], 'title ASC') as $row) {
            if ($exceptId !== null && (int) $row['id'] === $exceptId) {
                continue;
            }
            if (strcasecmp($row['title'], $title) === 0) {
                return true;
            }
        }
        return false;
    }

    /** All branches for the current tenant, with staff counts. */
    public function listWithCounts(): array
    {
        $branches = $this->all([], 'title ASC');
        if (!$branches) {
            return [];
        }
        $ids = array_column($branches, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT branch_id, COUNT(*) AS c FROM users WHERE branch_id IN ($in) GROUP BY branch_id"
        );
        $stmt->execute($ids);
        $counts = [];
        foreach ($stmt->fetchAll() as $r) { $counts[(int) $r['branch_id']] = (int) $r['c']; }
        foreach ($branches as &$b) { $b['staff_count'] = $counts[(int) $b['id']] ?? 0; }
        return $branches;
    }
}
