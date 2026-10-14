FROM wordpress:php8.2-apache

# Copy the theme into the WordPress themes directory
COPY . /var/www/html/wp-content/themes/habitat-leadgen/

# Set correct file permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/themes/habitat-leadgen/

EXPOSE 80
