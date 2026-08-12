# DukanPOS

Multi-tenant **retail point of sale** and back office for shops that buy wholesale and sell retail (book shops, mobile accessories, general stores, and similar).

This document is the **product & architecture spec** for review. Nothing here is finalized until approved. Implementation starts after sign-off.

**Related reference:** FoodPOS (`/Users/usman/Sites/foodpos`) — restaurant POS, single-DB multi-tenancy. DukanPOS reuses domain ideas (shifts, purchases, stock, money sources, RBAC) but is a **greenfield** retail product with **database-per-tenant** tenancy.

---

## 1. Goals

| Goal | Decision |
|------|----------|
| Business type | Retail (wholesale buy → retail sell), not restaurants |
| Tenancy | **Multi-database** (one DB per shop/company) |
| Tenant resolution | **No subdomain** — login via `username@tenant_code` |
| Web app | **One Laravel app** — POS + Admin + API |
| Mobile | **Separate repo**, React Native, consumes same API |
| Default UI | `/` → POS; `/admin` → admin panel |
| Web UI | **React** for POS **and** admin (same Laravel app) |

---

## 2. Repository layout

| Repo | Contents |
|------|----------|
| **dukanpos** (this repo) | Laravel: API, POS UI, Admin UI, landlord + tenant DBs |
| **dukanpos-mobile** (separate, later) | React Native (Expo recommended) |

No monorepo for mobile. Web POS and admin are **React**, served from the Laravel app (Inertia — see §3).

```
dukanpos/
├── app/
├── database/
│   ├── migrations/           # landlord (central)
│   └── migrations/tenant/    # per-tenant schema
├── routes/
│   ├── web.php               # Inertia pages: POS + admin + platform
│   ├── api.php               # mobile + JSON API
│   └── platform.php          # optional; or group under web
├── resources/
│   ├── js/                   # React (POS, Admin, Platform pages)
│   │   ├── Pages/
│   │   │   ├── Pos/
│   │   │   ├── Admin/
│   │   │   └── Platform/
│   │   └── Components/       # shared UI kit
│   └── css/
└── README.md
```

---

## 3. Tech stack

| Layer | Choice |
|-------|--------|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Tenancy | `stancl/tenancy` (database-per-tenant) |
| Auth (web) | Session |
| Auth (API / mobile) | Laravel Sanctum |
| Permissions | Spatie Laravel Permission (roles inside **tenant** DB) |
| Landlord DB | MySQL/PostgreSQL — tenants, billing, login lookup |
| Tenant DB | MySQL/PostgreSQL — one database per shop |
| Cache / queue / session | **Redis** (not stored in tenant DB) |
| Frontend (web) | **React** via **Laravel Inertia** + Vite + Tailwind — POS, Admin, Platform |
| JS bundling | **Split builds / code-split chunks** — admin does not download POS bundle (see §3.1) |
| Mobile | React Native (separate repo) |

#### 3.1 Frontend code splitting (POS vs Admin)

Yes — we should **not** ship one giant JS file that includes POS + Admin + Platform on every visit.

**Recommended approach (two layers)**

1. **Separate Vite entry points** (hard split by surface)

| Entry | Loaded when | Contains |
|-------|-------------|----------|
| `resources/js/pos.jsx` | `/`, `/pos/*` | POS pages + cart/scanner deps |
| `resources/js/admin.jsx` | `/admin/*` | Admin CRUD, reports, tables |
| `resources/js/platform.jsx` | `/platform/*` | Tenant provisioning, billing |

Laravel Blade/Inertia root for each area only `@vite`’s that entry. Visiting `/admin` never downloads the POS scanner/cart bundle.

2. **Per-page lazy chunks inside each entry** (Inertia default)

```js
// resolve page → dynamic import
() => import(`./Pages/Admin/Products/Index`)
```

Vite then emits small chunks per page (product form, report X, etc.). First admin hit loads shell + that page only.

**Shared code:** a thin `resources/js/shared/` (Button, Input, api helpers, types). Vite puts shared modules into common chunks automatically when both entries import them — keep this small so “shared” does not become a second monolith.

**What this is not:** three separate git apps. Still one Laravel repo, one React design system, multiple **build outputs**.

```text
public/build/
  pos-xxxxx.js
  admin-xxxxx.js
  platform-xxxxx.js
  chunks/   # lazy pages + small shared
```

**Mobile:** unrelated — its own RN Metro/Expo bundle in the other repo.
| PDF / Excel | dompdf, PhpSpreadsheet (as needed) |
| Printing | Browser receipts first; optional desktop silent-print later (FoodPOS pattern) |

**Hosting (planned):** Coolify / Docker, similar to FoodPOS. Per-tenant DB provision + `tenants:migrate` on deploy.

---

## 4. Tenancy model

### 4.1 Hybrid: landlord + tenant databases

**Landlord (central) DB**

- Tenants / companies registry (`tenant_code`, name, status, DB connection name)
- Billing / platform invoices
- Secret login / support tokens
- Platform super-admin users
- Login lookup helpers (optional: email/username → tenant map)
- Tenant provisioning metadata

**Tenant DB** (e.g. `dukanpos_tenant_shop1_d9bdf96d`)

- Users, roles, permissions
- Branches
- Catalog, stock, purchases, sales
- Customers, suppliers
- Shifts, money sources, transactions
- Reports data, HR (phase 2+), settings, activity log

### 4.2 Tenant identification (no subdomain)

Login identity format:

```text
{username}@{tenant_code}

Examples:
  admin@shop1
  cashier@shop1
  admin@shop2
```

| Part | Meaning |
|------|---------|
| `username` | Unique **within** that tenant only. Default owner user: `admin` |
| `tenant_code` | Unique **platform-wide** slug (`shop1`, `bookmart`, `phonex`) |

**Rules**

- `tenant_code`: lowercase, `a-z`, `0-9`, hyphens; stable (renaming later is painful)
- Display name of the shop can change without changing `tenant_code`
- This is **not** a real email domain; optional contact email is a separate field (resets, receipts)
- Platform super admin uses a landlord-only login (e.g. `superadmin`) with **no** tenant suffix

**Login UX**

- One field: **Login** = `username@shopcode` (e.g. `admin@shop1`) + **Password**
- Mobile API accepts the same `{ login: "admin@shop1", password }` or split `{ username, tenant_code, password }`

### 4.3 Login flow (web)

1. User opens single domain login page (e.g. `dukanpos.com/login`).
2. Parse `username` + `tenant_code`.
3. Landlord: resolve tenant by `tenant_code` (must be active).
4. Initialize tenant database connection.
5. Authenticate `username` + password against tenant `users`.
6. On success, store in session: `tenant_id`, `user_id`, `branch_id` (or prompt branch pick).
7. Redirect by role: cashiers → `/` (POS); managers/owners → `/admin` or `/` (configurable).

### 4.4 Request flow after login

```text
Login → tenant fixed in session
              │
              ▼
     ┌────────────────────────┐
     │  Same tenant context   │
     ├────────────┬───────────┤
     │  /         │  /admin   │
     │  POS       │  Admin    │
     └────────────┴───────────┘
```

- Middleware: read `tenant_id` from session → `tenancy()->initialize(...)` → Auth + branch context.
- **`/` vs `/admin` does not select tenant** — only which UI/module.
- Switch shop = logout or explicit “switch company” → new login.

### 4.5 API / mobile auth

1. `POST /api/v1/login` with username + tenant_code + password.
2. Response: Sanctum token + user + branches + tenant meta.
3. Token created **while tenant DB is initialized** → one token = one tenant.
4. Subsequent requests: `Authorization: Bearer …` → resolve token → initialize that tenant → authorize.

### 4.6 Branch context

- Multi-branch shops: user may access multiple branches (`user_branches`).
- Effective `branch_id` from session (web) or `X-Branch-Id` / body (API), validated against membership.
- Stock, shifts, and many sales ops are branch-scoped.

---

## 5. Application surfaces

**UI decision:** POS and Admin are both **React** inside the same Laravel app. Do **not** mix Blade admin + React POS (two stacks, two design systems).

| Surface | Path | UI | Who |
|---------|------|----|-----|
| POS | `/` | React (Inertia) | Cashiers / sales |
| Tenant admin | `/admin/*` | React (Inertia) | Owner / manager |
| Platform | `/platform/*` | React (Inertia) | SaaS operator (you) |
| API | `/api/v1/*` | JSON | React Native + any client |

Shared React `Components/` (buttons, tables, forms, modals). Different **layouts**: fullscreen POS vs sidebar admin vs platform chrome.

**Why Inertia (not a detached SPA)?** Fits “one Laravel app”: session auth, tenant middleware, `/admin` routes stay server-defined; pages are React. Mobile still uses `/api/v1` (not Inertia).

### 5.1 POS (`/`)

Default app after login for cashiers.

- Product search by name, short code, barcode, brand
- Search results show stock + **section/rack** location
- Cart; optional qty in purchase unit when conversion ≠ 1; discounts (permission-gated); **per-line tax** from product tax
- Checkout: cash / card / transfer / credit / split pay
- Hold / recall sale (optional phase)
- Receipt print (browser 80mm)
- Requires **active shift** (same idea as FoodPOS)
- Branch-aware stock deduction

### 5.2 Admin (`/admin`)

Tenant back office for owners/managers.

- Dashboard
- Catalog, purchases, stock, customers, suppliers
- Users & roles
- Shifts / money / Z-report
- Sales history, returns
- Reports
- Company & receipt settings

Same session/tenant as POS. Access controlled by permissions (cashiers may be blocked from `/admin`).

### 5.3 Platform (`/platform`)

**Your** SaaS control plane — landlord DB only. Not the shop’s `/admin`.

- Create / suspend tenants
- Assign `tenant_code`, provision database, run tenant migrations, seed `admin@{code}`
- Billing / invoices
- Secret login / support access
- Platform-wide health (optional)

### 5.4 API (`/api/v1`)

JSON API for React Native and any future clients. Same domain services as web POS/admin.

**Auth**

| Method | Path | Notes |
|--------|------|-------|
| `POST` | `/api/v1/login` | Body: `login` + `password`, or `username` + `tenant_code` + `password` |
| `GET` | `/api/v1/me` | Requires `Authorization: Bearer …` + `X-Tenant-Code` |
| `POST` | `/api/v1/logout` | Revokes current token |

**Brands** (same rules as admin web: unique name/code, auto `B01`…, image compress, cannot delete if products exist)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/brands` | Query: `q`, `per_page`, `sort`, `direction` |
| `POST` | `/api/v1/brands` | JSON or multipart (`image` file) |
| `GET` | `/api/v1/brands/{id}` | |
| `PUT`/`PATCH`/`POST` | `/api/v1/brands/{id}` | `POST` allowed for multipart image upload |
| `DELETE` | `/api/v1/brands/{id}` | `422` if brand has products |
| `GET` | `/api/v1/media/{path}` | Authenticated image serve (`image_url` in brand JSON) |

**Categories** (parent nest + default tax; auto `C01`…; no images)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/categories` | Query: `q`, `per_page`, `sort`, `direction` |
| `POST` | `/api/v1/categories` | Body: `name`, optional `code`, `parent_id`, `default_tax_id`, `is_active` |
| `GET` | `/api/v1/categories/{id}` | |
| `PUT`/`PATCH` | `/api/v1/categories/{id}` | |
| `DELETE` | `/api/v1/categories/{id}` | `422` if products or child categories exist |

**Units** (labels like `pcs`/`kg`; auto `u01`…; no images)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/units` | Query: `q`, `per_page`, `sort`, `direction` |
| `POST` | `/api/v1/units` | Body: `name`, optional `code`, `is_active` |
| `GET` | `/api/v1/units/{id}` | |
| `PUT`/`PATCH` | `/api/v1/units/{id}` | |
| `DELETE` | `/api/v1/units/{id}` | `422` if used on products/variants |

**Variations** (types + nested options; auto `V01`…)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/variations` | Includes `options` |
| `POST` | `/api/v1/variations` | Body: `name`, optional `code`, `options[]` |
| `GET` | `/api/v1/variations/{id}` | |
| `PUT`/`PATCH` | `/api/v1/variations/{id}` | Syncs options (omit/empty clears) |
| `DELETE` | `/api/v1/variations/{id}` | Cascades options |

**Sections** (section + nested racks; auto `S01`…)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/sections` | Includes `racks` |
| `POST` | `/api/v1/sections` | Body: `name`, optional `code`, `racks[]` |
| `GET` | `/api/v1/sections/{id}` | |
| `PUT`/`PATCH` | `/api/v1/sections/{id}` | Syncs racks |
| `DELETE` | `/api/v1/sections/{id}` | `422` if used on product locations |

**Racks** (must belong to a section; auto `R01`… per section)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/racks` | Query: `q`, `section_id`, `per_page`, `sort`, `direction` |
| `POST` | `/api/v1/racks` | Body: `section_id`, `name`, optional `code`, `is_active` |
| `GET` | `/api/v1/racks/{id}` | |
| `PUT`/`PATCH` | `/api/v1/racks/{id}` | |
| `DELETE` | `/api/v1/racks/{id}` | `422` if used on product locations |

**Products** (parent + nested variants; locations/stock scoped by `branch_id`)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/products` | Query: `q`, `branch_id`, `per_page`, `sort`, `direction` |
| `POST` | `/api/v1/products` | Body: product fields + `variants[]` (min 1); optional `branch_id` for locations |
| `GET` | `/api/v1/products/{id}` | Includes variants, location & stock for branch |
| `PUT`/`PATCH` | `/api/v1/products/{id}` | Syncs variants |
| `DELETE` | `/api/v1/products/{id}` | `422` if stock or sales/purchases/history exist |

All protected routes need `Authorization: Bearer {token}` and `X-Tenant-Code: {shopcode}`.

---

## 6. Feature list

Status legend for planning: **P0** = MVP, **P1** = soon after MVP, **P2** = later.

### 6.1 Platform (landlord)

| Feature | Priority | Notes |
|---------|----------|-------|
| Tenant CRUD + `tenant_code` | P0 | Unique code, status active/suspended |
| Auto provision tenant DB + migrate + seed | P0 | Creates default `admin` user |
| Platform super-admin auth | P0 | |
| Secret / support login | P1 | Time-limited login as tenant admin |
| Billing / platform invoices | P1 | Port concept from FoodPOS |
| Demo tenant reset | P1 | Wipe/reseed one tenant DB |
| Per-tenant backup trigger | P2 | |

### 6.2 Tenant — org & access

| Feature | Priority | Notes |
|---------|----------|-------|
| Branches | P0 | Multi-location |
| Users (`username` unique per tenant) | P0 | Login as `user@tenant_code` |
| Roles & permissions | P0 | Admin, Manager, Cashier defaults |
| Branch assignment for users | P0 | |
| Company settings | P0 | Currency, timezone, tax defaults, receipt branding |
| Activity log | P1 | |

### 6.3 Catalog (retail)

#### Product master fields (P0)

Each sellable item supports:

| Field | Required | Notes |
|-------|----------|-------|
| Name | Yes | Display name |
| Short code | Yes | Fast POS entry (unique per tenant), e.g. `P001`, `COKE330` |
| Barcode | Optional | EAN/UPC; unique per tenant when set; scan-to-find |
| SKU | Optional | Internal / supplier SKU if different from short code |
| Brand | Optional | FK → brands master |
| Base unit | Yes | = **sale / stock unit** (see §6.3.3); dual purchase unit + conversion_rate |
| Purchase unit | Yes | How supplier sells (may equal sale unit) |
| Conversion rate | Yes | Sale units per 1 purchase unit (default `1`) |
| Category | Optional | FK → categories (tree) |
| Tax | Yes* | FK → tax rate applied on this product (*or explicit tax-exempt) |
| Sale price | Yes | Default price **per base unit** (e.g. per pc); optional pack prices via unit conversions |
| Cost | Yes | Average cost **per base unit** (from purchases / WAC) |
| Section + Rack | Optional | Physical location in the shop (see below) |
| Active | Yes | Soft-hide from POS without deleting |

**Variants (P0):** size / color / storage, etc. Variant may override barcode, short code, price, and stock; brand/tax/location typically inherit from parent unless we allow override later. Unit conversions can be product-level or variant-level (same factor for simple SKUs).

#### Supporting masters (P0)

**Implementation standard:** Brands is the template module. Clone its stack (Service + thin Admin/API controllers + FormRequests + Inertia Index/drawers + optional image/import) for Categories, Units, Taxes, etc. See `.cursor/rules/catalog-master-module.mdc`.

| Feature | Priority | Notes |
|---------|----------|-------|
| Brands | P0 | **Reference master** — list/drawer CRUD, images, import/export, `/api/v1/brands` |
| Categories (tree) | P0 | Same stack as Brands — parent nest, default tax, import/export, `/api/v1/categories` |
| Units of measure | P0 | Same stack — codes like `pcs`/`kg`, import/export, `/api/v1/units` |
| Variations (types + options) | P0 | Size/Color masters + options; product SKUs still `product_variants` |
| Sections & racks | P0 | Sections module + dedicated Racks module (`/api/v1/sections`, `/api/v1/racks`) |
| Dual units on product | P0 | purchase unit + sale unit + conversion_rate — §6.3.3 |
| Taxes (flexible) | P0 | Multiple tax definitions; **assigned per product** (see §6.3.1) |
| Barcode / short-code / name search | P0 | POS + admin; location shown in search results |
| Product import / export | P1 | Spreadsheet including brand, units, tax, section, rack |
| Bundles / kits | P1 | Sell multiple SKUs as one |
| Serial / IMEI tracking | P1 | Flag per product (phones, accessories) |
| Images | P1 | |

#### 6.3.1 Flexible tax (per product)

Retail needs different rates on different goods (e.g. food vs perfume vs electronics).

**Model**

1. Tenant maintains a **taxes** list: name, rate %, inclusive/exclusive flag, active.
2. Each **product** has `tax_id` (or tax-exempt).
3. At checkout, line tax is computed from **that product’s tax**, not a single shop-wide rate.
4. Category may suggest a **default tax** when creating a product (convenience only); saving still stores tax on the product so it can differ from siblings.
5. Sale/receipt lines store **tax snapshot** (name + rate + amount) so historical invoices stay correct if rates change later.

Examples: `GST Food 5%`, `GST Cosmetics 18%`, `Exempt 0%` — assign per item.

#### 6.3.2 Section & rack (item location)

So cashiers/stock staff can find goods quickly when searching.

| Entity | Notes |
|--------|-------|
| Section | Area of the shop/warehouse (e.g. Aisle A, Cosmetics, Cold room) |
| Rack | Shelf/bin within a section (e.g. R3, Top-2); belongs to a section |

- Product (or variant, if we stock variants separately) links to optional `section_id` + `rack_id`.
- Location is **per branch** if the same SKU sits in different places per store (recommended for multi-branch): `branch_id` + product + section + rack.
- POS/admin search results show e.g. **Cosmetics → Rack R3** next to name/stock.

#### 6.3.3 Purchase unit vs sale unit (same pattern as FoodPOS ingredients)

**Adopt FoodPOS’s dual-unit model** — already proven there for ingredients (and menu items). Flexible, easy to teach staff who know FoodPOS.

**On each product**

| Field | Meaning | Lays example |
|-------|---------|--------------|
| `purchase_unit_id` | How you buy from supplier | Carton |
| `sale_unit_id` (stock / base) | How you sell & how stock is counted | Pcs |
| `conversion_rate` | **Sale units in 1 purchase unit** | `24` |
| Purchase price | Price per purchase unit | 1,200 / carton |
| Cost per sale unit | `purchase_price ÷ conversion_rate` | 50 / pc |
| Sale price | Price per sale unit | 60 / pc |

FoodPOS names the stock side **consumption unit**; for retail we call it **sale unit** (same role: stock is always in this unit).

**Formula** (identical to FoodPOS `IngredientQuantity`)

```text
stock_qty     = purchase_qty × conversion_rate
purchase_qty  = stock_qty ÷ conversion_rate
cost_per_sale = purchase_price ÷ conversion_rate
```

**Purchase:** enter `2 carton` → stock `+48 pcs`, WAC uses cost per pc.  
**Sale:** enter `3 pcs` → stock `-3 pcs`.  
**Same unit both sides:** `conversion_rate = 1` (buy pcs, sell pcs).

**POS / adjustments:** allow qty in sale unit by default; optionally allow purchase unit when rate ≠ 1 (FoodPOS already converts recipe lines in either unit — same idea for POS “sell 1 carton”).

**Unit master:** named labels only (`pcs`, `carton`, `kg`) — **no** conversion on the unit itself. The factor lives on the **product** (per-SKU), because 1 carton of Lays ≠ 1 carton of juice.

**Why prefer this over a multi-pack conversion table (MVP)**

| Dual unit (FoodPOS-style) | Multi-level packs table |
|---------------------------|-------------------------|
| 2 fields + 1 rate — simple UI | Extra table, more edge cases |
| Covers 95% of retail (carton↔pc, box↔pc, kg↔g) | Needed for pallet→carton→pc chains |
| Team already knows it from FoodPOS | New mental model |

**P2 later (only if needed):** extra pack levels or multiple sale packs with separate barcodes. Not required for MVP.

**What we still avoid:** two stock counters (carton stock + pc stock) for one SKU.

#### 6.3.4 Supplier offers / free (bonus) quantity on purchase

**Problem:** Supplier offer — “buy 1 carton Lays, get 1 pc free.” You pay for 24 pcs worth, but receive **25 pcs**. Pack size on the product stays `24`; do **not** change `conversion_rate`.

**Handle on the purchase line**, not on the product master.

| Field on purchase line | Example |
|------------------------|---------|
| Qty (purchase unit) | `1` carton |
| Unit price | `1,200` (for the paid carton) |
| **Bonus qty** | `1` (in **sale units** by default — clearest for “+1 pc”) |
| Bonus unit | sale unit (`pcs`), or allow purchase unit if they give a free carton |

**Stock & cost**

```text
Paid stock   = 1 × 24 = 24 pcs
Bonus stock  = 1 pc
Total stock += 25 pcs

Line amount  = 1 × 1,200 = 1,200   (bonus is free — no extra payable)
Cost per pc  = 1,200 ÷ 25 = 48     (WAC improves — correct)
```

**Rules**

1. `conversion_rate` on product remains the real pack size (`24`).
2. Bonus increases **received qty only**, not supplier payable amount.
3. WAC / batch cost spreads paid amount over **paid + bonus** qty in sale units (standard retail accounting).
4. Purchase document / PDF can show: `1 carton + 1 pc FOC`.
5. Optional note field: “Ramadan scheme”, “dealer scheme”, etc.
6. Returns: decide policy later (return bonus proportionally or paid qty only) — default P1: return against original line with max = received including bonus.

**UI (purchase form)**

```text
Product: Lays | Qty: 1 | Unit: Carton | Price: 1200
Bonus:   1    | Unit: Pcs     (optional)
→ Receiving 25 pcs | Line total 1,200
```

**Not for MVP:** complex schemes (“buy 10 cartons get 1 carton free” as a separate promotion engine). Entering bonus qty manually on the line covers real shop use. Scheme templates can be P2.

**Out of core (FoodPOS-only):** kitchen, tables, floors, recipes/BOM ingredient deduction, cuisines, dine-in/delivery channels, waiters/riders. Optional BOM/kits only if needed later for assembly businesses.

### 6.4 Purchasing & suppliers

| Feature | Priority | Notes |
|---------|----------|-------|
| Suppliers | P0 | |
| Purchases (GRN) | P0 | Qty in purchase unit → × conversion_rate → stock in sale unit |
| Unit on purchase line | P0 | Defaults to product purchase unit |
| **Bonus / FOC qty on line** | P0 | Free units received; cost spread over total — §6.3.4 |
| Weighted average cost (WAC) | P0 | Always per **sale unit**; includes bonus in divisor |
| Supplier payments | P0 | Payable = sum of line amounts (bonus not billed) |
| Purchase returns | P1 | Same unit + bonus awareness |
| Purchase orders (ordered ≠ received) | P2 | |

### 6.5 Inventory

| Feature | Priority | Notes |
|---------|----------|-------|
| Branch stock levels | P0 | Qty in **sale units**; UI may show “≈ N purchase units” |
| Stock movements ledger | P0 | Primary qty in sale units |
| Adjustments | P0 | Sale unit, or purchase unit when dual (FoodPOS-style) |
| Low stock alerts | P1 | Threshold in sale units |
| Branch transfers | P1 | |
| Stock valuation report | P1 | qty_sale × cost_per_sale_unit |

### 6.6 Customers & credit

| Feature | Priority | Notes |
|---------|----------|-------|
| Customers | P0 | |
| Credit sales / customer balance | P0 | |
| Customer payments | P0 | |
| Account statement | P1 | |
| Import / export | P1 | |

### 6.7 POS & sales

| Feature | Priority | Notes |
|---------|----------|-------|
| POS checkout | P0 | |
| Split payments | P0 | |
| Credit / FOC (permission) | P0 | |
| Sales returns / refunds | P0 | |
| Exchanges | P1 | |
| Held sales / layaway | P1 | |
| Quotes | P2 | |
| Coupons / promotions | P2 | |

### 6.8 Cash & shifts

| Feature | Priority | Notes |
|---------|----------|-------|
| Shifts (open/close) | P0 | Gate POS when no active shift |
| Money sources (cash, bank, apps) | P0 | |
| Transfers / reconcile | P0 | |
| Owner withdrawal | P1 | |
| Z-report | P0 | |

### 6.9 Reports

| Feature | Priority | Notes |
|---------|----------|-------|
| Sales report | P0 | |
| Payment methods | P0 | |
| Gross margin | P1 | |
| AR / AP | P1 | |
| Stock / inventory reports | P1 | |
| P&L (simple) | P1 | |
| PDF / Excel export | P1 | |
| Period closing | P2 | |

### 6.10 HR / payroll

| Feature | Priority | Notes |
|---------|----------|-------|
| Employees, attendance, payroll | P2 | Port from FoodPOS when needed |

### 6.11 Mobile app (separate repo)

| Feature | Priority | Notes |
|---------|----------|-------|
| Login (`user@tenant` or split fields) | P0 | |
| Branch select | P0 | |
| POS sale flow | P0 | |
| Shift open/close | P0 | |
| Receipt / share | P1 | |
| Stock lookup | P1 | |
| Today’s sales summary | P1 | |
| Offline draft sync | P2 | |

Heavy admin (catalog setup, complex reports) stays on **web `/admin`**.

---

## 7. Roles & permissions (defaults)

Seeded per tenant (names adjustable):

| Role | Typical access |
|------|----------------|
| Administrator | Full tenant admin + POS |
| Manager | Catalog, purchases, stock, reports, POS |
| Cashier | POS, limited returns, own shift |

Permissions follow `{module}.{action}` style (e.g. `products.create`, `pos.checkout`, `reports.view`), synced via artisan command (FoodPOS pattern).

Platform modules (`tenants`, billing) are **not** assigned to tenant roles.

---

## 8. What we take from FoodPOS vs what we drop

### Reuse as concepts / logic

- Company ≈ Tenant, multi-branch
- Shifts + money sources + Z-report
- Purchases, supplier payments, WAC-style costing
- Customers / suppliers / credit
- Spatie RBAC patterns
- Platform billing + secret login ideas
- Reports mindset (sales, margin, AR/AP)
- Desktop print bridge — optional later

### Do not bring into core

- Single shared DB + `company_id` scopes as primary isolation
- Kitchen / KOT / KDS / tables / floors
- Recipes → ingredient auto-deduction as default sale path
- Waiter / rider account types
- Food order channels (dine-in / takeaway / delivery) as core
- Blade-only unofficial POS JSON as the mobile contract — mobile gets a real `/api/v1`

### Important lesson from FoodPOS

Docs there described API-first / Livewire aspirations; production is session/Blade with almost no product API. **DukanPOS:** React (Inertia) for web + first-class `/api/v1` for mobile.

---

## 9. Implementation phases

### Phase 0 — Foundations

- [x] Laravel 12 app scaffold in this repo
- [x] `stancl/tenancy`: landlord + tenant migration folders
- [ ] Redis for cache / queue / session _(local uses file/sync + sqlite sessions for now)_
- [x] Tenant provision: create DB → migrate → seed roles + `admin`
- [x] Login: single field `username@tenant_code` (e.g. `admin@shop1`)
- [x] Middleware: session tenant init
- [x] Inertia + React + Tailwind scaffold; layouts for Pos / Admin / Platform
- [x] Split Vite entries (`auth` / `pos` / `admin` / `platform`)
- [x] Route split: `/` POS, `/admin/*` admin, `/platform/*` platform
- [x] Sanctum login API stub for mobile (`POST /api/v1/login`)
- [x] Brands CRUD API (`/api/v1/brands` + authenticated media)
- [x] Categories module (web + `/api/v1/categories`, import/export)
- [x] Units module (web + `/api/v1/units`, import/export)
- [x] Variations module (types + options, web + `/api/v1/variations`)
- [x] Sections module (section + racks, web + `/api/v1/sections`)
- [x] Racks module (web + `/api/v1/racks`, filter by section)
- [x] Products module (service + FormRequests, web form, `/api/v1/products`, import/export)

### Quick start (local)

Follow **[SETUP.md](./SETUP.md)** for the full one-time setup (env, MySQL, migrate, demo shop, build).

After setup, day-to-day:

```bash
php artisan serve
# optional while editing UI:
npm run dev
```

- Login: http://localhost:8000/login → `admin@shop1` / `password`
- Admin: `/admin`
- Platform: `/platform/login` → `admin@dukanpos.test` / `password` (after `php artisan dukan:seed-platform`)

Optional automation (same steps as SETUP.md): `./setup.sh`

### Phase 1 — MVP retail loop

**Done (core buy → stock → sell)**

- [x] Branches, users, roles _(users/roles from Phase 0; branch assignment seeded)_
- [x] Brands, units, categories, taxes (multi-rate)
- [x] Products: short code, barcode, brand, purchase+sale unit, conversion_rate, per-product tax
- [x] Sections & racks; per-branch product location; show on search
- [x] Variants _(sizes/SKUs under a product; stock & barcode per variant)_
- [x] Suppliers, purchases (purchase qty × rate → stock), bonus/FOC qty, WAC per sale unit
- [x] Customers _(list/create only)_
- [x] Shifts, money sources
- [x] POS checkout + receipts _(cash pay)_
- [x] Basic Z on shift close _(expected vs closing cash)_

**Deferred on purpose (Phase 1b — not blocked except as noted)**

| Item | Reason deferred | Dependency? |
|------|-----------------|-------------|
| Sales report screen | Prioritized sell loop; data already in DB | **Done in Phase 2** |
| Company settings / receipt branding | Polish; basic receipt works | **Done in Phase 2** |
| Credit sales on POS | Cash path first | **Done in Phase 2** |
| Basic sales returns | Avoid wrong stock/money reverse before sales stable | **Done in Phase 2** |

### Phase 2 — Harden & expand

- [x] Purchase returns, stock transfers, adjustments UX
- [x] Serial/IMEI module (flag on variants + serial import/list)
- [x] Sales report + product sales + CSV export
- [x] Sales returns, company settings / receipt branding
- [x] Credit sales (customer balance) + receive payment
- [x] Platform billing + secret (support) login
- [x] Import/export spreadsheets (products + customers CSV)
- [x] Activity log
- [x] Settings tabs (company, preferences, POS, receipt) + branches, users, roles & permissions
- [x] Financials (accounts, money sources/transfers, ledger transactions, supplier & employee payments)
- [x] HR (employees, attendance, leave, payroll, bonuses/deductions)
- [x] Reports hub (daily sales, payment methods, stock, receivables/payables, margin, P&L, Z)
- [x] Inventory stock-on-hand view (retail-relevant; no kitchen/recipes)

### Phase 3 — Mobile repo _(after web verification)_

- [ ] React Native app against `/api/v1`
- [ ] Login, shift, POS, receipts
- [ ] Stock lookup / daily summary

### Phase 4 — Optional

- [ ] Promotions, quotes, offline sync
- [ ] Silent desktop printing
- [ ] Deeper FoodPOS parity (tax matrix UI, advanced payroll rules)

---

## 10. Open decisions (review checklist)

Mark each when finalized:

| # | Topic | Current proposal | Your decision |
|---|--------|------------------|---------------|
| 1 | Web UI | React (Inertia) for **POS + Admin + Platform** | ☐ OK ☐ Change: ___ |
| 1b | Admin UI | Same React app as POS (`/admin/*`) — not Blade/Filament | ☐ OK ☐ Blade admin |
| 1c | JS bundles | Separate Vite entries: `pos` / `admin` / `platform` + lazy pages | ☐ OK ☐ Single bundle |
| 2 | Costing method | Weighted average cost (WAC) for MVP | ☐ OK ☐ Batch/FIFO |
| 3 | Login UX | Single `user@code` field (`admin@shop1`) | ☐ OK ☐ Change: ___ |
| 4 | Post-login redirect | Role-based (`cashier`→POS, else admin) | ☐ OK ☐ Always POS |
| 5 | Mobile repo name | `dukanpos-mobile` | ☐ OK ☐ ___ |
| 6 | DB engine (prod) | PostgreSQL preferred (or MySQL) | ☐ PG ☐ MySQL |
| 7 | Serial/IMEI | Phase 2 module | ☐ P1 ☐ P2 ☐ Skip |
| 8 | HR | Phase 4 / P2 | ☐ OK ☐ Earlier |
| 9 | Platform path | `/platform` | ☐ OK ☐ ___ |
| 10 | Product name / branding | DukanPOS | ☐ OK ☐ ___ |
| 11 | Tax assignment | Per product (+ optional category default on create) | ☐ OK ☐ Change: ___ |
| 12 | Section/rack scope | Per branch (same SKU, different location per store) | ☐ OK ☐ Global per tenant |
| 13 | Short code | Required, unique per tenant | ☐ OK ☐ Optional |
| 14 | Buy/sell units | FoodPOS-style: purchase unit + sale unit + conversion_rate | ☐ OK ☐ Change: ___ |
| 15 | Sell in purchase unit on POS | Allow when rate ≠ 1 (e.g. sell whole carton) | ☐ Yes ☐ Sale-unit only MVP |
| 16 | Purchase bonus / FOC | Bonus qty on purchase line; WAC over paid+free | ☐ OK ☐ Change: ___ |

---

## 11. Non-goals (for now)

- Subdomain or custom-domain tenant routing
- Converting FoodPOS codebase in place
- Kitchen / restaurant workflows
- Payment gateway integrations (Stripe etc.) — record method via money sources only
- Full accounting package (double-entry) — operational AR/AP + simple P&L only
- Shared UI package between Laravel and React Native

---

## 12. Success criteria for MVP

1. Create tenant `shop1` from platform → DB provisioned → login `admin@shop1`.
2. Add product with purchase unit carton, sale unit pcs, conversion_rate 24; brand, short code, barcode, tax, section/rack.
3. Purchase 1 carton + 1 pc bonus → stock +25 pcs, payable 1,200, WAC uses 1,200÷25.
4. POS search finds item and shows location; sell in pcs (optional: carton); correct line tax; stock decreases; receipt prints.
5. Two products with different tax rates on one bill compute tax correctly per line.
6. View sale in `/admin`, run Z-report on shift close.
7. Second tenant `shop2` fully isolated (no data leak).
8. Mobile (or API client) can login and create a sale via `/api/v1`.

---

## 13. Next step after review

1. You annotate **§10 Open decisions** and any feature priority changes.
2. We lock this README as v1 spec.
3. Start **Phase 0** scaffold in this repository.

---

*Spec version: 0.9 (Phase 1 MVP loop in progress) — 2026-07-28*
