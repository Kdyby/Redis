#!/bin/bash

mkdir /tmp/build-phpredis && cd /tmp/build-phpredis || exit 1

wget -O phpredis.zip "https://github.com/phpredis/phpredis/archive/master.zip" && unzip phpredis.zip && cd phpredis-*/ || exit 1
echo

phpize && ./configure && make && make install || exit 1
echo

echo 'extension = redis.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

cd $TRAVIS_BUILD_DIR
