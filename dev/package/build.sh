#!/bin/sh
# Builds the module folder a shop installs: the tracked files minus what
# .gitattributes marks export-ignore, plus the Composer autoloader the module
# loads its classes with.
#
# Usage: dev/package/build.sh OUTPUT_DIR [8|9] [REF]
#
# 8 (the default) is the whole module, which runs on PrestaShop 8 and 9 alike.
# 9 leaves out what only PrestaShop 8 uses to look like PrestaShop 9 - most of
# the zip's weight - and declares PrestaShop 9.0 as the lowest version, so
# PrestaShop refuses it on a shop it would leave without that look.
set -eu
OUT="$1"
TARGET="${2:-8}"
REF="${3:-HEAD}"
case "$TARGET" in 8|9) ;; *) echo "Target must be 8 or 9, not $TARGET" >&2; exit 1 ;; esac
# Found from the script itself, so it runs from any folder.
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEST="$OUT/ps_edition_basic"
rm -rf "$DEST"
mkdir -p "$DEST"
git -C "$ROOT" archive "$REF" | tar -x -C "$DEST"
if [ "$TARGET" = 9 ]; then
  # Each of these is asked for only behind hasPrestaShop8Look().
  rm -f "$DEST/views/css/back.css" "$DEST/views/css/fonts.css" \
    "$DEST"/views/fonts/*.woff2 "$DEST/views/fonts/LICENSE-IBM-Plex-Sans.txt"
  FROM="'min' => '8.0.0'"
  grep -q "$FROM" "$DEST/ps_edition_basic.php" \
    || { echo "The versions the module declares are no longer written as expected" >&2; exit 1; }
  sed -i "s/$FROM/'min' => '9.0.0'/" "$DEST/ps_edition_basic.php"
fi
# After the removals, so the class map lists only the classes shipped.
composer dump-autoload --working-dir="$DEST" --no-dev --classmap-authoritative --no-interaction --quiet
# Every folder needs an index.php, the autoloader's included, or PrestaShop's
# module validator refuses the zip.
find "$DEST" -type d ! -exec test -e '{}/index.php' ';' -exec cp "$DEST/index.php" '{}/index.php' ';'
echo "$DEST"
