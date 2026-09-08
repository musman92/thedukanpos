# DukanPOS — project map

Multi-tenant **retail POS + back office** (wholesale buy → retail sell). One Laravel app serves POS, Admin, Platform, and JSON API. Tenancy is **database-per-tenant** (`stancl/tenancy`). Login is `username@tenant_code` (no subdomains). Mobile is a separate repo; this app exposes Sanctum API for it.

Stack: Laravel 12, Inertia + React + Vite + Tailwind, Spatie Permission (tenant DB), DomPDF, Redis for cache/queue/session.

## Folder structure

| Path | Role |
|------|------|
| `app/Models/` | Eloquent models (tenant + landlord) |
| `app/Services/` | Business logic (controllers stay thin) |
| `app/Http/Controllers/Admin/` | Inertia admin CRUD |
| `app/Http/Controllers/Api/V1/` | Mobile/JSON API |
| `app/Http/Controllers/Platform/` | Landlord tenant ops |
| `app/Http/Requests/Admin/` | FormRequests (API reuses these) |
| `app/Http/Resources/Api/V1/` | API transformers |
| `app/Support/` | Helpers, permissions, shared constants |
| `addons/` | Optional feature packs (`shifts`, `installments`, `_template`) |
| `database/migrations/` | Landlord (central) schema |
| `database/migrations/tenant/` | Per-tenant schema |
| `routes/web.php` | POS + admin Inertia routes |
| `routes/api.php` | `/api/v1/*` Sanctum + `X-Tenant-Code` |
| `resources/js/Pages/Pos/` | Cashier UI |
| `resources/js/Pages/Admin/` | Back-office pages (drawers + tables) |
| `resources/js/Pages/Platform/` | Super-admin (tenants, backups, addons) |
| `resources/js/Layouts/` | `AdminLayout`, `PlatformLayout`, etc. |
| `resources/js/Components/` | Shared UI (Drawer, Pagination, …) |
| `resources/views/pdf/` | Invoice / report / quotation Blade PDFs |
| `.cursor/rules/` | Agent conventions (nav naming, catalog masters) |

## Main features & flows

1. **Auth / tenancy** — resolve tenant from login or `X-Tenant-Code`; session for web, Sanctum for API.
2. **POS** — `/pos` sell, park, void today, deliveries, receipt; checkout via `SaleService`.
3. **Catalog** — brands, categories, units, variations, sections, racks, products (masters mirror Brands).
4. **Purchases & stock** — purchases, purchase returns, transfers, stock on hand, serials.
5. **Sales & docs** — orders (admin edit/void), sale returns, quotations; print POS receipt or PDF invoice.
6. **People** — customers, suppliers, users (login + optional employee).
7. **Money** — money sources, customer/supplier payments, expenses, owner withdrawals, accounts.
8. **HR** — attendance, leaves, payroll adjustments, employee payments (addon-aware).
9. **Reports** — hub + PDF export; sales bucketed by `business_date`.
10. **Platform** — create tenants, reset selective data, DB backup/restore, provision addons.
11. **Settings / RBAC** — company settings, roles & permissions, branches, shifts (when addon on).

## Important files (~15)

| File | Why |
|------|-----|
| `README.md` | Product & architecture spec |
| `routes/web.php` | POS + admin route map |
| `routes/api.php` | API surface |
| `config/tenancy.php` | Tenancy wiring |
| `app/Http/Middleware/InitializeTenancyBySession.php` | Web tenant boot |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared Inertia props |
| `app/Services/SaleService.php` | Sales create/update/void, stock, credit |
| `app/Services/PurchaseService.php` | Purchases + stock in |
| `app/Services/ProductService.php` | Products / variants |
| `app/Services/BrandService.php` | **Template** for catalog masters |
| `app/Http/Controllers/PosController.php` | POS endpoints |
| `app/Http/Controllers/Admin/OrderController.php` | Admin orders CRUD |
| `resources/js/Layouts/AdminLayout.jsx` | Admin nav |
| `resources/js/Pages/Pos/Index.jsx` | POS shell |
| `resources/js/Pages/Admin/Brands/Index.jsx` | Catalog UI template |
| `.cursor/rules/catalog-master-module.mdc` | How to add masters |
| `.cursor/rules/admin-nav-naming.mdc` | Plural vs singular nav labels |

## Conventions

- **Thin controllers** → call a **Service** for writes; Admin + API share one service.
- **Catalog masters** → copy Brands end-to-end (model, service, requests, Inertia drawer Index, API resource). Do not extend `CatalogMasterController` / mega-page for new modules.
- **Routes** — `admin.{plural}.*`, `api.v1.{plural}.*`; pages under `Admin/{Plural}/`.
- **List contract** — filters `q`, `per_page`, `sort`, `direction`; default sort `id` desc.
- **UI** — Inertia Index + form drawer (not separate create/edit pages for masters); shared `Drawer`, `SortableTh`, `Pagination`, `confirmDelete`.
- **Nav labels** — entity lists plural (`Brands`, `Orders`); tools/topics singular (`Dashboard`, `Inventory`).
- **Migrations** — landlord in `database/migrations/`; tenant in `database/migrations/tenant/`.
- **Addons** — feature-flagged packs under `addons/`; gate UI/routes when disabled.
- **No soft deletes** on catalog masters by default; no inventing parallel CRUD styles.
