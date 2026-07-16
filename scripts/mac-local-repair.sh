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

# 2) Ensure paths.php uses /Mbita
PATHS_FILE="app/config/paths.php"
if [ -f "$PATHS_FILE" ]; then
  echo "==> Setting base_path to /Mbita in $PATHS_FILE"
  sed -i '' "s/'base_path'[[:space:]]*=>[[:space:]]*'[^']*'/'base_path'       => '\/Mbita'/" "$PATHS_FILE"
else
  echo "==> Creating $PATHS_FILE"
  mkdir -p app/config
  cat > "$PATHS_FILE" <<'PHP'
<?php
return [
    'base_path'       => '/Mbita',
    'public_segment'  => 'public',
];
PHP
fi
echo ""

# 3) PHP syntax check on key auth files
echo "==> PHP syntax check..."
for f in public/index.php public/auth/login.php public/auth/forgot-password.php; do
  if [ -f "$f" ]; then
    php -l "$f" || true
  fi
done
echo ""

echo "==> Done."
echo ""
echo "Next steps:"
echo "  1. Start MySQL (AMPPS/MAMP/XAMPP)"
echo "  2. Open: http://localhost/Mbita/public/devs/health-check.php"
echo "  3. Open login: http://localhost/Mbita/public/auth/login.php"
echo ""
echo "When GitHub works again, save your work then pull:"
echo "  git stash push -m 'my laptop changes'"
echo "  git pull origin cursor/pos-auth-pin-roles-d36f"
echo "  git stash pop"
