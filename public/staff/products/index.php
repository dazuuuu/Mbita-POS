<?php
// public/staff/products/index.php
// Staff entry point for entering/editing products. It reuses the very same
// products engine as the owner page (no duplicated logic); that page is gated
// by the 'inventory.edit' capability and renders in the staff layout for staff.
require __DIR__ . '/../../super/products/index.php';