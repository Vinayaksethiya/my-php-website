#!/bin/bash
# BehiveAds — WordPress auto-setup script
# Runs on container start, waits for DB, then sets up everything via WP-CLI.

# Do NOT exit on error — let each step try independently
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
        --admin_user="${WP_ADMIN_USER:-behiveads_admin}" \
        --admin_password="${WP_ADMIN_PASSWORD:-BehiveAds@2024!}" \
        --admin_email="${WP_ADMIN_EMAIL:-Behiveads@gmail.com}" \
        --skip-email
    echo "WordPress installed."
else
    echo "WordPress already installed — updating URLs and admin credentials..."
    wp option update siteurl "${RENDER_EXTERNAL_URL:-http://localhost:8080}" --allow-root --path=/var/www/html
    wp option update home    "${RENDER_EXTERNAL_URL:-http://localhost:8080}" --allow-root --path=/var/www/html
    # Update admin user credentials
    wp user update "${WP_ADMIN_USER:-behiveads_admin}" \
        --user_pass="${WP_ADMIN_PASSWORD:-BehiveAds@2024!}" \
        --user_email="${WP_ADMIN_EMAIL:-Behiveads@gmail.com}" \
        --allow-root --path=/var/www/html 2>/dev/null || \
    wp user update 1 \
        --user_login="${WP_ADMIN_USER:-behiveads_admin}" \
        --user_pass="${WP_ADMIN_PASSWORD:-BehiveAds@2024!}" \
        --user_email="${WP_ADMIN_EMAIL:-Behiveads@gmail.com}" \
        --allow-root --path=/var/www/html 2>/dev/null
    echo "Admin credentials updated."
fi

# ---------- Business details ----------
# Only fix phone if it is still the old Indian number or empty.
# If admin has changed it via Settings > BehiveAds, leave it alone.
echo "Checking business details..."
wp eval '
    $opts  = get_option("hlead_options", array());
    $phone = isset($opts["phone"]) ? $opts["phone"] : "";
    $is_old = (
        strpos($phone, "8305991192") !== false ||
        strpos($phone, "+91")        !== false ||
        trim($phone) === ""
    );
    if ($is_old) {
        $opts["phone"] = "(888) 888-8888";
        update_option("hlead_options", $opts);
        echo "Phone set to (888) 888-8888\n";
    } else {
        echo "Phone already set to: " . $phone . " — keeping admin value\n";
    }
    if (empty($opts["business"])) {
        $opts["business"] = "BehiveAds";
        update_option("hlead_options", $opts);
        echo "Business name set to BehiveAds\n";
    }
' --allow-root --path=/var/www/html
echo "Business details done."

# ---------- Ensure admin user exists with correct credentials (runs every deploy) ----------
echo "Setting up admin user..."
# Check if behiveads_admin exists
ADMIN_EXISTS=$(wp user get behiveads_admin --field=ID --allow-root --path=/var/www/html 2>/dev/null)
if [ -n "$ADMIN_EXISTS" ]; then
    # Update existing user password
    wp user update behiveads_admin \
        --user_pass="BehiveAds@2024!" \
        --user_email="Behiveads@gmail.com" \
        --allow-root --path=/var/www/html
    echo "Admin user updated: behiveads_admin"
else
    # Check if user ID 1 exists and rename it
    USER1=$(wp user get 1 --field=user_login --allow-root --path=/var/www/html 2>/dev/null)
    if [ -n "$USER1" ]; then
        wp user update 1 \
            --user_login="behiveads_admin" \
            --user_pass="BehiveAds@2024!" \
            --user_email="Behiveads@gmail.com" \
            --role=administrator \
            --allow-root --path=/var/www/html
        echo "Admin user renamed to behiveads_admin"
    else
        # Create fresh admin
        wp user create behiveads_admin Behiveads@gmail.com \
            --user_pass="BehiveAds@2024!" \
            --role=administrator \
            --allow-root --path=/var/www/html
        echo "Admin user created: behiveads_admin"
    fi
fi
echo "Admin setup done."
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

create_page "Plumbing"        "plumbing"        "page-plumbing.php"
create_page "Pest Control"     "pest-control"     "page-pest-control.php"
create_page "Auto Insurance"   "auto-insurance"   "page-auto-insurance.php"
create_page "Health Insurance" "health-insurance" "page-health-insurance.php"
create_page "Medicare"         "medicare"         "page-medicare.php"
create_page "Home Services"    "home-services"    "page-home-services.php"
create_page "Final Expense"    "final-expense"    "page-final-expense.php"
create_page "Water Services"   "water-services"   "page-water-services.php"

echo "All 7 pages done."

# ---------- Flush rewrite rules ----------
wp rewrite flush --hard --allow-root --path=/var/www/html

echo "BehiveAds setup complete!"
