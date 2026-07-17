<?php
// app/helpers/StaffRoles.php
// Staff role templates, labels, and which capabilities an owner may delegate.

class StaffRoles
{
    /** Role names used for non-owner employees (PIN login). */
    public static function employeeRoleNames(): array
    {
        return ['staff', 'cashier', 'reception', 'sales', 'barber', 'junior_admin'];
    }

    public static function isEmployeeRole(?string $role): bool
    {
        return $role !== null && in_array($role, self::employeeRoleNames(), true);
    }

    /** staff_type value → roles.role_name used at login. */
    public static function roleForType(string $staffType): string
    {
        $map = [
            'cashier'      => 'cashier',
            'reception'    => 'reception',
            'sales'        => 'sales',
            'barber'       => 'barber',
            'junior_admin' => 'junior_admin',
            'general'      => 'staff',
        ];
        return $map[$staffType] ?? 'staff';
    }

    public static function typeLabels(): array
    {
        return [
            'cashier'      => 'Cashier',
            'reception'    => 'Reception',
            'sales'        => 'Sales',
            'barber'       => 'Barber / Stylist',
            'junior_admin' => 'Junior Admin',
            'general'      => 'General Staff',
        ];
    }

    public static function typeDescriptions(): array
    {
        return [
            'cashier'      => 'Receive and update payments, overcheck payments, and follow-ups.',
            'reception'    => 'Appointments, check-in, service invoices. Assigns staff; payment at till.',
            'sales'        => 'Sell products, view catalogue, generate invoices, appointments, and commissions.',
            'barber'       => 'Performs services. Commission credited when invoice is paid at till.',
            'junior_admin' => 'Manage inventory, oversee sales, view reports. Delegated admin powers.',
            'general'      => 'Basic staff with view/sell defaults — customize permissions below.',
        ];
    }

    /** Capability groups shown on the authorization page. */
    public static function permissionGroups(?array $modules = null): array
    {
        $mods = $modules ?? TenantModules::defaults();
        $groups = [
            'Payments & till' => [
                [Capabilities::PAYMENTS_RECEIVE, 'Receive payments', 'Process cash/M-Pesa at till (default: cashier & reception). Delegate via staff permissions.'],
                [Capabilities::PAYMENTS_UPDATE, 'Update & verify payments', 'Correct payment records and follow up on pending payments'],
                [Capabilities::SALES_RECORD,   'Make sales', 'Use the till to record product sales'],
                [Capabilities::SALES_VIEW,       'View sales', 'See sales history and receipts'],
            ],
            'Appointments & customers' => [
                [Capabilities::APPOINTMENTS_MANAGE, 'Manage appointments', 'Book, reschedule, and cancel appointments'],
                [Capabilities::INVOICES_MANAGE,   'Generate invoices', 'Check-in customers and create service invoices (payment at till)'],
                [Capabilities::CUSTOMERS_MANAGE,  'Manage customers', 'Add and edit customer details'],
                [Capabilities::CUSTOMERS_CHECKIN, 'Customer check-in', 'Check customers in when they arrive'],
            ],
            'Products & inventory' => [
                [Capabilities::INVENTORY_VIEW, 'View products', 'See the product list and prices'],
                [Capabilities::INVENTORY_EDIT, 'Add & edit products', 'Create products and change details or prices'],
                [Capabilities::STOCK_ENTER,    'Enter stock', 'Add incoming stock and adjust quantities'],
                [Capabilities::DISCOUNTS_MANAGE, 'Manage discounts', 'Create and apply discounts'],
                [Capabilities::CREDITS_MANAGE,   'Manage credits', 'Issue and track customer credit sales'],
            ],
            'Services & commission' => [
                [Capabilities::COMMISSION_RECORD, 'Record commissioned sales', 'Create service sales (reception: pending invoice; staff with payment rights: immediate)'],
                [Capabilities::COMMISSION_VIEW,   'View own commission', 'See commission earned and payout history'],
            ],
            'Administration' => [
                [Capabilities::REPORTS_VIEW,   'View reports', 'See sales and performance reports'],
                [Capabilities::CATALOGUE_SEND, 'Share catalogue', 'Send the public product catalogue link'],
            ],
        ];

        if (empty($mods[TenantModules::PRODUCTS])) {
            unset($groups['Products & inventory']);
        }
        if (empty($mods[TenantModules::SERVICES])
            && empty($mods[TenantModules::PRODUCT_COMMISSIONS])
            && empty($mods[TenantModules::SERVICE_COMMISSIONS])) {
            unset($groups['Services & commission']);
        }

        return $groups;
    }

    public static function manageableCapabilities(?array $modules = null): array
    {
        $out = [];
        foreach (self::permissionGroups($modules) as $rows) {
            foreach ($rows as $row) {
                $out[] = $row[0];
            }
        }
        return $out;
    }

    /** Which staff types are available for this tenant's enabled modules. */
    public static function availableStaffTypes(?array $modules = null): array
    {
        $mods = $modules ?? TenantModules::defaults();
        $types = ['general', 'sales'];
        if (!empty($mods[TenantModules::CASHIER])) {
            $types[] = 'cashier';
        }
        if (!empty($mods[TenantModules::RECEPTION])) {
            $types[] = 'reception';
        }
        if (!empty($mods[TenantModules::SERVICES])) {
            $types[] = 'barber';
        }
        if (!empty($mods[TenantModules::STAFF_PROMOTION])) {
            $types[] = 'junior_admin';
        }
        return $types;
    }
}
