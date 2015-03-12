#### Build PHP with ZTS

```sh
$ apt-get update > /dev/null
$ apt-get install -y \
mcrypt \
libmcrypt-dev \
libedit-dev \
libxslt1-dev
```

```sh
$ ./buildconf --force
$ ./configure \
--prefix=/etc/php5 \
--with-config-file-scan-dir=/etc/php5/conf.d \
--disable-cgi \
--enable-maintainer-zts \
--enable-pcntl \
--enable-sockets \
--enable-zip \
--enable-inline-optimization \
--with-pear \
--with-libedit \
--with-readline \
--with-openssl \
--with-zlib \
--with-pcre-regex \
--with-libdir=/lib/x86_64-linux-gnu
```

```sh
$ make -j8
$ make install
```

Create links.
```sh
$ ln -s /etc/php5/bin/php        /usr/bin/php
$ ln -s /etc/php5/bin/pecl       /usr/bin/pecl
$ ln -s /etc/php5/bin/pear       /usr/bin/pear
$ ln -s /etc/php5/bin/phpize     /usr/bin/phpize
$ ln -s /etc/php5/bin/php-config /usr/bin/php-config
```

```sh
$ mkdir -p /etc/php5/conf.d
```

```sh
$ pecl install pthreads
```

```sh
$ echo "extension=pthreads.so" > /etc/php5/conf.d/pthreads.ini
```