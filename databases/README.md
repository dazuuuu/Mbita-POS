# Database setup (Curlz / AMPPS)

## 1. Create the database

In phpMyAdmin or MySQL:

```sql
CREATE DATABASE IF NOT EXISTS curlz_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. Point the app at your database

Edit `app/config/database.php`:

```php
return [
    'host'     => 'localhost',
    'dbname'   => 'curlz_db',
    'username' => 'root',
    'password' => 'mysql',   // default AMPPS password
    'charset'  => 'utf8mb4',
];
```

## 3. Run migrations

**Option A — browser (easiest on AMPPS)**

Open once in your browser:

```
http://localhost/Curlz/public/devs/run-migrations.php
```

You should see `[run]` / `[skip]` lines for migrations `001` through `023`.

**Option B — MySQL command line**

From the project root:

```bash
mysql -u root -pmysql curlz_db < databases/migrations/001_create_users_table.sql
mysql -u root -pmysql curlz_db < databases/migrations/013_create_tenancy_tables.sql
# ... run 014–023 in numeric order
```

## 4. Register your shop (first owner)

```
http://localhost/Curlz/public/devs/register-tenant.php
```

Or log in with an existing owner account after migrations complete.

## Troubleshooting

| Error | Fix |
|-------|-----|
| `Table 'curlz_db.subscriptions' doesn't exist` | Run migrations (step 3), especially `013_create_tenancy_tables.sql` |
| Login works but features fail | Run migration `023` then `024`, or use `fix-schema-024.php` |

Remove or protect `public/devs/run-migrations.php` and `fix-schema-024.php` before going live.
