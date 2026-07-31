#!/usr/bin/env bash
# DukanPOS local setup — install, migrate, seed demo shop, build assets.
# Usage:
#   ./setup.sh           # one-time / refresh setup
#   ./setup.sh --serve   # setup then start php + vite
#   ./setup.sh --fresh   # wipe landlord DB tables and re-provision shop1 (destructive)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

SERVE=0
FRESH=0
for arg in "$@"; do
  case "$arg" in
    --serve) SERVE=1 ;;
    --fresh) FRESH=1 ;;
    -h|--help)
      sed -n '2,7p' "$0"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg (try --help)"
      exit 1
      ;;
  esac
done

ok()   { printf '\033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '\033[33m!\033[0m %s\n' "$*"; }
die()  { printf '\033[31m✗\033[0m %s\n' "$*" >&2; exit 1; }

echo ""
echo "=== DukanPOS setup ==="
echo ""

# --- prerequisites ---
command -v php >/dev/null || die "PHP 8.2+ is required"
command -v composer >/dev/null || die "Composer is required"
command -v node >/dev/null || die "Node.js 20+ is required"
command -v npm >/dev/null || die "npm is required"

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
ok "PHP $PHP_VER"
ok "Composer $(composer --version 2>/dev/null | head -1 | sed 's/Composer version //')"
ok "Node $(node -v) / npm $(npm -v)"

# --- .env ---
if [[ ! -f .env ]]; then
  cp .env.example .env
  ok "Created .env from .env.example"
else
  ok ".env already exists"
fi

# Local-friendly defaults (idempotent)
php -r '
$path = ".env";
$env = file_get_contents($path);
$sets = [
    "QUEUE_CONNECTION" => "sync",
    "CACHE_STORE" => "file",
    "SESSION_CONNECTION" => "mysql",
    "SESSION_DRIVER" => "database",
    "DB_CONNECTION" => "mysql",
    "DB_DATABASE" => "dukanpos_landlord",
];
foreach ($sets as $key => $value) {
    if (preg_match("/^{$key}=.*/m", $env)) {
        $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
    } else {
        $env .= "\n{$key}={$value}\n";
    }
}
file_put_contents($path, $env);
'
ok "Local env defaults (MySQL landlord, sync queue, file cache)"

# --- PHP dependencies (needed before artisan) ---
echo ""
echo "Installing PHP dependencies…"
composer install --no-interaction
ok "composer install"

# --- app key ---
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
  ok "Generated APP_KEY"
else
  ok "APP_KEY present"
fi

# --- MySQL landlord database ---
php <<'PHP'
<?php
function env_val(string $key, ?string $default = null): ?string {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim($v, " \t\"'");
        }
    }
    return $default;
}

$host = env_val('DB_HOST', '127.0.0.1');
$port = env_val('DB_PORT', '3306');
$user = env_val('DB_USERNAME', 'root');
$pass = env_val('DB_PASSWORD', '');
$db   = env_val('DB_DATABASE', 'dukanpos_landlord');

try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "OK landlord DB `{$db}` on {$host}:{$port}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL error: {$e->getMessage()}\n");
    fwrite(STDERR, "Start MySQL and ensure DB_USERNAME/DB_PASSWORD in .env are correct.\n");
    exit(1);
}
PHP
ok "Landlord database ready"

# --- migrations ---
if [[ "$FRESH" -eq 1 ]]; then
  warn "Fresh migrate — dropping landlord tables"
  php artisan migrate:fresh --force
  ok "Landlord migrate:fresh"
else
  php artisan migrate --force
  ok "Landlord migrations"
fi

# --- demo tenant ---
TENANT_CODE="${DUKAN_TENANT_CODE:-shop1}"
TENANT_NAME="${DUKAN_TENANT_NAME:-Demo Shop}"
TENANT_PASS="${DUKAN_TENANT_PASSWORD:-password}"

if [[ "$FRESH" -eq 1 ]]; then
  # After migrate:fresh, tenants table is empty but tenant DBs may linger.
  php -r "
    require 'vendor/autoload.php';
    \$app = require 'bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    \$db = 'dukanpos_tenant_${TENANT_CODE}';
    try {
        Illuminate\Support\Facades\DB::statement('DROP DATABASE IF EXISTS \`'.\$db.'\`');
        echo \"Dropped {\$db}\n\";
    } catch (Throwable \$e) {
        fwrite(STDERR, \$e->getMessage().PHP_EOL);
    }
  " || true
fi

TENANT_EXISTS="$(php -r "
  require 'vendor/autoload.php';
  \$app = require 'bootstrap/app.php';
  \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  echo App\Models\Tenant::query()->where('code', '${TENANT_CODE}')->exists() ? 'yes' : 'no';
")"

if [[ "$TENANT_EXISTS" == "yes" ]]; then
  ok "Tenant [${TENANT_CODE}] already exists — running tenants:migrate"
  php artisan tenants:migrate --force
else
  php artisan dukan:create-tenant "${TENANT_CODE}" --name="${TENANT_NAME}" --password="${TENANT_PASS}"
  ok "Created tenant [${TENANT_CODE}]"
fi

# --- frontend ---
if [[ ! -d node_modules ]]; then
  echo ""
  echo "Installing npm dependencies…"
  npm install
else
  npm install --silent
fi
ok "npm install"

echo ""
echo "Building frontend assets…"
npm run build
ok "npm run build"

# --- done ---
echo ""
echo "========================================"
echo "  Ready. Open and check:"
echo "========================================"
echo ""
echo "  App:      http://localhost:8000"
echo "  Login:    http://localhost:8000/login"
echo "  Account:  admin@${TENANT_CODE}"
echo "  Password: ${TENANT_PASS}"
echo ""
echo "  Admin:    http://localhost:8000/admin"
echo "  Platform: http://localhost:8000/platform/tenants"
echo ""
echo "  Quick check:"
echo "    1. Login as admin@${TENANT_CODE} → open a shift (Admin → Shifts)"
echo "    2. Add a product → receive a purchase"
echo "    3. Sell on POS (/) → print receipt"
echo "    4. Try credit sale with a customer"
echo ""

if [[ "$SERVE" -eq 1 ]]; then
  echo "Starting servers (Ctrl+C to stop)…"
  echo "  • php artisan serve  → http://localhost:8000"
  echo "  • npm run dev        → Vite HMR"
  echo ""
  if command -v npx >/dev/null && npx --yes concurrently -h >/dev/null 2>&1; then
    npx concurrently -k -c "#93c5fd,#c4b5fd" \
      "php artisan serve" \
      "npm run dev" \
      --names "php,vite"
  else
    php artisan serve &
    PHP_PID=$!
    trap 'kill $PHP_PID 2>/dev/null || true' EXIT
    npm run dev
  fi
else
  echo "Start the app with:"
  echo "  ./setup.sh --serve"
  echo "  # or:"
  echo "  php artisan serve"
  echo "  npm run dev          # optional HMR; build already done"
  echo ""
fi
