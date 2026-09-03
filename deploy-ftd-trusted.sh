#!/bin/bash
set -euo pipefail
DEST="${1:-/var/www/html/adapter/public/ftd-trusted}"
BRANCH="${FTD_BRANCH:-okepayftd-trusted-06bd}"
ZIP_URL="https://github.com/ronaldreadenholdin/readies-2/raw/${BRANCH}/ftd-trusted-vps.zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
echo "Installing FTD vs trusted into $DEST"
mkdir -p "$DEST"
if command -v curl >/dev/null 2>&1; then
  curl -fL "$ZIP_URL" -o "$TMP/pack.zip"
else
  wget -O "$TMP/pack.zip" "$ZIP_URL"
fi
python3 - <<PY
import zipfile
zipfile.ZipFile("$TMP/pack.zip").extractall("$DEST")
PY
echo "Standalone staff tool is at https://0609.readies.biz/ftd-trusted/"
echo "Merchants do not upload. Prefer the 0609 admin page: /admin/ftd-trusted"
