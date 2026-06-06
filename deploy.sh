#!/usr/bin/env bash
# Deploy grouch-rss to a remote (or local) Apache/PHP host.
#
# USAGE
#   ./deploy.sh [--skip-config] <destination>
#
#   <destination>    Local path or rsync/scp-style remote, e.g.
#                      /var/www/html/feeds          (local)
#                      akop@example.com:public_html/feeds  (remote)
#
#   --skip-config    Do not copy config.php (use after first deployment
#                    if you've customised the token on the server)
#
# PREREQUISITES
#   - SSH key-based access to the target host (no password prompt)
#   - No Composer or Docker required — production uses a built-in autoloader
#
# SUBSEQUENT DEPLOYMENTS
#   Re-run this script any time. By default config.php is included.
#   Pass --skip-config to leave an existing server config.php untouched.

set -euo pipefail

SKIP_CONFIG=false

# Parse flags
while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-config) SKIP_CONFIG=true; shift ;;
        -*) echo "Unknown option: $1" >&2; exit 1 ;;
        *) break ;;
    esac
done

if [[ $# -ne 1 ]]; then
    echo "Usage: ./deploy.sh [--skip-config] <destination>" >&2
    echo "  local:  ./deploy.sh /var/www/html/feeds" >&2
    echo "  remote: ./deploy.sh akop@example.com:public_html/feeds" >&2
    exit 1
fi

DESTINATION="$1"

# Determine whether this is a remote (user@host:path) or local path.
if [[ "$DESTINATION" == *:* ]]; then
    IS_REMOTE=true
    REMOTE="${DESTINATION%%:*}"      # user@host
    REMOTE_PATH="${DESTINATION#*:}"  # path on the remote host
else
    IS_REMOTE=false
    REMOTE_PATH="$DESTINATION"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

RSYNC_EXCLUDES=(
    --exclude='.git/'
    --exclude='.github/'
    --exclude='tests/'
    --exclude='docker/'
    --exclude='docs/'
    --exclude='phpunit.xml'
    --exclude='deploy.sh'
    --exclude='.gitignore'
    --exclude='config.php.example'
    --exclude='README.md'
    --exclude='LICENSE'
)

if [[ "$SKIP_CONFIG" == true ]]; then
    RSYNC_EXCLUDES+=(--exclude='config.php')
    echo "==> Syncing to ${DESTINATION}/ (skipping config.php) ..."
else
    echo "==> Syncing to ${DESTINATION}/ ..."
fi

rsync -avz --delete "${RSYNC_EXCLUDES[@]}" "${SCRIPT_DIR}/" "${DESTINATION}/"

echo "==> Setting permissions..."
if [[ "$IS_REMOTE" == true ]]; then
    # shellcheck disable=SC2087
    ssh "${REMOTE}" bash -s <<EOF
  find "${REMOTE_PATH}" -type d -exec chmod 755 {} \;
  find "${REMOTE_PATH}" -type f -exec chmod 644 {} \;
EOF
else
    find "${REMOTE_PATH}" -type d -exec chmod 755 {} \;
    find "${REMOTE_PATH}" -type f -exec chmod 644 {} \;
fi

echo "==> Done."
if [[ "$SKIP_CONFIG" == false ]]; then
    echo "    config.php was deployed. Edit it on the server to set FEED_TOKEN if needed."
fi
