# Prebuilt static ffmpeg/ffprobe (includes libmp3lame). Pinned version.
# Much faster than apt-get install ffmpeg, which pulls hundreds of MB of packages.
FROM mwader/static-ffmpeg:7.1 AS ffmpeg

FROM php:8.2-apache

# ffmpeg + ffprobe in /usr/bin so both "ffmpeg" and "/usr/bin/ffmpeg" keep working
COPY --from=ffmpeg /ffmpeg /usr/bin/ffmpeg
COPY --from=ffmpeg /ffprobe /usr/bin/ffprobe

# Install system dependencies (cached layer)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Configure PHP for large file uploads
RUN echo "upload_max_filesize = 500M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Update Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files (this layer changes most often)
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chmod -R 777 /var/www/html/uploads /var/www/html/logs

EXPOSE 80

CMD ["apache2-foreground"]
