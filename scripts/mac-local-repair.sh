#!/bin/bash
# scripts/mac-local-repair.sh
# Run on your Mac inside the project folder to fix common login/path issues
# WITHOUT losing your local changes. Safe to run multiple times.
#
# Usage:
#   cd /Users/mac/Projects/Mbita
#   bash scripts/mac-local-repair.sh

set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Mbita POS local repair"
echo "Project: $ROOT"
echo ""

# 1) Fix PHP parse errors from bad public_path escaping
echo "==> Fixing escaped quotes in public_path() calls..."
fixed=0
while IFS= read -r -d '' f; do
  if grep -q "public_path(\\\\'" "$f" 2>/dev/null || grep -q "\\\\');" "$f" 2>/dev/null; then
    sed -i '' "s/public_path(\\\\'/public_path('/g; s/\\\\');/');/g" "$f"
    echo "  fixed: $f"
    fixed=$((fixed + 1))
  fi
done < <(find . -name '*.php' -not -path './vendor/*' -print0)
echo "  ($fixed files updated)"
echo ""

# 2) Ensure app.php app_url matches php built-in server (paths auto-detect on cli-server)
APP_FILE="app/config/app.php"
if [ -f "$APP_FILE" ]; then
  echo "==> Setting app_url to http://localhost:8000 in $APP_FILE"
  sed -i '' "s|'app_url'[[:space:]]*=>[[:space:]]*'[^']*'|'app_url'      => 'http://localhost:8000'|" "$APP_FILE"
fi
echo ""

# 3) PHP syntax check on key auth files
echo "==> PHP syntax check..."
for f in public/index.php public/auth/login.php public/auth/forgot-password.php app/helpers/AppUrl.php; do
  if [ -f "$f" ]; then
    php -l "$f" || true
  fi
done
echo ""

echo "==> Done."
echo ""
echo "Next steps (Mac — php built-in server + MySQL Workbench):"
echo "  1. Start MySQL server (Workbench is only the client)"
echo "  2. In project root:"
echo "       php -S localhost:8000 -t public"
echo "  3. Open: http://localhost:8000/ping.php"
echo "  4. Open: http://localhost:8000/devs/health-check.php"
echo "  5. Login: http://localhost:8000/auth/login.php"
echo ""
echo "When GitHub works again, save your work then pull:"
echo "  git stash push -m 'my laptop changes'"
echo "  git pull origin cursor/admins-management-d36f"
echo "  git stash pop"
