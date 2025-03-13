# Run the default WordPress entrypoint
docker-entrypoint.sh apache2 &

# Wait for WordPress to initialize
sleep 10

# Check if user already exists
if ! wp user list --allow-root | grep -q "admin@example.com"; then
  # Create a new WordPress admin user
  wp user create admin admin@example.com --role=administrator --user_pass=admin_password --allow-root
fi

# Bring Apache to the foreground
wait
