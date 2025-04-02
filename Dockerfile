FROM wordpress:latest

# Set the working directory
WORKDIR /var/www/html

RUN apt update && DEBIAN_FRONTEND=noninteractive apt install -y bash vim less curl unzip

# Copy your plugin to the WordPress plugins directory
COPY . /var/www/html/wp-content/plugins/fiberpay-payment-gateway

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/plugins && \
    chmod -R 755 /var/www/html/wp-content/plugins

# Expose port 80
EXPOSE 80

# Start WordPress
CMD ["apache2-foreground"]
