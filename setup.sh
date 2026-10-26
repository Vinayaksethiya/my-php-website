#!/bin/bash
# BehiveAds — WordPress auto-setup script
# Runs on container start, waits for DB, then sets up everything via WP-CLI.
set +e

# ---------- Install WP-CLI ----------
if ! command -v wp &> /dev/null; then
    curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
fi

# ---------- Wait for database ----------
echo "Waiting for database..."
for i in $(seq 1 30); do
    if wp db check --allow-root --path=/var/www/html 2>/dev/null; then
        echo "Database is ready."
        break
    fi
    echo "Attempt $i/30 — DB not ready yet, retrying in 5s..."
    sleep 5
done

# ---------- WordPress install ----------
if ! wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "Installing WordPress..."
    wp core install \
        --allow-root \
        --path=/var/www/html \
        --url="${RENDER_EXTERNAL_URL:-http://localhost:8080}" \
        --title="BehiveAds" \
        --admin_user="admin" \
        --admin_password="Admin@1234" \
        --admin_email="Behiveads@gmail.com" \
        --skip-email
    echo "WordPress installed."
fi

# Always update URLs
wp option update siteurl "${RENDER_EXTERNAL_URL:-http://localhost:8080}" --allow-root --path=/var/www/html
wp option update home    "${RENDER_EXTERNAL_URL:-http://localhost:8080}" --allow-root --path=/var/www/html

# ---------- Force admin password reset — ALWAYS runs every deploy ----------
echo "Force resetting admin password..."
wp user update 1 --user_pass="BehiveAds@2024!" --allow-root --path=/var/www/html 2>/dev/null
wp user update 1 --user_login="admin" --allow-root --path=/var/www/html 2>/dev/null
echo "Admin: username=admin password=BehiveAds@2024!"

# ---------- Business details ----------
echo "Checking business details..."
wp eval '
    $opts  = get_option("hlead_options", array());
    $phone = isset($opts["phone"]) ? $opts["phone"] : "";
    $is_old = (strpos($phone, "8305991192") !== false || strpos($phone, "+91") !== false || trim($phone) === "");
    if ($is_old) { $opts["phone"] = "(888) 888-8888"; update_option("hlead_options", $opts); echo "Phone set\n"; }
    if (empty($opts["business"])) { $opts["business"] = "BehiveAds"; update_option("hlead_options", $opts); }
' --allow-root --path=/var/www/html

# ---------- Ensure leads table exists ----------
echo "Ensuring leads table exists..."
wp eval '
global $wpdb;
$table = $wpdb->prefix . "leadgen_leads";
$charset = $wpdb->get_charset_collate();
$sql = "CREATE TABLE IF NOT EXISTS $table (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_slug     VARCHAR(80)     NOT NULL DEFAULT \"\",
    full_name     VARCHAR(160)    NOT NULL DEFAULT \"\",
    phone         VARCHAR(40)     NOT NULL DEFAULT \"\",
    email         VARCHAR(160)    NOT NULL DEFAULT \"\",
    zip           VARCHAR(20)     NOT NULL DEFAULT \"\",
    topic         VARCHAR(120)    NOT NULL DEFAULT \"\",
    details       TEXT                     DEFAULT NULL,
    ip_address    VARCHAR(45)     NOT NULL DEFAULT \"\",
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset;";
require_once ABSPATH . "wp-admin/includes/upgrade.php";
dbDelta($sql);
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "Leads table OK — $count leads stored\n";
' --allow-root --path=/var/www/html
echo "Leads table checked."

# ---------- Activate theme ----------
echo "Activating theme..."
wp theme activate habitat-leadgen --allow-root --path=/var/www/html
echo "Theme activated."

# ---------- Set permalinks ----------
wp rewrite structure '/%postname%/' --allow-root --path=/var/www/html
wp rewrite flush --hard --allow-root --path=/var/www/html
echo "Permalinks set."

# ---------- Create / ensure all landing pages ----------
echo "Creating/verifying all landing pages..."

create_page() {
    local title="$1"
    local slug="$2"
    local template="$3"
    local existing
    existing=$(wp post list --post_type=page --name="$slug" --field=ID --allow-root --path=/var/www/html 2>/dev/null | tr -d '[:space:]' | head -c 10)
    if [ -n "$existing" ] && [ "$existing" -gt 0 ] 2>/dev/null; then
        wp post update "$existing" --post_status=publish --allow-root --path=/var/www/html 2>/dev/null
        wp post meta update "$existing" _wp_page_template "$template" --allow-root --path=/var/www/html
        echo "✔ '$title' exists (ID:$existing) — template updated"
    else
        local id
        id=$(wp post create \
            --post_type=page \
            --post_status=publish \
            --post_title="$title" \
            --post_name="$slug" \
            --field=ID \
            --allow-root \
            --path=/var/www/html 2>/dev/null)
        if [ -n "$id" ]; then
            wp post meta update "$id" _wp_page_template "$template" --allow-root --path=/var/www/html
            echo "✔ Created '$title' (ID:$id)"
        else
            echo "✘ Failed to create '$title'"
        fi
    fi
}

create_page "Roofing"                 "roofing"              "page-roofing.php"
create_page "Plumbing"                "plumbing"             "page-plumbing.php"
create_page "Pest Control"            "pest-control"         "page-pest-control.php"
create_page "Pest Control No Form"    "pest-control-info"    "page-pest-control-no-form.php"
create_page "Auto Insurance"          "auto-insurance"       "page-auto-insurance.php"
create_page "Health Insurance"        "health-insurance"     "page-health-insurance.php"
create_page "Medicare"                "medicare"             "page-medicare.php"
create_page "Home Services"           "home-services"        "page-home-services.php"
create_page "Final Expense"           "final-expense"        "page-final-expense.php"
create_page "Final Expense 2"        "final-expense-2"      "page-final-expense-2.php"
create_page "Water Services"          "water-services"       "page-water-services.php"

echo "All pages verified."

# ---------- Final rewrite flush + force .htaccess ----------
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
wp rewrite flush --hard --allow-root --path=/var/www/html
echo "BehiveAds setup complete!"
