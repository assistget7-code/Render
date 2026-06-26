FROM php:8.2-apache

# Copy all project files into the Apache document root
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/ && chmod -R 755 /var/www/html/

EXPOSE 80
