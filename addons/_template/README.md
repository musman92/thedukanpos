# Addon template

Copy this folder to create a new addon:

```bash
cp -R addons/_template addons/my-addon
```

Then replace placeholders (case-sensitive):

| Placeholder | Example |
|-------------|---------|
| `your-addon` | `loyalty` (folder + slug) |
| `YourAddon` | `Loyalty` (StudlyCase PHP namespace segment) |
| `your_addon` | `loyalty` (permission / table prefix style) |
| `Your Addon` | `Loyalty` (display name) |

Checklist:

1. Edit `addon.json` (slug, name, provider, permissions, nav).
2. Rename `src/Providers/YourAddonServiceProvider.php` → `{YourAddon}ServiceProvider.php` and fix the class/namespace.
3. Add to root `composer.json`:

   ```json
   "Addons\\YourAddon\\": "addons/your-addon/src/"
   ```

4. Run `composer dump-autoload`.
5. Add tenant migrations under `database/migrations/`.
6. Register routes in `routes/admin.php` (and `pos.php` if needed).
7. Keep business logic inside this addon; use core events/hooks only.

Do not commit a filled copy of `_template` as a live addon — use a real slug folder instead.
