
<?php
/**
 * WordPress configuration — multi-environment setup
 */

// ---------- Simple .env file loader ----------
function hlead_load_env( $file ) {
    if ( ! file_exists( $file ) ) return;

    $lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

    foreach ( $lines as $line ) {
        if ( strpos( trim( $line ), '#' ) === 0 ) continue;
        if ( strpos( $line, '=' ) === false ) continue;

        list( $key, $value ) = explode( '=', $line, 2 );

        $key   = trim( $key );
        $value = trim( $value );

        if ( getenv( $key ) === false ) {
            putenv( "$key=$value" );
            $_ENV[ $key ] = $value;
        }
    }
}

// ---------- Detect environment ----------
$wp_env = getenv('WP_ENV') ?: 'local';

$base = __DIR__;

hlead_load_env( "$base/.env.$wp_env" );
hlead_load_env( "$base/.env" );

// ---------- Database ----------
define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'leadgen_wp' );
define( 'DB_USER', getenv('WORDPRESS_DB_USER') ?: 'wpuser' );
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'wppassword' );

$db_host = getenv('WORDPRESS_DB_HOST') ?: 'db';
$db_port = getenv('WORDPRESS_DB_PORT') ?: '';

if ( $db_port && strpos( $db_host, ':' ) === false ) {
    $db_host = $db_host . ':' . $db_port;
}

define( 'DB_HOST', $db_host );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

// ---------- Website URLs ----------

$site_url = getenv('WP_HOME')
    ?: ( getenv('RENDER_EXTERNAL_URL') ?: 'https://behiveads.com' );

define( 'WP_HOME', $site_url );
define( 'WP_SITEURL', $site_url );

// ---------- HTTPS / Reverse Proxy Support ----------
// Supports Caddy and Render reverse proxies.

if (
    isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
    strpos( $_SERVER['HTTP_X_FORWARDED_PROTO'], 'https' ) !== false
) {
    $_SERVER['HTTPS'] = 'on';
}

if ( isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

// ---------- Auth Keys & Salts ----------
define( 'AUTH_KEY', getenv('WP_AUTH_KEY') ?: 'change-me-local-auth-key' );
define( 'SECURE_AUTH_KEY', getenv('WP_SECURE_AUTH_KEY') ?: 'change-me-local-secure-auth-key' );
define( 'LOGGED_IN_KEY', getenv('WP_LOGGED_IN_KEY') ?: 'change-me-local-logged-in-key' );
define( 'NONCE_KEY', getenv('WP_NONCE_KEY') ?: 'change-me-local-nonce-key' );
define( 'AUTH_SALT', getenv('WP_AUTH_SALT') ?: 'change-me-local-auth-salt' );
define( 'SECURE_AUTH_SALT', getenv('WP_SECURE_AUTH_SALT') ?: 'change-me-local-secure-auth-salt' );
define( 'LOGGED_IN_SALT', getenv('WP_LOGGED_IN_SALT') ?: 'change-me-local-logged-in-salt' );
define( 'NONCE_SALT', getenv('WP_NONCE_SALT') ?: 'change-me-local-nonce-salt' );

// ---------- Debug ----------
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// ---------- Bootstrap ----------
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';

