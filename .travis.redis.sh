#!/bin/bash

mkdir build && cd build || exit 1

wget -O phpredis.zip "https://github.com/nicolasff/phpredis/archive/2.2.2.zip" && unzip phpredis.zip && cd phpredis-2.2.2/ || exit 1
echo

phpize && ./configure && make && sudo make install || exit 1
echo

echo 'extension = redis.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
