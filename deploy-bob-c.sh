#!/bin/bash
# Install BOB C onto the live 0609 adapter public folder.
# Run on the VPS:
#   bash deploy-bob-c.sh
set -euo pipefail

DEST="${1:-/var/www/html/adapter/public/bob-c}"
BRANCH="${BOB_C_BRANCH:-okepaybob-c-sidebar-06bd}"
ZIP_URL="https://github.com/ronaldreadenholdin/readies-2/archive/refs/heads/${BRANCH}.zip"
TMP="$(mktemp -d)"
cleanup() { rm -rf "$TMP"; }
trap cleanup EXIT

echo "Installing BOB C into $DEST"

mkdir -p "$DEST"
if [ -f "$DEST/.env" ]; then
  cp "$DEST/.env" "$TMP/keep.env"
fi

echo "Downloading $ZIP_URL"
if command -v curl >/dev/null 2>&1; then
  curl -fL "$ZIP_URL" -o "$TMP/repo.zip"
else
  wget -O "$TMP/repo.zip" "$ZIP_URL"
fi

echo "Unpacking"
if command -v unzip >/dev/null 2>&1; then
  unzip -q "$TMP/repo.zip" -d "$TMP/unpacked"
else
  python3 - <<PY
import zipfile
zipfile.ZipFile("$TMP/repo.zip").extractall("$TMP/unpacked")
PY
fi

SRC="$(find "$TMP/unpacked" -type d -path '*/bob-c-0609/hostinger/public_html/bob-c' | head -n 1)"
if [ -z "$SRC" ] || [ ! -f "$SRC/index.php" ]; then
  echo "Could not find BOB C files in the download."
  exit 1
fi

mkdir -p "$DEST"
cp -a "$SRC/." "$DEST/"

if [ -f "$TMP/keep.env" ]; then
  cp "$TMP/keep.env" "$DEST/.env"
  echo "Kept existing .env"
elif [ ! -f "$DEST/.env" ]; then
  cp "$DEST/.env.example" "$DEST/.env"
  echo "Created $DEST/.env from .env.example"
fi

chmod -R u+rwX "$DEST"
find "$DEST" -type f -name '*.sh' -exec chmod +x {} \;

echo
echo "BOB C files are in $DEST"
ls -la "$DEST" | sed -n '1,20p'
echo
echo "Next: edit $DEST/.env and set real XAI_API_KEY and BOB_C_ACCESS_TOKEN"
echo "Then open https://0609.readies.biz/bob-c/"
