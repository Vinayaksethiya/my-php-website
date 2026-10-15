#!/bin/bash
# Custom entrypoint — start Apache first, then run WP setup

# Start the original WordPress entrypoint in background
docker-entrypoint.sh apache2-foreground &
APACHE_PID=$!

# Wait for Apache to be ready
echo "Waiting for Apache to start..."
sleep 10

# Run BehiveAds setup
/setup.sh

# Keep Apache running in foreground
wait $APACHE_PID
