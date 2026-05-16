#!/usr/bin/env bash
# SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later
#
# Build the PrestaShop-installable .zip artifact for a tagged release.
#
# Usage:
#   tools/build-zip.sh                    # uses version from composer.json or git
#   tools/build-zip.sh v0.1.0-alpha.1     # explicit version override
#
# Output: dist/opensalestax-<VERSION>.zip
#
# The zip contains a single top-level directory `opensalestax/` (PrestaShop
# expects the module's slug as the directory name) with:
#   opensalestax/opensalestax.php          # module main file
#   opensalestax/config.xml                # PS metadata
#   opensalestax/index.php                 # blank-redirect
#   opensalestax/src/                      # framework-agnostic core (PSR-4)
#   opensalestax/vendor/                   # production-only composer tree
#   opensalestax/LICENSE etc.
#
# `vendor/` is the production-only composer tree (no dev dependencies, no
# phpunit / phpstan / php-cs-fixer). The bundled SDK + Guzzle + PSR
# interfaces add ~1.5 MB; the SDK alone is what the runtime module
# actually uses.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

VERSION="${1:-}"
if [ -z "$VERSION" ]; then
    if command -v jq >/dev/null 2>&1; then
        VERSION="$(jq -r '.extra.version // empty' composer.json 2>/dev/null || true)"
    fi
fi
if [ -z "$VERSION" ]; then
    VERSION="$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.1.0-alpha.1")"
fi

VERSION_NUM="${VERSION#v}"

DIST_DIR="$REPO_ROOT/dist"
STAGING_DIR="$REPO_ROOT/build/zip-staging"
MODULE_DIR="$STAGING_DIR/opensalestax"
ARTIFACT="$DIST_DIR/opensalestax-v${VERSION_NUM}.zip"

echo "Building $ARTIFACT (version=$VERSION_NUM)"

rm -rf "$STAGING_DIR"
mkdir -p "$MODULE_DIR"
mkdir -p "$DIST_DIR"

# Copy the module files into the staging directory.
cp "$REPO_ROOT/opensalestax.php" "$MODULE_DIR/"
cp "$REPO_ROOT/config.xml" "$MODULE_DIR/"
cp "$REPO_ROOT/index.php" "$MODULE_DIR/"
cp "$REPO_ROOT/LICENSE" "$MODULE_DIR/"
cp "$REPO_ROOT/LICENSE-APACHE.txt" "$MODULE_DIR/"
cp "$REPO_ROOT/LICENSE-GPL.txt" "$MODULE_DIR/"
cp "$REPO_ROOT/README.md" "$MODULE_DIR/"
cp "$REPO_ROOT/CHANGELOG.md" "$MODULE_DIR/"

# Copy src/.
mkdir -p "$MODULE_DIR/src"
cp -R "$REPO_ROOT/src/." "$MODULE_DIR/src/"

# Build a production-only vendor tree alongside the staging.
VENDOR_BUILD_DIR="$REPO_ROOT/build/vendor-build"
rm -rf "$VENDOR_BUILD_DIR"
mkdir -p "$VENDOR_BUILD_DIR"
cp "$REPO_ROOT/composer.json" "$VENDOR_BUILD_DIR/composer.json"
[ -f "$REPO_ROOT/composer.lock" ] && cp "$REPO_ROOT/composer.lock" "$VENDOR_BUILD_DIR/composer.lock"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

# Pick a sensible composer entry on Windows + XAMPP if `composer` is missing.
if ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
    if [ -f "/c/Users/ejosterberg/.local/bin/composer.phar" ]; then
        COMPOSER_BIN="/c/xampp/8.2.4/php/php.exe /c/Users/ejosterberg/.local/bin/composer.phar"
    fi
fi

(
    cd "$VENDOR_BUILD_DIR"
    # shellcheck disable=SC2086
    $COMPOSER_BIN install --no-dev --optimize-autoloader --no-progress --no-interaction
)

cp -R "$VENDOR_BUILD_DIR/vendor" "$MODULE_DIR/vendor"

# Bump the version inside config.xml + opensalestax.php to match VERSION_NUM.
# Use sed-friendly substitution — both files have a single `0.1.0` literal at
# install time. After we have stable semver releases this becomes a noop.
if [ "$VERSION_NUM" != "0.1.0" ]; then
    if command -v perl >/dev/null 2>&1; then
        perl -pi -e "s/<version><!\[CDATA\[0\.1\.0\]\]><\/version>/<version><![CDATA[${VERSION_NUM}]]><\/version>/g" "$MODULE_DIR/config.xml"
        perl -pi -e "s/\\\$this->version\s*=\s*'0\.1\.0';/\$this->version = '${VERSION_NUM}';/g" "$MODULE_DIR/opensalestax.php"
    fi
fi

# Strip OS-specific cruft.
find "$STAGING_DIR" -name ".DS_Store" -delete 2>/dev/null || true
find "$STAGING_DIR" -name "Thumbs.db" -delete 2>/dev/null || true

# Produce the zip.
rm -f "$ARTIFACT"
if command -v zip >/dev/null 2>&1; then
    (
        cd "$STAGING_DIR"
        zip -qr "$ARTIFACT" opensalestax
    )
else
    # Fallback: PHP's ZipArchive — portable across Windows / macOS / Linux.
    ZIP_PHP="$REPO_ROOT/build/zip-helper.php"
    cat > "$ZIP_PHP" <<'PHP_ZIP'
<?php
$src = $argv[1];
$dst = $argv[2];
$prefix = $argv[3];
$zip = new ZipArchive();
if ($zip->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Failed to open $dst for writing\n");
    exit(3);
}
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iter as $file) {
    $absPath = $file->getRealPath();
    if ($absPath === false) {
        continue;
    }
    $relPath = ltrim(substr($absPath, strlen($src)), DIRECTORY_SEPARATOR . '/');
    $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
    if (!$zip->addFile($absPath, $relPath)) {
        fwrite(STDERR, "Failed to add $relPath to zip\n");
        exit(4);
    }
}
$zip->close();
PHP_ZIP
    "$PHP_BIN" "$ZIP_PHP" "$STAGING_DIR" "$ARTIFACT" "opensalestax"
fi

echo "OK: $ARTIFACT"
ls -lh "$ARTIFACT"
