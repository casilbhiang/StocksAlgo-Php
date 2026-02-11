
# Use an official PHP runtime as a parent image
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Python dependencies
# Break system packages barrier for Docker
RUN pip3 install --break-system-packages -r ml/requirements.txt

# Make entrypoint executable
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose the port the app runs on
EXPOSE 8000

# Entrypoint runs the bot
ENTRYPOINT ["docker-entrypoint.sh"]
