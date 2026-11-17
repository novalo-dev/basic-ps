#!/bin/sh
# Builds the module folder a shop installs: the tracked files minus what
# .gitattributes marks export-ignore, plus the Composer autoloader the module
# loads its classes with. Usage: dev/package/build.sh OUTPUT_DIR [REF]
set -eu
OUT="$1"
REF="${2:-HEAD}"
# Found from the script itself, so it runs from any folder.
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEST="$OUT/ps_edition_basic"
rm -rf "$DEST"
mkdir -p "$DEST"
git -C "$ROOT" archive "$REF" | tar -x -C "$DEST"
composer dump-autoload --working-dir="$DEST" --no-dev --classmap-authoritative --no-interaction --quiet
# Every folder needs an index.php, the autoloader's included, or PrestaShop's
# module validator refuses the zip.
find "$DEST" -type d ! -exec test -e '{}/index.php' ';' -exec cp "$DEST/index.php" '{}/index.php' ';'
echo "$DEST"
