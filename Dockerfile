FROM debian:10
LABEL Description="EvolutionLinux (swift) results statistic generator (php) running on Docker"

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils
RUN apt-get dist-upgrade -y
RUN apt-get install -y wget bison autoconf build-essential pkg-config git-core libzip-dev libxml2-dev libssl-dev libpng-dev unzip libfreetype6-dev

WORKDIR /root

RUN wget -q --show-progress --progress=bar:force https://github.com/php/php-src/archive/php-7.2.31.tar.gz
RUN tar --extract --gzip --file php-*
RUN rm php-*.tar.gz
RUN mv php-src-* php-src
WORKDIR /root/php-src

ADD https://git.archlinux.org/svntogit/packages.git/plain/trunk/freetype.patch?h=packages/php&id=a8e8e87f405e0631b2a4552656413735ebf9457c /tmp/freetype.patch
RUN patch -p1 -i /tmp/freetype.patch
RUN rm /tmp/freetype.patch

RUN ./buildconf --force
ENV CONFIGURE_STRING="--prefix=/etc/php7 --enable-mbstring --enable-zip --with-zlib --with-openssl --enable-zlib --without-sqlite3 --without-pdo-sqlite --disable-cgi --enable-opcache --with-config-file-path=/etc/php7/cli --with-config-file-scan-dir=/etc/php7/etc --enable-cli --with-tsrm-pthreads --enable-maintainer-zts --with-gd --with-png-dir --with-freetype-dir"
RUN ./configure $CONFIGURE_STRING
RUN make && make install
RUN ln -s /etc/php7/bin/php /usr/bin/php
RUN chmod o+x /etc/php7/bin/phpize
RUN chmod o+x /etc/php7/bin/php-config

RUN git clone https://github.com/krakjoe/pthreads.git

WORKDIR pthreads
RUN /etc/php7/bin/phpize
RUN ./configure --prefix='/etc/php7' --with-libdir='/lib/x86_64-linux-gnu' --enable-pthreads=shared --with-php-config='/etc/php7/bin/php-config'
RUN make && make install
RUN mkdir -p /etc/php7/cli/
RUN cp /root/php-src/php.ini-production /etc/php7/cli/php.ini
RUN echo "extension=pthreads.so" | tee -a /etc/php7/cli/php.ini
RUN echo "zend_extension=opcache.so" | tee -a /etc/php7/cli/php.ini

RUN wget -q --show-progress --progress=bar:force -O- https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN ln -s /usr/local/bin/composer /usr/bin/composer

ADD . /app
WORKDIR /app

RUN /usr/local/bin/composer install

ENTRYPOINT ["/bin/bash"]
#ENTRYPOINT ["php", "index.php"]