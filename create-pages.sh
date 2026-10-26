# Updated: 2026-10-26 17:23:22
#!/bin/bash
# Direct page creation script — runs independently of setup.sh
WP="wp --allow-root --path=/var/www/html"

create_page() {
    local title="$1"
    local slug="$2"  
    local template="$3"
    
    local existing=$($WP post list --post_type=page --name="$slug" --field=ID 2>/dev/null | tr -d '[:space:]')
    
    if [ -n "$existing" ] && [ "$existing" -gt 0 ] 2>/dev/null; then
        $WP post meta update "$existing" _wp_page_template "$template" 2>/dev/null
        $WP post update "$existing" --post_status=publish 2>/dev/null
        echo "UPDATED: $slug (ID:$existing)"
    else
        local id=$($WP post create \
            --post_type=page \
            --post_status=publish \
            --post_title="$title" \
            --post_name="$slug" \
            --field=ID 2>/dev/null)
        if [ -n "$id" ] && [ "$id" -gt 0 ] 2>/dev/null; then
            $WP post meta update "$id" _wp_page_template "$template" 2>/dev/null
            echo "CREATED: $slug (ID:$id)"
        else
            echo "FAILED: $slug"
        fi
    fi
}

create_page "Final Expense 2"       "final-expense-2"      "page-final-expense-2.php"
create_page "Plumbing"              "plumbing"             "page-plumbing.php"
create_page "Roofing"               "roofing"              "page-roofing.php"
create_page "Pest Control No Form"  "pest-control-info"    "page-pest-control-no-form.php"

$WP rewrite flush --hard 2>/dev/null
echo "Done!"

