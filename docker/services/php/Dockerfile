FROM php:8.5-fpm

ARG WWWUSERID

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libonig-dev \
    libzip-dev \
    libwebp-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libxpm-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    sudo \
    libicu-dev \
    ghostscript \
    qpdf \
    imagemagick \
    libmagickwand-dev \
    libmagickcore-dev \
    mc \
    pkg-config \
    bash-completion \
     && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
     && apt-get install -y nodejs \
     && apt-get clean && rm -rf /var/lib/apt/lists/*

#RUN pecl install imagick \
#    && docker-php-ext-enable imagick


# Install extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl



RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl
RUN docker-php-ext-configure gd --enable-gd --with-freetype=/usr/include/ --with-webp=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd


#RUN pecl install xdebug  \
#    && pecl install --onlyreqdeps --force redis
#RUN docker-php-ext-enable xdebug  \
#    && docker-php-ext-enable redis

# Install Composer from the official image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Create new user for php-fpm with pass "123"
RUN useradd -m -G www-data,sudo -u ${WWWUSERID} -U fpm_user \
    && usermod --password "\$1\$1rfE38aS\$K4LMKVFSEB80PPMhbLHsG0" fpm_user \
    && usermod -a -G sudo fpm_user

# Change php-fpm pool owner to new user "fpm_user"
RUN (echo 'user = fpm_user' && echo 'group = fpm_user';) >> "/usr/local/etc/php-fpm.d/zz-docker.conf"

# Copy php config local-local-php.ini
COPY ./docker/services/php/local-php.ini /usr/local/etc/php/conf.d/local-php.ini

# Copy existing application directory contents
COPY . /var/www

USER fpm_user

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
