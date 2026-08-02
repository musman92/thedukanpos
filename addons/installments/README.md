# Installments addon

**Slug:** `installments`  
**Status:** Structure only — product features not implemented yet.

This package will let a tenant sell on installment plans without changing core sale/payment code paths permanently. Core stays usable when the addon is inactive or uninstalled.

## Layout

```text
addons/installments/
  addon.json
  README.md
  src/
    Providers/InstallmentsServiceProvider.php
    Http/Controllers/
    Models/
    Services/
    Listeners/
  database/migrations/
  routes/admin.php
  routes/pos.php
  resources/js/Pages/
  lang/en.json
```

## What is scaffolded

- Manifest (`addon.json`) with placeholder permissions + nav
- Service provider (migrations + route registration stubs)
- Empty admin/POS route files
- Example migration filename reserved (not active until we design tables)
- Lang stub

## What we will discuss next (features)

Out of scope until agreed:

- Plan types (weeks/months, interest/fees, down payment)
- Creating an installment from POS vs admin-only
- Schedule / due dates / late fees
- Collecting installment payments (money sources, partial pays)
- Linking to customers (Walk-in excluded?)
- Reports / overdue list
- Permissions granularity
- Uninstall: keep historical schedules vs wipe

## Runtime

Provider will load only when the Addon Manager marks `installments` as **active** for the tenant. Until that manager exists, this folder is inert scaffolding.
