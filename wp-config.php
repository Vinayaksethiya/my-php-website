<?php
/**
 * WordPress configuration — multi-environment setup
 *
 * Priority order:
 *  1. System environment variables (Render dashboard / Docker Compose)
 *  2. .env.<WP_ENV> file  (local / development / production)
 *  3. .env file           (generic fallback)
 *  4. Hardcoded defaults  (local Docker)
 */

// ---------- Simple .env file loader ----------
function hlead_load_env( $file ) {
    if ( ! file_exists( $file ) ) return;
    $lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    foreach ( $lines as $line ) {
        if ( strpos( trim( $line ), '#' ) === 0 ) continue; // skip comments
        if ( strpos( $line, '=' ) === false ) continue;
        list( $key, $value ) = explode( '=', $line, 2 );
        $key   = trim( $key );
        $value = trim( $value );
        if ( ! getenv( $key ) ) {          // do not override real env vars
            putenv( "$key=$value" );
            $_ENV[ $key ] = $value;
        }
    }
}

// Detect environment: WP_ENV env var → default 'local'
$wp_env = getenv('WP_ENV') ?: 'local';

// Load env files (most specific first)
$base = __DIR__;
hlead_load_env( "$base/.env.$wp_env" );   // .env.local / .env.development / .env.production
hlead_load_env( "$base/.env" );           // generic fallback

// ---------- Database ----------
define( 'DB_NAME',     getenv('WORDPRESS_DB_NAME')     ?: 'leadgen_wp'   );
define( 'DB_USER',     getenv('WORDPRESS_DB_USER')     ?: 'wpuser'       );
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'wppassword'   );
// DB_HOST supports optional port: "hostname" or "hostname:3306"
$_db_host = getenv('WORDPRESS_DB_HOST') ?: 'db';
$_db_port = getenv('WORDPRESS_DB_PORT') ?: '';
if ( $_db_port && strpos( $_db_host, ':' ) === false ) {
    $_db_host = $_db_host . ':' . $_db_port;
}
define( 'DB_HOST', $_db_host );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

// ---------- URLs ----------
if ( getenv('RENDER_EXTERNAL_URL') ) {
    define( 'WP_HOME',    getenv('RENDER_EXTERNAL_URL') );
    define( 'WP_SITEURL', getenv('RENDER_EXTERNAL_URL') );
}

// ---------- Force HTTPS on Render (fixes too-many-redirects) ----------
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}
if ( isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

// ---------- Auth Keys & Salts ----------
// Generate at: https://api.wordpress.org/secret-key/1.1/salt/
define( 'AUTH_KEY',         getenv('WP_AUTH_KEY')         ?: 'change-me-local-auth-key'         );
define( 'SECURE_AUTH_KEY',  getenv('WP_SECURE_AUTH_KEY')  ?: 'change-me-local-secure-auth-key'  );
define( 'LOGGED_IN_KEY',    getenv('WP_LOGGED_IN_KEY')    ?: 'change-me-local-logged-in-key'    );
define( 'NONCE_KEY',        getenv('WP_NONCE_KEY')        ?: 'change-me-local-nonce-key'        );
define( 'AUTH_SALT',        getenv('WP_AUTH_SALT')        ?: 'change-me-local-auth-salt'        );
define( 'SECURE_AUTH_SALT', getenv('WP_SECURE_AUTH_SALT') ?: 'change-me-local-secure-auth-salt' );
define( 'LOGGED_IN_SALT',   getenv('WP_LOGGED_IN_SALT')   ?: 'change-me-local-logged-in-salt'   );
define( 'NONCE_SALT',       getenv('WP_NONCE_SALT')       ?: 'change-me-local-nonce-salt'       );

// ---------- Debug ----------
define( 'WP_DEBUG',         false );
define( 'WP_DEBUG_LOG',     false );
define( 'WP_DEBUG_DISPLAY', false );

// ---------- Bootstrap ----------
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
