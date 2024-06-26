ARG VARIANT="8-cli"

FROM php:${VARIANT}

ARG USERNAME=${USERNAME}
ARG USER_UID=1000
ARG USER_GID=1000

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

# Install xdebug

RUN curl -sSLf -o /usr/local/bin/install-php-extensions \
      https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
&& chmod +x /usr/local/bin/install-php-extensions \
&& install-php-extensions \
     xdebug-stable

RUN echo "xdebug.mode = debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install AWSCLI

RUN curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" \
 && unzip awscliv2.zip \
 && ./aws/install \
 && rm -rf awscliv2.zip aws

# Install AWSCLI deps (groff for 'help' command, pip + git-remote-codecommit)

RUN apt-get update \
 && apt-get install -y groff python3-pip \
 && apt-get autoremove -y && rm -rf /var/lib/apt/lists/* \
 && pip install git-remote-codecommit

# Install composer

RUN curl -sS -o /tmp/composer-setup.php https://getcomposer.org/installer \
 && curl -sS -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
 && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
 && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer \
 && rm -rf /tmp/composer-setup.*

ENV COMPOSER_DISABLE_XDEBUG_WARN=1
ENV PATH="${PATH}:./vendor/bin"

# Install phpdoc

RUN curl -sSL -o /usr/local/bin/phpDocumentor.phar https://github.com/phpDocumentor/phpDocumentor/releases/download/v3.3.1/phpDocumentor.phar \
 && printf '#!/bin/sh\nphp -d "error_reporting=E_ALL\&~E_DEPRECATED" /usr/local/bin/phpDocumentor.phar "$@"\n' > /usr/local/bin/phpdoc \
 && chmod 755 /usr/local/bin/phpDocumentor.phar /usr/local/bin/phpdoc
