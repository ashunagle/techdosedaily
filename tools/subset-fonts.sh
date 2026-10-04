#!/usr/bin/env sh
# Phase 7: trims OpenType layout features the design never uses (fractions, numerators/denominators,
# proportional figures) and the STAT table from the shipped woff2 files. Every character is kept
# (--unicodes='*'), so coverage and rendering are unchanged; files shrink ~15%.
# Needs fontTools + brotli:  pip install fonttools brotli
# Run on the ORIGINAL design-system font files (01-Design-System), never on already-trimmed output.
set -e
SRC="${1:?usage: tools/subset-fonts.sh <dir with original .woff2 files>}"
OUT="$(dirname "$0")/../theme/techdosedaily/assets/fonts"
for f in "$SRC"/*.woff2; do
  pyftsubset "$f" --unicodes='*' \
    --layout-features='kern,liga,calt,ccmp,locl,mark,mkmk,tnum' \
    --drop-tables+=STAT --name-IDs='1,2,3,4,6' \
    --flavor=woff2 --output-file="$OUT/$(basename "$f")"
  echo "$(basename "$f"): $(wc -c < "$f") -> $(wc -c < "$OUT/$(basename "$f")") bytes"
done
