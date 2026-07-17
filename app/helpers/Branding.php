<?php
// app/helpers/Branding.php
// Logo display rules:
//  - Login / public auth pages  -> always the default Modern logo.
//  - Tenant pages, staff dashboard, receipts -> the tenant's uploaded logo,
//    falling back to the default if they haven't uploaded one yet.

class Branding
{
    // Default Modern logo (you'll replace the file later; path stays the same).
    const DEFAULT_LOGO = '/public/assets/images/logo/logo.png';

    /** Always the default — used on the login/registration screens. */
    public static function loginLogo(): string
    {
        return self::DEFAULT_LOGO;
    }

    /** Tenant's own logo for internal pages & receipts, else the default. */
    public static function tenantLogo(?array $tenant): string
    {
        if ($tenant && !empty($tenant['logo_path'])) {
            return $tenant['logo_path'];
        }
        return self::DEFAULT_LOGO;
    }
}