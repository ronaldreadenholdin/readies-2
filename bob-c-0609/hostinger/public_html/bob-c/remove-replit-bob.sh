#!/bin/bash
# Cancel the old Replit Bob on 0609. Keep BOB C.
# Run on the 0609 host as a user who can write the adapter app.
set -euo pipefail

APP_ROOT="${1:-/var/www/html/adapter}"
PUBLIC="$APP_ROOT/public"
BOB_C="$PUBLIC/bob-c"
QUARANTINE="$APP_ROOT/_removed_replit_bob_$(date +%Y%m%d%H%M%S)"
LAYOUT="$APP_ROOT/resources/views/layouts/adminpanel.blade.php"

if [ ! -d "$APP_ROOT" ]; then
  echo "Adapter app not found: $APP_ROOT"
  exit 1
fi

mkdir -p "$QUARANTINE"
echo "Quarantine: $QUARANTINE"
echo "Keeping BOB C at: $BOB_C"

move_if_exists() {
  local path="$1"
  if [ -e "$path" ]; then
    local rel="${path#$APP_ROOT/}"
    local dest="$QUARANTINE/$rel"
    mkdir -p "$(dirname "$dest")"
    mv "$path" "$dest"
    echo "Removed $rel"
  fi
}

# Typical Replit Bob locations. Never touch public/bob-c.
move_if_exists "$PUBLIC/bob"
move_if_exists "$PUBLIC/replit-bob"
move_if_exists "$PUBLIC/assistant/bob"
move_if_exists "$APP_ROOT/app/Http/Controllers/BobController.php"
move_if_exists "$APP_ROOT/app/Http/Controllers/ReplitBobController.php"
move_if_exists "$APP_ROOT/app/Services/ReplitBob.php"
move_if_exists "$APP_ROOT/app/Services/BobService.php"
move_if_exists "$APP_ROOT/resources/views/bob"
move_if_exists "$APP_ROOT/resources/views/replit-bob"
move_if_exists "$APP_ROOT/routes/bob.php"

# Extra files named Bob but not BOB C.
while IFS= read -r path; do
  case "$path" in
    *"/public/bob-c"*|*"/_removed_replit_bob"*|*"/bob-c-0609"*|*"/BobC"*|*"/bob_c"*|*"/BobG"*)
      continue
      ;;
  esac
  move_if_exists "$path"
done < <(find "$APP_ROOT" \
  \( -path "$BOB_C" -o -path "$QUARANTINE" -o -path "$APP_ROOT/vendor" -o -path "$APP_ROOT/node_modules" -o -path "$APP_ROOT/storage" \) -prune -o \
  \( -iname '*replit*bob*' -o -iname '*bob*replit*' -o -iname 'BobController.php' -o -iname 'replit-bob*' \) -print)

strip_old_bob_from_file() {
  local file="$1"
  [ -f "$file" ] || return 0
  cp "$file" "$QUARANTINE/$(basename "$file").bak"
  python3 - "$file" <<'PY'
import pathlib, re, sys
path = pathlib.Path(sys.argv[1])
text = path.read_text()
original = text
# Remove nav items that point at old Bob, never /bob-c.
patterns = [
    r'<li[^>]*>\s*<a[^>]+href=["\'][^"\']*/bob(?!-c)[^"\']*["\'][^>]*>.*?</a>\s*</li>\s*',
    r'<a[^>]+href=["\'][^"\']*/bob(?!-c)[^"\']*["\'][^>]*>\s*Bob\s*</a>\s*',
    r'<a[^>]+href=["\'][^"\']*replit[^"\']*["\'][^>]*>\s*Bob\s*</a>\s*',
]
for pattern in patterns:
    text = re.sub(pattern, '', text, flags=re.I | re.S)
# Neutralize leftover route registrations.
text = re.sub(r'^.*Route::\w+\([^\n]*/bob(?!-c)[^\n]*\n', '', text, flags=re.I | re.M)
if text != original:
    path.write_text(text)
    print(f"Stripped old Bob from {path}")
else:
    print(f"No old Bob markup in {path}")
PY
}

for file in \
  "$LAYOUT" \
  "$APP_ROOT/resources/views/layouts/app.blade.php" \
  "$APP_ROOT/resources/views/partials/sidebar.blade.php" \
  "$APP_ROOT/resources/views/components/sidebar.blade.php" \
  "$APP_ROOT/routes/web.php" \
  "$APP_ROOT/routes/admin.php"
do
  strip_old_bob_from_file "$file"
done

if [ -f "$LAYOUT" ] && ! grep -q '/bob-c' "$LAYOUT"; then
  python3 - "$LAYOUT" <<'PY'
import pathlib, sys
path = pathlib.Path(sys.argv[1])
text = path.read_text()
link = '''
<li class="nav-item">
    <a class="nav-link" href="/bob-c/"><span>BOB C</span></a>
</li>
'''
for marker in ("</ul>", "</nav>"):
    if marker in text:
        path.write_text(text.replace(marker, link + marker, 1))
        print(f"Inserted BOB C link before {marker} in {path}")
        break
else:
    path.write_text(text + link)
    print(f"Appended BOB C link to {path}")
PY
fi

if [ -f "$APP_ROOT/artisan" ]; then
  (cd "$APP_ROOT" && php artisan route:clear && php artisan view:clear && php artisan config:clear) || true
fi

echo
echo "Old Replit Bob cancelled."
echo "BOB C stays at /var/www/html/adapter/public/bob-c/"
echo "Open https://0609.readies.biz/bob-c/"
echo "Old files were moved to $QUARANTINE"
