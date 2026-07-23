FROM php:8.4-fpm

WORKDIR /var/www/html

# PHP packages
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    gnupg \
    ca-certificates \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libonig-dev \
    default-mysql-client \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        intl \
        bcmath \
        gd \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Node.js 22 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Non-root user
RUN useradd -ms /bin/bash armorhub

USER armorhub

EXPOSE 9000

CMD ["php-fpm"]