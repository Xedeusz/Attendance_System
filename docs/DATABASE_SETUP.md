# Local Database Setup

Phase 3 update: the additive HR/Employee and master-data migration is applied. The database now contains 17 tables. See [implemented schema](DATABASE_SCHEMA.md) and [Phase 3 setup](PHASE_3_IMPLEMENTATION.md). Counts and scope statements below are the retained Phase 2 setup record.

Phase 2 verification date: 2026-11-12. No employee, branch, attendance, leave, or biometric tables have been created.

## Verified configuration

| Setting | Actual local configuration |
| --- | --- |
| Server | XAMPP MariaDB **10.11.16**, not Oracle MySQL |
| Host / port | `127.0.0.1:3306` |
| Database | `employee_biometric_db` |
| Encoding / collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| PHP driver | PDO MySQL (`pdo_mysql`), Laravel `mysql` connection |
| Application identity | Dedicated randomly named local development user; credentials only in ignored `.env` |
| Authentication | Local MariaDB account with a generated password; application does not use the administrative account |
| Migrations | All three standard Laravel migrations applied in batch 1 |

The server was not running initially; the existing XAMPP server was started without modifying existing database files. Administrative local connectivity was verified before creating a fresh project database and restricted database user. The setup refused a nonempty project database. No existing database was reset, dropped, or migrated with `migrate:fresh`.

## Creation on another workstation

Start MySQL/MariaDB in the XAMPP control panel. Verify the actual version; the button can say MySQL while the product is MariaDB. Log in using your administrator's actual credentials, for example:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u YOUR_DATABASE_ADMIN -p
```

Inside the SQL client, create a new database and dedicated account. Replace the uppercase user/password placeholders privately; do not paste a real password into tracked files, chat, or shared terminal logs.

```sql
CREATE DATABASE employee_biometric_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'YOUR_APP_USER'@'127.0.0.1' IDENTIFIED BY 'YOUR_PRIVATE_GENERATED_PASSWORD';
GRANT ALL PRIVILEGES ON employee_biometric_db.* TO 'YOUR_APP_USER'@'127.0.0.1';
```

These are local development/migration privileges on this database only. Production should separate migration and runtime identities and reduce runtime grants. Do not alter or reset an existing database/user just to match these example names.

## Laravel configuration

Copy `.env.example` to `.env` only if `.env` does not exist, then set your private values:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employee_biometric_db
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_private_password
```

The tracked example contains placeholders; the real local connection has already been configured. `.env` and `.tools/` are ignored by Git. Never print all of Laravel's database configuration into a shared report because it contains credentials.

Run from `C:\xampp\htdocs\Attendance_System`:

```powershell
& 'C:\xampp\php\php.exe' artisan config:clear
& 'C:\xampp\php\php.exe' artisan migrate
& 'C:\xampp\php\php.exe' artisan migrate:status
& 'C:\xampp\php\php.exe' -d extension=intl artisan db:show --counts
```

Migration status and `db:show` verify Laravel's PDO connection. Only framework `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, and migration metadata belong to this phase. No default login is seeded.

## Tests and isolation

PHP feature tests use a separate SQLite in-memory database configured by `phpunit.xml`; `RefreshDatabase` does not reset the configured development MariaDB. Real Edge browser tests use the local server and a uniquely named temporary synthetic login, then remove only that login and its sessions. Never point browser fixtures at production. MariaDB connectivity/migrations and actual browser login are verified separately from the SQLite feature suite. MySQL concurrency/domain tests are deferred with their features.

## Common problems

| Error | Check / resolution |
| --- | --- |
| `2002` / connection refused | Start the existing XAMPP database; verify host/port and port conflicts. Do not start a second process against the same data directory. |
| `1045` / access denied | Verify actual user/password and account host (`127.0.0.1` vs `localhost`); do not assume a blank administrator password. |
| `1049` / unknown database | Create the intended new DB through authorized administrative access; confirm spelling in `.env`. |
| `could not find driver` | Check the PHP executable and `php -m`; enable `pdo_mysql` in the PHP installation actually being used. |
| `sessions` / `cache` table missing | Run framework migrations after configuring the connection. |
| Old connection still used | `php artisan config:clear`; restart development server. |
| Invalid application key / unreadable encrypted session | Ensure the same `.env` key is used; do not regenerate an established key casually. Clear only disposable local sessions if appropriate. |
| MariaDB/MySQL version difference | Confirm engine compatibility with installed Laravel before schema changes; CI/production must intentionally target the selected engine. |

Backups, restore drills, production identities, retention, and domain migrations remain later-phase work. Do not use `migrate:fresh`, `db:wipe`, or destructive SQL against existing project data without explicit review.
