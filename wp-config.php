<?php
/**
 * WordPress configuration — reads from environment variables
 * so Render / Docker can inject values without rebuilding.
 */

// ** Database settings ** //
define( 'DB_NAME',     getenv('WORDPRESS_DB_NAME')     ?: getenv('DB_NAME')     ?: 'leadgen_wp' );
define( 'DB_USER',     getenv('WORDPRESS_DB_USER')     ?: getenv('DB_USER')     ?: 'wpuser' );
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'wppassword' );
define( 'DB_HOST',     getenv('WORDPRESS_DB_HOST')     ?: getenv('DB_HOST')     ?: 'db' );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

// ** Authentication keys and salts ** //
// Generate fresh ones at: https://api.wordpress.org/secret-key/1.1/salt/
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

// ** WordPress URLs ** //
if ( getenv('RENDER_EXTERNAL_URL') ) {
    define( 'WP_HOME',    getenv('RENDER_EXTERNAL_URL') );
    define( 'WP_SITEURL', getenv('RENDER_EXTERNAL_URL') );
}

// ** Debug (disable on production) ** //
define( 'WP_DEBUG', false );

// Absolute path to the WordPress directory
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
