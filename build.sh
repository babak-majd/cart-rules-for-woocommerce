#!/usr/bin/env bash
# Build the distributable plugin zip (slug "cart-rules-for-woocommerce") into dist/.
# Excludes everything listed in .distignore, so the zip is exactly what wordpress.org would serve.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
SLUG="cart-rules-for-woocommerce"
DIST="$ROOT/dist"; rm -rf "$DIST"; mkdir -p "$DIST"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/$SLUG"
rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$TMP/$SLUG/"
( cd "$TMP" && zip -rqX "$DIST/$SLUG.zip" "$SLUG" )

echo "== built =="; ls -la "$DIST"
