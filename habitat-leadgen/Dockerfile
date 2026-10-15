FROM wordpress:php8.2-apache

# Enable mod_rewrite for WordPress pretty permalinks
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy the theme into the WordPress themes directory
COPY . /var/www/html/wp-content/themes/habitat-leadgen/

# Set correct file permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/themes/habitat-leadgen/

EXPOSE 80
