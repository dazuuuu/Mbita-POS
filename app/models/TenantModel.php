<?php
// app/models/TenantModel.php
namespace Models;

/**
 * The tenant record itself. NOT tenant-scoped (it predates/defines the scope),
 * so $tenantScoped is false and queries are addressed by tenant id explicitly.
 */
class TenantModel extends Model
{
    protected string $table = 'tenants';
    protected bool $tenantScoped = false;

    public function create(string $name, string $slug, string $businessType = 'shop'): int
    {
        $data = ['name' => $name, 'slug' => $slug, 'status' => 'active'];
        if (\SchemaHelper::columnExists($this->db, $this->table, 'business_type')) {
            $data['business_type'] = in_array($businessType, ['barbershop_salon', 'shop'], true) ? $businessType : 'shop';
        }
        return $this->insert($data);
    }

    public function setOwner(int $tenantId, int $userId): bool
    {
        return $this->update($tenantId, ['owner_user_id' => $userId]);
    }

    /** Whitelisted business-settings update. Caller passes their own tenant id. */
    public function updateSettings(int $tenantId, array $data): bool
    {
        $allowed = ['name', 'logo_path', 'currency', 'phone', 'address', 'location', 'kra_pin', 'receipt_footer', 'credits_enabled', 'business_type', 'modules'];
        $clean = array_intersect_key($data, array_flip($allowed));
        if (isset($clean['modules']) && is_array($clean['modules'])) {
            $clean['modules'] = json_encode(\TenantModules::sanitizePosted($clean['modules']));
        }
        $clean = \SchemaHelper::filterColumns($this->db, $this->table, $clean);
        if (!$clean) {
            return false;
        }
        return $this->update($tenantId, $clean);
    }

    /** Whether migration 024 settings columns exist. */
    public function hasExtendedSettings(): bool
    {
        return \SchemaHelper::columnExists($this->db, $this->table, 'kra_pin');
    }

    /** Unique slug from a business name. */
    public function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($base === '') {
            $base = 'shop';
        }
        $slug = $base;
        $i = 1;
        while ($this->slugExists($slug)) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }
}