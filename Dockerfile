FROM php:8.2-apache

# Enable mod_rewrite (required for .htaccess routing)
RUN a2enmod rewrite

# Allow .htaccess overrides in the document root
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install PDO + MySQL driver
RUN docker-php-ext-install pdo pdo_mysql

# Copy application files
COPY . /var/www/html/

# Remove files that should not be served from this image
RUN rm -f /var/www/html/schema.sql \
           /var/www/html/setup.php \
           /var/www/html/docker-entrypoint.sh \
           /var/www/html/.gitignore \
           /var/www/html/Dockerfile \
           /var/www/html/docker-compose.yml

RUN chown -R www-data:www-data /var/www/html

# Custom entrypoint: waits for DB and seeds the first admin user if missing
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
