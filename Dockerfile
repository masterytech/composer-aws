ARG VARIANT="8-cli"
FROM php:${VARIANT}

RUN curl -sSLf -o /usr/local/bin/install-php-extensions \
      https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
&& chmod +x /usr/local/bin/install-php-extensions \
&& install-php-extensions \
     xdebug-stable

RUN echo "xdebug.mode = debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN sed -ri 's/#( alias.+)/\1/' ~/.bashrc

RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
 && apt-get -y install --no-install-recommends docker.io git less vim unzip zip

RUN curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" \
 && unzip awscliv2.zip \
 && ./aws/install --bin-dir /usr/bin \
 && rm -rf awscliv2.zip aws

RUN curl -sS -o /tmp/composer-setup.php https://getcomposer.org/installer \
 && curl -sS -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
 && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
 && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer \
 && rm -rf /tmp/composer-setup.*

ENV PATH="${PATH}:./vendor/bin"
