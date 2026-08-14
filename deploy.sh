#!/usr/bin/env bash
# DukanPOS production deploy
#
# Local:  build frontend assets only when sources changed (committed under public/build)
# Remote: git pull + landlord migrate + tenant migrate
#
# Usage:
#   ./deploy.sh               # smart build, push if ahead, then deploy
#   ./deploy.sh --force-build # always rebuild assets
#   ./deploy.sh --skip-build  # never rebuild (assets already built & pushed)
#   ./deploy.sh --no-push     # build (if needed) + remote only
#
# SSH:  usman@172.236.227.235
# Path: /var/www/app.thedukanpos.com

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

SSH_HOST="${DEPLOY_SSH_HOST:-usman@172.236.227.235}"
REMOTE_DIR="${DEPLOY_REMOTE_DIR:-/var/www/app.thedukanpos.com}"
BUILD_STAMP="public/build/.source-hash"

DO_BUILD=1
FORCE_BUILD=0
DO_PUSH=1

for arg in "$@"; do
  case "$arg" in
    --no-push) DO_PUSH=0 ;;
    --skip-build) DO_BUILD=0 ;;
    --force-build) FORCE_BUILD=1 ;;
    -h|--help)
      sed -n '2,16p' "$0"
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

# Content hash of frontend inputs. Stable if sources (and package lock) are unchanged.
frontend_source_hash() {
  git ls-files -- \
    resources/js \
    resources/css \
    resources/views/app.blade.php \
    vite.config.js \
    vite.config.ts \
    package.json \
    package-lock.json \
    postcss.config.js \
    tailwind.config.js \
    jsconfig.json \
    | LC_ALL=C sort \
    | git hash-object --stdin-paths \
    | git hash-object --stdin
}

echo ""
echo "=== DukanPOS deploy ==="
echo "  Host:   ${SSH_HOST}"
echo "  Path:   ${REMOTE_DIR}"
echo ""

command -v ssh >/dev/null || die "ssh is required"
command -v git >/dev/null || die "git is required"

# --- local build (skip when sources match last build stamp) ---
if [[ "$DO_BUILD" -eq 0 ]]; then
  warn "Skipping local build (--skip-build)"
  echo ""
else
  command -v npm >/dev/null || die "npm is required for build"

  CURRENT_HASH="$(frontend_source_hash)"
  PREVIOUS_HASH=""
  if [[ -f "$BUILD_STAMP" ]]; then
    PREVIOUS_HASH="$(tr -d '[:space:]' < "$BUILD_STAMP")"
  fi

  if [[ "$FORCE_BUILD" -eq 0 \
     && -n "$CURRENT_HASH" \
     && "$CURRENT_HASH" == "$PREVIOUS_HASH" \
     && -f public/build/manifest.json ]]; then
    ok "Frontend unchanged — skipping npm run build"
    echo "  stamp: ${CURRENT_HASH:0:12}…"
    echo ""
  else
    if [[ "$FORCE_BUILD" -eq 1 ]]; then
      warn "Forcing rebuild (--force-build)"
    elif [[ ! -f public/build/manifest.json ]]; then
      warn "No public/build/manifest.json — building"
    elif [[ -z "$PREVIOUS_HASH" ]]; then
      warn "No build stamp yet — building"
    else
      warn "Frontend sources changed — building"
    fi

    echo "Building frontend assets…"
    npm run build
    mkdir -p "$(dirname "$BUILD_STAMP")"
    printf '%s\n' "$CURRENT_HASH" > "$BUILD_STAMP"
    ok "npm run build"
    echo "  stamp: ${CURRENT_HASH:0:12}…"
    echo ""
  fi
fi

# Ignore local storage noise; fail if deploy-relevant files are uncommitted.
RELEVANT_DIRTY="$(
  git status --porcelain \
    | grep -vE '^\?\? ' \
    | grep -vE ' storage/' \
    | grep -vE '\.DS_Store$' \
    || true
)"
if [[ -n "$RELEVANT_DIRTY" ]]; then
  echo "$RELEVANT_DIRTY"
  die "Uncommitted changes above (storage/ ignored). Commit and push, then re-run ./deploy.sh --skip-build"
fi

# --- push local commits so remote git pull can see them ---
if [[ "$DO_PUSH" -eq 1 ]]; then
  git fetch origin >/dev/null 2>&1 || warn "git fetch failed — continuing with local tracking info"
  BRANCH="$(git rev-parse --abbrev-ref HEAD)"
  UPSTREAM="$(git rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || true)"

  if [[ -z "$UPSTREAM" ]]; then
    die "Branch '${BRANCH}' has no upstream. Push once with: git push -u origin ${BRANCH}"
  fi

  AHEAD="$(git rev-list --count "${UPSTREAM}..HEAD" 2>/dev/null || echo 0)"
  BEHIND="$(git rev-list --count "HEAD..${UPSTREAM}" 2>/dev/null || echo 0)"

  if [[ "$BEHIND" -gt 0 ]]; then
    die "Local ${BRANCH} is behind ${UPSTREAM} by ${BEHIND} commit(s). Pull/rebase first."
  fi

  if [[ "$AHEAD" -gt 0 ]]; then
    echo "Pushing ${AHEAD} local commit(s) to ${UPSTREAM}…"
    git push
    ok "git push"
  else
    ok "Remote already has latest ${BRANCH}"
  fi
  echo ""
else
  warn "Skipping git push (--no-push)"
  echo ""
fi

# --- remote deploy ---
echo "Connecting to ${SSH_HOST}…"
ssh -o BatchMode=yes -o ConnectTimeout=20 "${SSH_HOST}" bash -s <<REMOTE
set -euo pipefail

ok() { printf '\\033[32m✓\\033[0m %s\\n' "\$*"; }
die() { printf '\\033[31m✗\\033[0m %s\\n' "\$*" >&2; exit 1; }

cd "${REMOTE_DIR}" || die "Cannot cd to ${REMOTE_DIR}"
ok "In ${REMOTE_DIR}"

echo "Pulling latest code…"
git pull --ff-only
ok "git pull"

echo "Running landlord migrations…"
php artisan migrate --force
ok "Landlord migrate"

echo "Running tenant migrations…"
php artisan tenants:migrate --force
ok "Tenants migrate"

echo "Clearing caches…"
php artisan optimize:clear
ok "optimize:clear"
REMOTE

echo ""
echo "========================================"
echo "  Successfully deployed."
echo "========================================"
echo ""
echo "  App:  https://app.thedukanpos.com"
echo ""
