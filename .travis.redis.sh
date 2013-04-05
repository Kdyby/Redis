#!/bin/bash

mkdir build && cd build || exit 1
git clone --depth 1 git://github.com/nicolasff/phpredis.git && cd phpredis || exit 1
phpize && ./configure && make && sudo make install && cd .. || exit 1

echo 'extension = redis.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
