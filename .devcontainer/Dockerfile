ARG VARIANT="5.6-cli"

FROM php:${VARIANT}

ARG USERNAME=${USERNAME}
ARG USER_UID=1000
ARG USER_GID=1000

# Fix outdated debian package repo references

RUN sed -i ' \
      s/deb.debian.org/archive.debian.org/g; \
      s/security.debian.org/archive.debian.org/g; \
      /stretch-updates/d; \
    ' /etc/apt/sources.list

# Install tools

RUN apt-get update && apt-get -y install \
     git \
     less \
     sudo \
     unzip \
     vim \
     zip \
 && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

# Create build user

RUN groupadd --gid ${USER_GID} ${USERNAME} \
 && useradd --uid ${USER_UID} --gid ${USER_GID} -m ${USERNAME} -s /bin/bash

# Make dev user superuser

RUN echo "${USERNAME} ALL=(root) NOPASSWD:ALL" > /etc/sudoers.d/dev-user \
 && chmod 0400 /etc/sudoers.d/dev-user

# Enable bash aliases

RUN sed -ri 's/#( *alias l.+)/\1/' /home/${USERNAME}/.bashrc

 # Install xdebug extension.

# TODO: Currently, there is a bug in the official php:5.6-fpm docker image which
#       causes Xdebug to not work correctly when installed via pecl. The
#       workaround is to disable compiler optimizations. This can be probably be
#       taken out after we finish migrating to php 7.2. See the details here:
#
#         https://github.com/docker-library/php/issues/133#issuecomment-304933175
#         https://bugs.php.net/bug.php?id=73545

#RUN pecl install xdebug-2.5.5 \
# && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

RUN cd /tmp && pecl download xdebug-2.5.5 \
 && tar zxf xdebug-2.5.5.tgz && cd xdebug-2.5.5 \
 && phpize && ./configure --enable-xdebug && make CFLAGS=" -g -O0" && make install \
 && rm -rf /tmp/xdebug-2.5.5*

#RUN echo "xdebug.mode = debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install AWSCLI

RUN apt-get update \
 && apt-get install -y awscli \
 && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

# Install composer utility

RUN curl -sS -o /tmp/composer-setup.php https://getcomposer.org/installer \
 && curl -sS -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
 && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
 && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --version=2.2.24 \
 && rm -rf /tmp/composer-setup.*

ENV COMPOSER_DISABLE_XDEBUG_WARN=1
ENV PATH="${PATH}:./vendor/bin"

# Install phpdoc

RUN curl -sSL -o /usr/local/bin/phpDocumentor.phar https://github.com/phpDocumentor/phpDocumentor/releases/download/v3.3.1/phpDocumentor.phar \
 && printf '#!/bin/sh\nphp -d "error_reporting=E_ALL\&~E_DEPRECATED" /usr/local/bin/phpDocumentor.phar "$@"\n' > /usr/local/bin/phpdoc \
 && chmod 755 /usr/local/bin/phpDocumentor.phar /usr/local/bin/phpdoc

USER "${USERNAME}"