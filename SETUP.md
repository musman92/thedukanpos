# DukanPOS — local setup

One-time setup so you can run and verify the web app. After this, day-to-day you only start the servers.

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.2+ (with `pdo_mysql`) |
| Composer | 2.x |
| Node.js | 20+ |
| npm | 10+ |
| MySQL | 8.x (running locally) |

Default MySQL login assumed: user `root`, empty password. Change this in `.env` if yours differs.

---

## 1. Clone / open the project

```bash
cd /path/to/dukanpos
```

---

## 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

If `vendor/` is not installed yet, run `composer install` first, then `php artisan key:generate`.

Confirm these values in `.env` (already set in `.env.example`):

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dukanpos_landlord
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_CONNECTION=mysql
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

`SESSION_CONNECTION=mysql` must stay on the **landlord** DB (sessions are not per-tenant).

---

## 3. Create landlord database

In MySQL:

```sql
CREATE DATABASE IF NOT EXISTS dukanpos_landlord
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Or from the shell (if `mysql` CLI is available):

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS dukanpos_landlord CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Tenant databases (e.g. `dukanpos_tenant_shop1`) are created automatically when you provision a shop.

---

## 4. Install dependencies

```bash
composer install
npm install
```

---

## 5. Run landlord migrations

```bash
php artisan migrate
```

---

## 6. Create the demo shop (tenant)

```bash
php artisan dukan:create-tenant shop1 --name="Demo Shop" --password=password
```

This creates:

- Tenant code: `shop1`
- DB: `dukanpos_tenant_shop1`
- User: `admin` (login as `admin@shop1`)
- Password: `password`
- Default branch, units, taxes, money sources

If the tenant already exists, skip this step. To apply new tenant migrations later:

```bash
php artisan tenants:migrate
```

Create more shops from the UI at `/platform/tenants`, or with the same artisan command and a different code.

---

## 7. Build frontend assets

**Option A — production build (simple)**

```bash
npm run build
```

Then you only need `php artisan serve`.

**Option B — Vite HMR while developing**

```bash
npm run dev
```

Keep that terminal open, and run `php artisan serve` in another.

---

## 8. Start the app

```bash
php artisan serve
```

Open: [http://localhost:8000](http://localhost:8000)

### Login

| Field | Value |
|-------|--------|
| Login | `admin@shop1` |
| Password | `password` |

Useful URLs:

| URL | What |
|-----|------|
| `/login` | Shop sign in (`admin@shop1`) |
| `/` | POS |
| `/admin` | Tenant admin |
| `/platform/login` | SaaS platform (landlord) |

### Platform operator (optional)

```bash
php artisan dukan:seed-platform
# email: admin@dukanpos.test  password: password
```

Then open `/platform/login` for tenant create, billing, invoices, and support-login links.

After pulling new code, also run:

```bash
php artisan migrate
php artisan tenants:migrate
npm run build
```

---

## Day-to-day (after setup)

You do **not** need to re-run migrations or recreate the tenant every time.

```bash
# Terminal 1
php artisan serve

# Terminal 2 (only if you want live JS/CSS reload)
npm run dev
```

If you only use `npm run build`, terminal 2 is optional until you change frontend code again.

---

## Quick smoke check

1. Login as `admin@shop1`
2. Admin → **Shifts** → open a shift
3. Admin → **Products** → create a product (+ variant)
4. Admin → **Purchases** → receive stock
5. Open **POS** (`/`) → sell → receipt
6. Optional: customer + credit sale; Admin → Customers → receive payment
7. Optional: returns, adjustments, transfers, sales report, settings

---

## Common fixes

| Problem | Fix |
|---------|-----|
| MySQL connection refused | Start MySQL; check `DB_*` in `.env` |
| Shop code not found | Run `dukan:create-tenant shop1 ...` again (or check code spelling) |
| Blank page after login | Vite/JS not loading — run `php artisan config:clear`, hard refresh. Confirm `tenancy.asset_helper_tenancy` is `false` |
| `Tenant::domains()` / tenancy assets error | Domain tenancy is disabled; clear config cache |
| Session / login loops | Confirm `SESSION_CONNECTION=mysql` and landlord migrations ran |
| New tenant tables missing | `php artisan tenants:migrate` |
| Reset everything | Drop `dukanpos_landlord` + `dukanpos_tenant_*`, then repeat steps 3–7 |

---

## Optional: `setup.sh`

`setup.sh` automates the steps above. Prefer this **SETUP.md** for a clear, one-time manual setup. Use the script only if you want automation:

```bash
./setup.sh          # one-shot install
./setup.sh --fresh  # destructive reset (landlord migrate:fresh + recreate shop1)
```
