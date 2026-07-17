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
    public function create(string $title, ?string $location): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'id' => null, 'error' => 'Branch name is required.'];
        }
        if (strlen($title) > 120) {
            return ['ok' => false, 'id' => null, 'error' => 'Branch name is too long.'];
        }
        if ($this->titleTaken($title)) {
            return ['ok' => false, 'id' => null, 'error' => 'You already have a branch with that name.'];
        }
        try {
            $id = $this->insert([
                'title'     => $title,
                'location'  => ($location !== null && trim($location) !== '') ? trim($location) : null,
                'is_active' => 1,
            ]);
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') { // unique violation race
                return ['ok' => false, 'id' => null, 'error' => 'You already have a branch with that name.'];
            }
            throw $e;
        }
    }

    /** Is this title already used by the current tenant? (Tenant-scoped read.) */
    public function titleTaken(string $title): bool
    {
        $rows = $this->all(['title' => trim($title)]);
        return count($rows) > 0;
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