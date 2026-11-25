#!/bin/bash
# ============================================================================
# local-entrypoint.sh — replaces entrypoint.sh for local dev only.
#
# Key differences from production entrypoint.sh:
#   1. Reads WP_HOME / RENDER_EXTERNAL_URL from environment; never hard-codes
#      https://behiveads.com.
#   2. Skips the full setup.sh (which would overwrite URLs with the production
#      value).  Instead runs a minimal local-setup that:
#        a) Waits for MySQL
#        b) Runs the one-time restore (import SQL + WP files) ONLY when the
#           sentinel file /var/local-restore-done does NOT exist.
#        c) Calls wp search-replace to rewrite behiveads.com → localhost:8080
#           every boot (idempotent and serialization-safe).
#        d) Keeps pages, theme, and rewrite rules consistent.
#        e) Disables outgoing emails and external webhooks at the PHP level.
#   3. Never calls wp option update siteurl with a production URL.
# ============================================================================
set +e

LOCAL_URL="${WP_HOME:-http://localhost:8080}"
WP="wp --allow-root --path=/var/www/html"
SENTINEL="/var/local-restore-done"

# ── Start Apache in background ───────────────────────────────────────────────
docker-entrypoint.sh apache2-foreground &
APACHE_PID=$!

echo "[local] Waiting for Apache to become ready..."
sleep 10

# ── Wait for MySQL ────────────────────────────────────────────────────────────
echo "[local] Waiting for MySQL..."
for i in $(seq 1 40); do
    if mysql -h"db_local" \
             -u"${WORDPRESS_DB_USER}" \
             -p"${WORDPRESS_DB_PASSWORD}" \
             -e "SELECT 1;" "${WORDPRESS_DB_NAME}" >/dev/null 2>&1; then
        echo "[local] MySQL is ready."
        break
    fi
    echo "[local] Attempt $i/40 — MySQL not ready, retrying in 5s..."
    sleep 5
done

# ── One-time restore ─────────────────────────────────────────────────────────
# The restore script is copied into the image at build time.
# It creates $SENTINEL on success so it never runs again on restart.
if [ ! -f "$SENTINEL" ]; then
    echo "[local] Running one-time restore..."
    /local-restore-inner.sh
else
    echo "[local] Restore already done (sentinel found). Skipping."
fi

# ── Always enforce local URL (idempotent, serialization-safe) ────────────────
echo "[local] Enforcing local URL: $LOCAL_URL"
$WP search-replace 'https://behiveads.com' "$LOCAL_URL" \
    --all-tables --skip-columns=guid --precise --quiet 2>/dev/null
$WP search-replace 'http://behiveads.com'  "$LOCAL_URL" \
    --all-tables --skip-columns=guid --precise --quiet 2>/dev/null
$WP option update siteurl "$LOCAL_URL" --quiet 2>/dev/null
$WP option update home    "$LOCAL_URL" --quiet 2>/dev/null

# ── Disable outgoing email at the PHP/WordPress level ────────────────────────
# Strategy: drop a must-use plugin into wp-content/mu-plugins/.
# MU-plugins load on every request before regular plugins; they cannot be
# deactivated from the admin UI.  This is more reliable than WP_MAIL_DISABLED
# because it intercepts wp_mail() even if another plugin re-enables it.
echo "[local] Installing local-disable mu-plugin..."
mkdir -p /var/www/html/wp-content/mu-plugins
cat > /var/www/html/wp-content/mu-plugins/local-disable.php << 'MUPLUGIN'
<?php
/**
 * Local Development Safety Net
 * Loaded automatically on every request (must-use plugin).
 * Blocks outgoing email, HTTP API calls to production, and webhook dispatches.
 */

// ── 1. Block all outgoing WordPress email ────────────────────────────────────
add_filter( 'wp_mail', function( $args ) {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        return $args; // allow WP-CLI install/admin commands to send if needed
    }
    // Redirect to a local black-hole address instead of a real recipient.
    $args['to'] = 'devnull@localhost.invalid';
    return $args;
} );

// Log blocked mail so you can inspect what would have been sent.
add_filter( 'wp_mail', function( $args ) {
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( '[local-disable] Intercepted wp_mail() to: ' . print_r( $args['to'] ?? '', true ) . ' | subject: ' . ( $args['subject'] ?? '' ) );
    }
    return $args;
}, 20 );

// ── 2. Block outgoing HTTP requests to production domains ────────────────────
// WordPress uses wp_remote_* / WP_Http internally. This filter runs before
// every external HTTP request and blocks requests to production domains.
add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
    $blocked_hosts = [
        'behiveads.com',
        'www.behiveads.com',
    ];
    $host = wp_parse_url( $url, PHP_URL_HOST );
    foreach ( $blocked_hosts as $blocked ) {
        if ( $host && ( $host === $blocked || str_ends_with( $host, '.' . $blocked ) ) ) {
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( '[local-disable] Blocked HTTP request to production: ' . $url );
            }
            return new WP_Error( 'local_blocked', 'Outgoing HTTP request to production domain blocked in local environment.' );
        }
    }
    return $preempt;
}, 1, 3 );

// ── 3. Suppress admin email change notifications ──────────────────────────────
add_filter( 'send_email_change_email',    '__return_false' );
add_filter( 'send_password_change_email', '__return_false' );
add_filter( 'wp_new_user_notification_email',       '__return_false' );
add_filter( 'wp_new_user_notification_email_admin', '__return_false' );

// ── 4. Identify local environment in admin bar ───────────────────────────────
add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
    $wp_admin_bar->add_node( [
        'id'    => 'local-env-badge',
        'title' => '🏠 LOCAL DEV — emails & prod HTTP blocked',
        'href'  => false,
        'meta'  => [ 'class' => 'local-env-badge' ],
    ] );
}, 100 );
MUPLUGIN

chown -R www-data:www-data /var/www/html/wp-content/mu-plugins/
echo "[local] mu-plugin installed."

# ── Activate theme + flush rewrite rules ─────────────────────────────────────
$WP theme activate habitat-leadgen --quiet 2>/dev/null
$WP rewrite structure '/%postname%/' --quiet 2>/dev/null
$WP rewrite flush --hard --quiet 2>/dev/null

# ── Write .htaccess ───────────────────────────────────────────────────────────
cat > /var/www/html/.htaccess << 'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS

echo "[local] Setup complete. Handing off to Apache."
wait $APACHE_PID
