#!/usr/bin/env bash
# Build a WordPress.org / SVN-ready plugin ZIP.
# Root folder inside the ZIP must match the plugin slug: logicanvas-auctions/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${ROOT}/dist"
NAME="logicanvas-auctions"
VER="$(/usr/bin/grep -E "^\s*\* Version:" "${ROOT}/logicanvas-auctions.php" | /usr/bin/head -n1 | awk '{print $3}')"
VER="${VER:-1.0.0}"
ZIP="${OUT}/${NAME}-${VER}.zip"

mkdir -p "${OUT}"
rm -f "${ZIP}"

STAGE="$(mktemp -d)"
trap 'rm -rf "${STAGE}"' EXIT

rsync -a \
  --exclude '.git/' \
  --exclude '.gitignore' \
  --exclude '.gitattributes' \
  --exclude '.github/' \
  --exclude '.cursor/' \
  --exclude '.cursorrules' \
  --exclude '.idea/' \
  --exclude '.vscode/' \
  --exclude '.phpunit.cache/' \
  --exclude '.DS_Store' \
  --exclude '._*' \
  --exclude 'Thumbs.db' \
  --exclude '.distignore' \
  --exclude '/dist/' \
  --exclude '/bin/' \
  --exclude 'tests/' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude 'assets/src/' \
  --exclude 'composer.lock' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'phpstan.neon.dist' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'playwright.config.js' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude '*.zip' \
  "${ROOT}/" "${STAGE}/${NAME}/"

# Drop AppleDouble / Finder junk if any slipped through.
find "${STAGE}/${NAME}" \( -name '.DS_Store' -o -name '._*' -o -name 'Thumbs.db' \) -delete 2>/dev/null || true

# Hard requirement for WordPress.org.
if [[ ! -f "${STAGE}/${NAME}/readme.txt" ]]; then
  echo "error: readme.txt missing from staged plugin" >&2
  exit 1
fi
if [[ ! -f "${STAGE}/${NAME}/logicanvas-auctions.php" ]]; then
  echo "error: main plugin file missing from staged plugin" >&2
  exit 1
fi
if [[ ! -f "${STAGE}/${NAME}/LICENSE" ]]; then
  echo "error: LICENSE missing from staged plugin" >&2
  exit 1
fi
if [[ ! -f "${STAGE}/${NAME}/assets/dist/css/frontend.css" ]]; then
  echo "error: assets/dist CSS missing — do not exclude assets/dist/" >&2
  exit 1
fi
if [[ ! -f "${STAGE}/${NAME}/assets/dist/js/frontend.js" ]]; then
  echo "error: assets/dist JS missing — do not exclude assets/dist/" >&2
  exit 1
fi

cd "${STAGE}"
zip -r -q "${ZIP}" "${NAME}"

# Summary for release checks.
BYTES="$(wc -c < "${ZIP}" | tr -d ' ')"
COUNT="$(unzip -l "${ZIP}" | tail -1 | awk '{print $2}')"
echo "Wrote ${ZIP}"
echo "Size: ${BYTES} bytes"
echo "Entries: ${COUNT}"
echo ""
echo "SVN tip: unzip into a working copy trunk/ (folder must be ${NAME}/),"
echo "or copy contents of ${NAME}/ into svn/trunk/."
echo "Put banner/icon assets in the SVN assets/ directory — not inside this ZIP."
