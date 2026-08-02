# DukanPOS Addons

WordPress-style plugins that live **in this same repo**, under `addons/{slug}/`.

Core POS/admin must keep working with **zero addons active**. An addon may be installed or removed **per tenant** without editing core feature code.

**Who manages addons**

| Actor | Can install / remove? |
|-------|------------------------|
| **Platform admin** | Yes — on the tenant **Show** screen → **Addons** tab |
| **Tenant (company) admin** | **No** — they only *use* addons that platform installed |

> Status: catalog + platform Install/Remove UI are in place (central `tenant_addons` table). Loading active addon providers inside the tenant app, migrations on install, and Installments features come next.

---

## Goals

| Goal | Meaning |
|------|---------|
| Same repo | Addon source ships with the app (`addons/`) |
| Optional | Deleting or deactivating an addon never breaks sales, stock, customers, etc. |
| Tenant-scoped | Install/activate state is per company (tenant DB) |
| Platform-gated (later) | Central catalog can allow/deny which tenants may use which addons |
| Hooks, not forks | Core exposes extension points; addons listen / register — core does not `if ($addon === '…')` |

---

## Mental model (WordPress → DukanPOS)

| WordPress | DukanPOS |
|-----------|----------|
| `wp-content/plugins/{plugin}` | `addons/{slug}/` |
| Plugin header / bootstrap | `addon.json` + `*ServiceProvider` |
| Activate / Deactivate | Per-tenant status: `installed` → `active` / `inactive` |
| Uninstall | Drop addon migrations + clear addon settings (optional data wipe) |
| Hooks / filters | Laravel events + nav/route/permission registries |
| Network activate | Platform allow-list / plan (future) |

---

## Lifecycle (platform-controlled)

```text
Discover (scan addons/*/addon.json)
    → Platform Install on tenant (record in central tenant_addons + run addon migrations later)
    → Tenant uses feature (when runtime loader is wired)
    → Platform Remove (uninstall hooks + delete tenant_addons row)
```

There is **no** tenant-facing “Plugins” screen for install/remove.

**Install** (platform) — marks the addon installed/active for that company on the central DB. Later: run that addon’s tenant migrations inside the company database.

**Remove** (platform) — removes the provision record. Later: drop addon tables / clear addon settings for that tenant. Core tables stay untouched.

**Use** (tenant) — once runtime loading exists, only addons with an active `tenant_addons` row boot routes/nav/listeners for that company.

---

## Folder layout

```text
addons/
  README.md                 ← this file
  _template/                ← copy this to start a new addon
  installments/             ← first real addon (features TBD)
    addon.json
    README.md
    src/
      Providers/
      Http/
      Models/
      Services/
      Listeners/
    database/migrations/    ← tenant migrations owned by this addon only
    routes/
      admin.php
      pos.php                 ← optional
    resources/js/Pages/
    lang/
      en.json
```

Copy `_template` → `addons/{slug}`, replace placeholders, add a Composer PSR-4 entry (see below).

---

## `addon.json` contract

```json
{
  "slug": "installments",
  "name": "Installments",
  "version": "0.1.0",
  "description": "Short summary for the Addons screen.",
  "requires_core": ">=1.0.0",
  "provider": "Addons\\Installments\\Providers\\InstallmentsServiceProvider",
  "permissions": [
    "installments.view",
    "installments.manage"
  ],
  "nav": [
    {
      "label": "Installments",
      "route": "admin.installments.index",
      "group": "sales",
      "permission": "installments.view"
    }
  ]
}
```

| Field | Purpose |
|-------|---------|
| `slug` | Unique id; folder name should match |
| `provider` | Laravel service provider class |
| `permissions` | Declared when active; merged into roles UI |
| `nav` | Sidebar items registered only while active |

Settings for an addon should live under a namespaced key, e.g. `addons.{slug}.*`, never mixed into core company settings keys.

---

## How core stays clean

1. **Registry** — Core asks `Addon::active('installments')` (API TBD). It does not import addon controllers.
2. **Nav** — Sidebar = core links + items from active addons’ `nav`.
3. **Routes** — Only **active** addons load `routes/admin.php` / `routes/pos.php`.
4. **Permissions** — Addon-declared permissions appear only when the addon is installed/active.
5. **Events** — Core dispatches domain events (`SaleCompleted`, etc.). Addons listen. Prefer new events over editing core sale logic.
6. **Migrations** — Addon tables live under `addons/{slug}/database/migrations`. They are **not** mixed into core `database/migrations/tenant` permanently in a way that uninstall cannot reverse.

Rule of thumb: if you delete `addons/installments`, the app still boots; that feature is simply missing.

---

## State storage

**Catalog** — filesystem: `addons/*/addon.json` (same repo).

**Central DB** — `tenant_addons`:

| Column | Notes |
|--------|--------|
| `tenant_id` | Company (Stancl tenant id) |
| `slug` | Matches `addon.json` |
| `status` | `active` / `inactive` |
| `version` | From manifest at install time |
| `installed_at` / `activated_at` | Audit |

**Tenant DB** — only the addon’s own feature tables (created on Install when migration hooks are wired). No tenant-facing install UI writes here for provisioning.

**Platform UI** — `Platform → Tenants → {company} → Addons` tab (`AddonProvisionService`).

---

## Composer autoload

Each addon registers its own PSR-4 namespace in the **root** `composer.json`:

```json
"autoload": {
  "psr-4": {
    "App\\": "app/",
    "Addons\\Installments\\": "addons/installments/src/"
  }
}
```

After adding an addon:

```bash
composer dump-autoload
```

Do **not** autoload `addons/_template` — it is a cookie-cutter only.

---

## Creating a new addon

1. Copy `addons/_template` → `addons/{slug}`.
2. Rename placeholders (`YourAddon`, `your-addon`, etc.) — see `_template/README.md`.
3. Fill `addon.json`.
4. Add PSR-4 to root `composer.json` and run `composer dump-autoload`.
5. Implement migrations, routes, pages, listeners.
6. Discuss product features before coding business logic (as with Installments).

---

## Installments addon

Scaffold: `addons/installments/`.

**Shipped now:** folder layout, `addon.json`, empty provider, route stubs, migration placeholder, lang stub.

**Not built yet:** installment plans, schedules, POS payment option, collections UI — those features will be designed next.

See `addons/installments/README.md`.

---

## What we avoid

- Hard-coding `if (addon === 'installments')` all over core controllers
- Putting irreversible addon columns on core tables without a clear uninstall story
- Requiring inactive addon React pages in the main Vite entry (prefer lazy / registry later)
- Auto-seeding addon demo data into every normal tenant create

---

## Next engineering steps (runtime)

1. ~~Central `tenant_addons` + platform tenant Addons tab (Install / Remove)~~ — done.
2. After tenancy boots, load providers only for addons active on that tenant.
3. On Install/Remove: run / rollback addon tenant migrations.
4. Merge nav + permissions from active addons into tenant admin (use only — no install UI).
5. Implement Installments **features** against that runtime.
