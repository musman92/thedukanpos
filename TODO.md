# DukanPOS — later TODOs

Features agreed but not built yet.

---

## Flash product (POS ad-hoc sale)

Sell a one-off line without adding it to the product catalog.

### Behaviour
- Cashier enters **name**, **price**, and **qty** on POS and adds it to the cart
- **Cart / sale line only** — not a real product or variant
- **No stock** movement
- Appears on receipt / order detail as a flash/custom line
- Optional later: “Save to catalog” after sale

### Settings
- Preference under **Settings → Preferences** (or POS settings): **Enable flash products**
- **Default: off**
- When off, POS hides the flash-product entry UI entirely

### Open for build time
- [ ] Tax: inherit default tax vs none vs cashier pick
- [ ] Role/permission gate (who can use flash sales)
- [ ] Reports labelling (“Flash / Custom”)
- [ ] Returns against flash lines

---

## Addons

### Bug — not showing on platform
- [ ] **Platform → Tenants → {company} → Addons tab** does not show addons (catalog / install UI empty or broken)
- Investigate: `AddonCatalog` scan, `tenant_addons` migration on landlord, Show page props, Installments `addon.json`

### Runtime (reminder)
Still pending from earlier work (see `addons/README.md`):

- [ ] Boot active addon providers per tenant
- [ ] Run / rollback addon migrations on install / remove
- [ ] Merge nav + permissions from active addons
- [ ] Installments product features
