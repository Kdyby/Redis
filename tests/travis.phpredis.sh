#!/bin/bash

mkdir ~/build-phpredis && cd ~/build-phpredis || exit 1

wget -O phpredis.zip "https://github.com/nicolasff/phpredis/archive/master.zip" && unzip phpredis.zip && cd phpredis-master/ || exit 1
echo

phpize && ./configure && make && sudo make install || exit 1
echo

echo 'extension = redis.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

cd $TRAVIS_BUILD_DIR
