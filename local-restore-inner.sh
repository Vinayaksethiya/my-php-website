#!/bin/bash
# ============================================================================
# local-restore-inner.sh — runs ONCE inside the WordPress container.
#
# Called by local-entrypoint.sh only when /var/local-restore-done is absent.
# After a successful run it touches that sentinel file so subsequent container
# restarts skip this script entirely.
#
# What it does:
#   1. Import database.sql.gz into the local MySQL (wp_local database).
#   2. Extract wordpress-files.tar.gz into /var/www/html, excluding the theme
#      directory (which is bind-mounted from ./theme/ on the host).
#   3. Ensure wp-config.php starts with <?php (no BOM, no leading whitespace).
#   4. Run WP-CLI search-replace to rewrite production URLs → localhost:8080.
#   5. Touch the sentinel file.
#
# Backup archives are NEVER modified; they are read-only bind-mounts.
# ============================================================================
set -e

LOCAL_URL="${WP_HOME:-http://localhost:8080}"
WP="wp --allow-root --path=/var/www/html"
SENTINEL="/var/local-restore-done"

BACKUP_DIR="/backups"
SQL_GZ="$BACKUP_DIR/database.sql.gz"
WP_GZ="$BACKUP_DIR/wordpress-files.tar.gz"
WP_ROOT="/var/www/html"

echo "=========================================="
echo " BehiveAds — LOCAL one-time restore"
echo "=========================================="

# ── Verify backup files are present ──────────────────────────────────────────
if [ ! -f "$SQL_GZ" ]; then
    echo "ERROR: $SQL_GZ not found. Mount the backup directory correctly."
    exit 1
fi
if [ ! -f "$WP_GZ" ]; then
    echo "ERROR: $WP_GZ not found. Mount the backup directory correctly."
    exit 1
fi

# ── 1. Import the SQL dump ────────────────────────────────────────────────────
echo "[restore] Importing database from $SQL_GZ ..."
# Drop and recreate the database to ensure a clean import
mysql -h"db_local" \
      -u"${WORDPRESS_DB_USER}" \
      -p"${WORDPRESS_DB_PASSWORD}" \
      -e "DROP DATABASE IF EXISTS \`${WORDPRESS_DB_NAME}\`; CREATE DATABASE \`${WORDPRESS_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;" 2>/dev/null

gunzip -c "$SQL_GZ" | mysql \
    -h"db_local" \
    -u"${WORDPRESS_DB_USER}" \
    -p"${WORDPRESS_DB_PASSWORD}" \
    "${WORDPRESS_DB_NAME}"

echo "[restore] Database import complete."

# ── 2. Extract WordPress files ────────────────────────────────────────────────
# We exclude:
#   - wp-content/themes/  (bind-mounted from ./theme/ — always up to date)
#   - wp-config.php       (provided by the image via COPY in Dockerfile)
# This preserves uploads, plugins, and core files from the production backup.
echo "[restore] Extracting WordPress files from $WP_GZ ..."

# Detect archive structure: may be flat (./wp-login.php) or wrapped (wordpress/)
# Try to find wp-login.php to determine the strip level.
STRIP_LEVEL=0
if tar -tzf "$WP_GZ" 2>/dev/null | grep -q '^wordpress/wp-login.php'; then
    STRIP_LEVEL=1
elif tar -tzf "$WP_GZ" 2>/dev/null | grep -q '^\./wp-login.php'; then
    STRIP_LEVEL=1
fi

tar -xzf "$WP_GZ" \
    --strip-components="$STRIP_LEVEL" \
    -C "$WP_ROOT" \
    --exclude='*/wp-content/themes' \
    --exclude='./wp-content/themes' \
    --exclude='wp-content/themes' \
    --exclude='*/wp-config.php' \
    --exclude='./wp-config.php' \
    --exclude='wp-config.php' \
    2>/dev/null || true

echo "[restore] WordPress files extracted."

# ── 3. Verify wp-config.php starts with <?php (no BOM, no leading space) ─────
# The Dockerfile COPYs wp-config.php fresh; we just validate it.
CONFIG="$WP_ROOT/wp-config.php"
if [ -f "$CONFIG" ]; then
    # Read first 5 bytes and check for UTF-8 BOM (EF BB BF) or leading whitespace
    FIRST_BYTES=$(head -c 5 "$CONFIG" | od -An -tx1 | tr -d ' \n' | head -c 10)
    # Remove BOM if present (efbbbf = UTF-8 BOM)
    if echo "$FIRST_BYTES" | grep -qi '^efbbbf'; then
        echo "[restore] BOM detected in wp-config.php — removing..."
        # Create a temp file without BOM
        tail -c +4 "$CONFIG" > "${CONFIG}.nobom"
        mv "${CONFIG}.nobom" "$CONFIG"
        echo "[restore] BOM removed."
    fi
    # Remove leading whitespace/newlines before <?php
    FIRST_CHARS=$(head -c 10 "$CONFIG")
    if ! echo "$FIRST_CHARS" | grep -q '^<?php'; then
        echo "[restore] Leading whitespace detected in wp-config.php — stripping..."
        # Use awk to strip everything before the first <?php
        awk '/^<\?php/{found=1} found{print}' "$CONFIG" > "${CONFIG}.clean"
        mv "${CONFIG}.clean" "$CONFIG"
        echo "[restore] Leading content stripped."
    fi
    echo "[restore] wp-config.php starts with: $(head -c 10 "$CONFIG" | cat -v)"
fi

# ── 4. Fix file ownership ─────────────────────────────────────────────────────
chown -R www-data:www-data "$WP_ROOT"
# Re-assert theme ownership (bind-mount may have different uid)
chown -R www-data:www-data "$WP_ROOT/wp-content/themes/habitat-leadgen" 2>/dev/null || true
echo "[restore] File ownership corrected."

# ── 5. URL search-replace (serialization-safe via WP-CLI) ────────────────────
echo "[restore] Rewriting production URLs to $LOCAL_URL ..."
$WP search-replace 'https://behiveads.com' "$LOCAL_URL" \
    --all-tables --skip-columns=guid --precise
$WP search-replace 'http://behiveads.com' "$LOCAL_URL" \
    --all-tables --skip-columns=guid --precise
# Update the two critical options explicitly (belt-and-suspenders)
$WP option update siteurl "$LOCAL_URL"
$WP option update home    "$LOCAL_URL"
echo "[restore] URL rewrite complete."

# ── 6. Disable WP cron from phoning home ─────────────────────────────────────
$WP config set DISABLE_WP_CRON true --raw --quiet 2>/dev/null || true

# ── 7. Touch sentinel — prevents re-running on restart ───────────────────────
touch "$SENTINEL"
echo "[restore] Sentinel created at $SENTINEL."
echo "[restore] ✅ One-time restore finished successfully."
