#!/bin/bash
# Custom entrypoint — start Apache first, then run WP setup

# Start the original WordPress entrypoint in background
docker-entrypoint.sh apache2-foreground &
APACHE_PID=$!

echo "Waiting for Apache to start..."
sleep 15

# Run main setup
/setup.sh

# Run page creation separately to ensure all pages exist
/create-pages.sh

# Keep Apache running in foreground
wait $APACHE_PID
