FROM pimcore/pimcore:php8.3-debug-latest

# Install PHP XSL extension
RUN set -eux; \
  build-install.sh; \
  apt-get install -y libxslt-dev; \
  docker-php-ext-install xsl;

# Install PHP SSH2 extension
RUN set -eux; \
  build-install.sh; \
  apt-get update; \
  apt-get install -y libssh2-1-dev pkg-config; \
  printf "\n" | pecl install ssh2-1.4.1; \
  docker-php-ext-enable ssh2; \
  apt-get clean; \
  rm -rf /var/lib/apt/lists/* /tmp/pear
