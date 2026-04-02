FROM php:8.3-fpm

# Arguments defined in docker-compose.yml
ARG user=laravel
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    autoconf \
    g++ \
    make \
    pkg-config \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zlib1g-dev \
    libssl-dev \
    zip \
    unzip \
    nano \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# - sockets: required by RoadRunner/Temporal PHP SDK worker runtime
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Temporal Client required extensions
# - grpc: required by Temporal PHP SDK client
# - protobuf: recommended for performance
RUN pecl install grpc protobuf && docker-php-ext-enable grpc protobuf

# Install Xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory permissions
COPY --chown=$user:$user . /var/www/html

# Change current user to $user
USER $user

EXPOSE 9000
CMD ["php-fpm"]
