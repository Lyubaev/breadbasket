#### Build PHP with ZTS

```sh
$ apt-get update > /dev/null
$ apt-get install -y \
git \
build-essential \
autoconf2.13 \
bison \
libedit-dev \
libxslt1-dev
```

```sh
mkdir -p /etc/php5/conf.d
mkdir ~/php-src
```

Fetch php-src (5.3.29)
```sh
$ cd ~/php-src
$ git remote add origin https://github.com/php/php-src.git
$ git fetch origin PHP-5.3.29:refs/remotes/origin/master
$ git checkout -b master origin/master
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
$ make 
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
$ pecl install pthreads
$ echo "extension=pthreads.so" > /etc/php5/conf.d/pthreads.ini
```

```sh
$ pecl install event-1.10.2
$ echo "extension=event.so" > /etc/php5/conf.d/event.ini
```
