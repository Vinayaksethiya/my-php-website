#!/bin/bash
# BehiveAds — WordPress auto-setup script
# Runs on container start, waits for DB, then sets up everything via WP-CLI.

set -e

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

# ---------- Run WordPress setup if not installed ----------
if ! wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "Installing WordPress..."
    wp core install \
        --allow-root \
        --path=/var/www/html \
        --url="${RENDER_EXTERNAL_URL:-http://localhost:8080}" \
        --title="BehiveAds" \
        --admin_user="${WP_ADMIN_USER:-admin}" \
        --admin_password="${WP_ADMIN_PASSWORD:-Admin@1234}" \
        --admin_email="${WP_ADMIN_EMAIL:-admin@behiveads.com}" \
        --skip-email
    echo "WordPress installed."
else
    echo "WordPress already installed — updating URLs..."
    wp option update siteurl "${RENDER_EXTERNAL_URL:-http://localhost:8080}" --allow-root --path=/var/www/html
    wp option update home    "${RENDER_EXTERNAL_URL:-http://localhost:8080}" --allow-root --path=/var/www/html
fi

# ---------- Activate theme ----------
echo "Activating theme..."
wp theme activate habitat-leadgen --allow-root --path=/var/www/html
echo "Theme activated."

# ---------- Set permalinks ----------
echo "Setting permalinks..."
wp rewrite structure '/%postname%/' --allow-root --path=/var/www/html
wp rewrite flush --hard --allow-root --path=/var/www/html
echo "Permalinks set."

# ---------- Create landing pages ----------
echo "Creating landing pages..."

create_page() {
    local title="$1"
    local slug="$2"
    local template="$3"

    existing=$(wp post list --post_type=page --name="$slug" --field=ID --allow-root --path=/var/www/html 2>/dev/null | head -1)

    if [ -n "$existing" ]; then
        echo "Page '$title' already exists (ID: $existing) — updating template."
        wp post meta update "$existing" _wp_page_template "$template" --allow-root --path=/var/www/html
    else
        id=$(wp post create \
            --post_type=page \
            --post_status=publish \
            --post_title="$title" \
            --post_name="$slug" \
            --field=ID \
            --allow-root \
            --path=/var/www/html)
        wp post meta update "$id" _wp_page_template "$template" --allow-root --path=/var/www/html
        echo "Created '$title' (ID: $id) with template $template"
    fi
}

create_page "Pest Control"     "pest-control"     "page-pest-control.php"
create_page "Auto Insurance"   "auto-insurance"   "page-auto-insurance.php"
create_page "Health Insurance" "health-insurance" "page-health-insurance.php"
create_page "Medicare"         "medicare"         "page-medicare.php"
create_page "Home Services"    "home-services"    "page-home-services.php"
create_page "Final Expense"    "final-expense"    "page-final-expense.php"
create_page "Water Services"   "water-services"   "page-water-services.php"

echo "All pages created."

# ---------- Flush rewrite rules ----------
wp rewrite flush --hard --allow-root --path=/var/www/html

echo "BehiveAds setup complete!"
