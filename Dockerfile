FROM wordpress:php8.2-apache

# ---------- System dependencies ----------
RUN apt-get update && apt-get install -y \
    less \
    curl \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# ---------- WP-CLI ----------
RUN curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# ---------- Apache ----------
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# ---------- wp-config.php ----------
COPY wp-config.php /var/www/html/wp-config.php

# ---------- Theme ----------
COPY habitat-leadgen/ /var/www/html/wp-content/themes/habitat-leadgen/

# ---------- Setup script ----------
COPY setup.sh /setup.sh
RUN chmod +x /setup.sh

# ---------- Entrypoint ----------
# Run setup THEN start Apache
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN chown -R www-data:www-data /var/www/html/wp-content/themes/habitat-leadgen/

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
