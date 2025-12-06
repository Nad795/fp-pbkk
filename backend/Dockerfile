# Laravel Backend Dockerfile
FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # Build tools
    build-essential \
    git \
    curl \
    wget \
    # Archive utilities
    zip unzip \
    p7zip-full \
    # PDF tools
    poppler-utils \
    # File tools
    file \
    libmagic1 \
    # ClamAV (virus scanning)
    clamav \
    clamav-daemon \
    # PHP extensions
    libzip-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install zip curl \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /tmp/*

# Initialize ClamAV (virus definitions)
RUN mkdir -p /var/lib/clamav /var/run/clamav /var/log/clamav && \
    chmod 777 /var/run/clamav /var/log/clamav && \
    touch /var/log/clamav/clamav.log && \
    chmod 666 /var/log/clamav/clamav.log && \
    echo "DatabaseMirror database.clamav.net" > /etc/clamav/freshclam.conf && \
    sed -i 's/^User clamav/User root/' /etc/clamav/clamd.conf && \
    sed -i 's/^LocalSocketMode 666/LocalSocketMode 777/' /etc/clamav/clamd.conf && \
    freshclam --quiet 2>/dev/null || echo "ClamAV definitions will be updated on first run"

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Leverage docker cache: copy composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies (deterministic from lock file)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
 || composer update --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy the rest of the source code
COPY . .

# Create SQLite database directory
RUN mkdir -p /var/www/html/database && \
    touch /var/www/html/database/database.sqlite && \
    chmod 664 /var/www/html/database/database.sqlite

# Set permissions
RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views && \
    chmod -R 775 /var/www/html/storage && \
    chmod -R 775 /var/www/html/bootstrap/cache

# Copy and set entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create startup script to start services
RUN echo '#!/bin/bash' > /usr/local/bin/start-services.sh && \
    echo 'set -e' >> /usr/local/bin/start-services.sh && \
    echo '' >> /usr/local/bin/start-services.sh && \
    echo '# Ensure ClamAV directories exist with proper permissions' >> /usr/local/bin/start-services.sh && \
    echo 'mkdir -p /var/run/clamav /var/log/clamav' >> /usr/local/bin/start-services.sh && \
    echo 'chmod 755 /var/run/clamav /var/log/clamav' >> /usr/local/bin/start-services.sh && \
    echo 'touch /var/log/clamav/clamav.log' >> /usr/local/bin/start-services.sh && \
    echo 'chmod 666 /var/log/clamav/clamav.log' >> /usr/local/bin/start-services.sh && \
    echo '' >> /usr/local/bin/start-services.sh && \
    echo '# Start ClamAV daemon in background' >> /usr/local/bin/start-services.sh && \
    echo 'echo "Starting ClamAV daemon..."' >> /usr/local/bin/start-services.sh && \
    echo 'clamd -c /etc/clamav/clamd.conf > /var/log/clamav/clamav.log 2>&1 &' >> /usr/local/bin/start-services.sh && \
    echo 'sleep 3' >> /usr/local/bin/start-services.sh && \
    echo '' >> /usr/local/bin/start-services.sh && \
    echo 'exec "$@"' >> /usr/local/bin/start-services.sh && \
    chmod +x /usr/local/bin/start-services.sh

# Expose port
EXPOSE 8000

# Health check for Laravel (lightweight)
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -sf http://localhost:8000/api/health | grep -q '"status":"ok"' || exit 1

# Set entrypoint and start PHP built-in server with proper routing
ENTRYPOINT ["start-services.sh", "docker-entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
