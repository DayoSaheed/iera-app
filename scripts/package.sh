#!/usr/bin/env bash
# Build a release zip (no secrets, no tests, no local data).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
name="iera-app-$(date +%Y%m%d-%H%M).zip"
rm -f "dist/$name"
zip -qr "dist/$name" . \
  -x 'config/config.php' 'storage/uploads/*' 'storage/logs/*' 'storage/cache/*' 'storage/backups/*' 'storage/exports/*' 'storage/*.sqlite' \
     'dist/*' 'tests/*' '.git/*' '.DS_Store' 'node_modules/*'
# keep the empty storage folders
for d in uploads logs cache backups; do zip -q "dist/$name" "storage/$d/.gitkeep" 2>/dev/null || true; done
echo "dist/$name"
