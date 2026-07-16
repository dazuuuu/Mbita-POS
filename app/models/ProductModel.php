<?php
// app/models/ProductModel.php
namespace Models;

class ProductModel extends Model
{
    protected string $table = 'products';

    public const UNITS = ['piece', 'g', 'kg', 'tonne', 'ml', 'litre'];

    /**
     * @param array $in name, category_id, subcategory_id, description, quantity,
     *                  unit, buying_price, selling_price, colors[], sizes[],
     *                  image_path, low_stock_threshold, status
     */
    public function create(array $in): array
    {
        $errors = $this->validate($in);
        if ($errors) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }
        $id = $this->insert($this->columns($in));
        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function edit(int $id, array $in): array
    {
        if (!$this->find($id)) {
            return ['ok' => false, 'errors' => ['_' => 'Product not found.']];
        }
        $errors = $this->validate($in);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $this->update($id, $this->columns($in));
        return ['ok' => true, 'errors' => []];
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'draft'], true)) {
            return false;
        }
        return $this->update($id, ['status' => $status]);
    }

    public function deleteSafe(int $id): array
    {
        if (!$this->find($id)) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            'SELECT 1 FROM sale_items WHERE product_id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tid]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'This product has sales history and cannot be deleted. Set it to draft instead.'];
        }
        $this->delete($id);
        return ['ok' => true, 'error' => null];
    }

    /** Per-unit profit and margins. */
    public static function profit(float $buying, float $selling): array
    {
        $unit = $selling - $buying;
        return [
            'unit_profit' => round($unit, 2),
            'margin_pct'  => $selling > 0 ? round($unit / $selling * 100, 1) : null, // share of selling price
            'markup_pct'  => $buying > 0 ? round($unit / $buying * 100, 1) : null,    // markup over cost
        ];
    }

    /** Active products at or below their restock threshold (for alerts). */
    public function lowStock(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT * FROM products
              WHERE tenant_id = ? AND status = 'active' AND quantity <= low_stock_threshold
           ORDER BY quantity ASC"
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    /** Active, in-stock products for the till (selling price only — no cost). */
    public function sellable(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT id, name, selling_price, quantity, unit, image_path
               FROM products
              WHERE tenant_id = ? AND status = 'active' AND quantity > 0
           ORDER BY name ASC"
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    /** All active products with category names — for the public catalogue. No cost data exposed. */
    public function catalogueForTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name, p.selling_price, p.image_path, p.description, p.unit,
                    c.name AS category_name, s.name AS subcategory_name
               FROM products p
          LEFT JOIN categories c  ON c.id = p.category_id AND c.tenant_id = p.tenant_id
          LEFT JOIN subcategories s ON s.id = p.subcategory_id AND s.tenant_id = p.tenant_id
              WHERE p.tenant_id = ? AND p.status = 'active'
           ORDER BY p.name ASC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    /** Products with category + subcategory names for listing. */
    public function listWithMeta(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT p.*, c.name AS category_name, s.name AS subcategory_name
               FROM products p
          LEFT JOIN categories c ON c.id = p.category_id AND c.tenant_id = p.tenant_id
          LEFT JOIN subcategories s ON s.id = p.subcategory_id AND s.tenant_id = p.tenant_id
              WHERE p.tenant_id = ?
           ORDER BY p.name ASC"
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    // ---- internals ----

    private function validate(array $in): array
    {
        $errors = [];
        if (trim($in['name'] ?? '') === '') {
            $errors['name'] = 'Product name is required.';
        }
        $catId = (int) ($in['category_id'] ?? 0);
        if ($catId > 0 && !$this->categoryBelongsToTenant($catId)) {
            $errors['category_id'] = 'Choose a valid category.';
        }
        $subId = (int) ($in['subcategory_id'] ?? 0);
        if ($subId > 0) {
            if (!$this->subcategoryBelongsToTenant($subId)) {
                $errors['subcategory_id'] = 'Choose a valid subcategory.';
            } elseif ($catId > 0 && !$this->subcategoryBelongsToCategory($subId, $catId)) {
                $errors['subcategory_id'] = 'That subcategory is not in the chosen category.';
            }
        }
        $unit = $in['unit'] ?? 'piece';
        if (!in_array($unit, self::UNITS, true)) {
            $errors['unit'] = 'Choose a valid unit.';
        }
        if (!is_numeric($in['buying_price'] ?? null) || (float) $in['buying_price'] < 0) {
            $errors['buying_price'] = 'Enter a valid buying price.';
        }
        if (!is_numeric($in['selling_price'] ?? null) || (float) $in['selling_price'] < 0) {
            $errors['selling_price'] = 'Enter a valid selling price.';
        }
        if (!is_numeric($in['quantity'] ?? null) || (float) $in['quantity'] < 0) {
            $errors['quantity'] = 'Enter a valid quantity.';
        }
        return $errors;
    }

    private function columns(array $in): array
    {
        $subId = (int) ($in['subcategory_id'] ?? 0);
        $catId = (int) ($in['category_id'] ?? 0);
        // A subcategory implies its parent category — fill it in if left blank.
        if ($subId > 0 && $catId <= 0) {
            $catId = $this->subcategoryParent($subId);
        }
        $colors = array_values(array_filter(array_map('trim', (array) ($in['colors'] ?? []))));
        $sizes  = array_values(array_filter(array_map('trim', (array) ($in['sizes'] ?? []))));
        $status = $in['status'] ?? 'active';
        $status = in_array($status, ['active', 'draft'], true) ? $status : 'active';
        $row = [
            'category_id'         => $catId > 0 ? $catId : null,
            'subcategory_id'      => $subId > 0 ? $subId : null,
            'name'                => trim($in['name']),
            'description'         => ($in['description'] ?? '') !== '' ? trim($in['description']) : null,
            'quantity'            => (float) ($in['quantity'] ?? 0),
            'unit'                => $in['unit'] ?? 'piece',
            'buying_price'        => (float) ($in['buying_price'] ?? 0),
            'selling_price'       => (float) ($in['selling_price'] ?? 0),
            'commission_type'     => in_array($in['commission_type'] ?? 'percent', ['percent', 'fixed'], true) ? $in['commission_type'] : 'percent',
            'commission_value'    => (float) ($in['commission_value'] ?? 0),
            'credit_allowed'      => !empty($in['credit_allowed']) ? 1 : 0,
            'colors'              => $colors ? json_encode($colors) : null,
            'sizes'               => $sizes ? json_encode($sizes) : null,
            'image_path'          => ($in['image_path'] ?? '') !== '' ? $in['image_path'] : null,
            'low_stock_threshold' => (int) ($in['low_stock_threshold'] ?? 10),
            'status'              => $status,
        ];
        return \SchemaHelper::filterColumns($this->db, $this->table, $row);
    }

    private function categoryBelongsToTenant(int $categoryId): bool
    {
        if ($categoryId <= 0) { return false; }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM categories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$categoryId, $tid]);
        return (bool) $stmt->fetchColumn();
    }

    private function subcategoryBelongsToCategory(int $subId, int $categoryId): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM subcategories WHERE id = ? AND category_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$subId, $categoryId, $tid]);
        return (bool) $stmt->fetchColumn();
    }

    private function subcategoryBelongsToTenant(int $subId): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM subcategories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$subId, $tid]);
        return (bool) $stmt->fetchColumn();
    }

    private function subcategoryParent(int $subId): int
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT category_id FROM subcategories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$subId, $tid]);
        return (int) $stmt->fetchColumn();
    }
}