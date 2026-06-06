#!/usr/bin/env bash
# Deploy grouch-rss to a remote (or local) Apache/PHP host.
#
# Prerequisites:
#   - Docker must be running (used to build vendor/ with no-dev deps)
#   - SSH key-based access to DEPLOY_USER@DEPLOY_HOST
#
# Usage:
#   DEPLOY_USER=myuser DEPLOY_HOST=example.com DEPLOY_PATH=/home/myuser/public_html/feeds ./deploy.sh
#
# Or set the variables in your shell profile / CI secrets.

set -euo pipefail

: "${DEPLOY_USER:?DEPLOY_USER is required}"
: "${DEPLOY_HOST:?DEPLOY_HOST is required}"
: "${DEPLOY_PATH:?DEPLOY_PATH is required}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Building vendor/ (no-dev) inside Docker..."
docker run --rm \
  -v "${SCRIPT_DIR}:/app" \
  -w /app \
  "$(docker build -q -f "${SCRIPT_DIR}/docker/Dockerfile" "${SCRIPT_DIR}")" \
  composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Syncing to ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}/ ..."
rsync -avz --delete \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='tests/' \
  --exclude='docker/' \
  --exclude='docker-compose.yml' \
  --exclude='phpunit.xml' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='config.php' \
  --exclude='deploy.sh' \
  --exclude='.gitignore' \
  --exclude='composer' \
  "${SCRIPT_DIR}/" "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}/"

echo "==> Setting permissions..."
# shellcheck disable=SC2087
ssh "${DEPLOY_USER}@${DEPLOY_HOST}" bash -s <<EOF
  find "${DEPLOY_PATH}" -type d -exec chmod 755 {} \;
  find "${DEPLOY_PATH}" -type f -exec chmod 644 {} \;
EOF

echo "==> Done. Remember to create config.php on the server if you haven't already:"
echo "    ssh ${DEPLOY_USER}@${DEPLOY_HOST}"
echo "    cp ${DEPLOY_PATH}/config.php.example ${DEPLOY_PATH}/config.php"
echo "    \$EDITOR ${DEPLOY_PATH}/config.php"
