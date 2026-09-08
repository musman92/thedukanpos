# Agent instructions

Before exploring or changing this repo, **read [`docs/project-map.md`](docs/project-map.md)** for product scope, folders, flows, key files, and conventions.

Also apply Cursor rules when relevant:

- `.cursor/rules/catalog-master-module.mdc` — new/refactored catalog masters copy **Brands**
- `.cursor/rules/admin-nav-naming.mdc` — plural lists vs singular tools

Defaults: thin controllers, logic in `app/Services/`, Inertia drawers for admin CRUD, landlord vs `database/migrations/tenant/` migrations. Prefer matching existing modules over inventing new patterns.
