#!/bin/bash

cd ~

echo "Removing current Redis"
sudo apt-get remove redis-server

echo
echo "Downloading Redis 3.0"
wget -O redis.tar.gz http://download.redis.io/releases/redis-3.0.0.tar.gz || exit 1
tar -xzf redis.tar.gz || exit 1

echo
echo "Compiling & installing"
cd redis-*/
make && sudo make install || exit 1

echo
echo "Fix init script and configure"
sudo cp utils/redis_init_script /etc/init.d/redis_6379
sudo cp redis.conf /etc/redis/6379.conf
sudo sed -i -e 's/daemonize no/daemonize yes/g' /etc/redis/6379.conf
sudo sed -i -e 's/redis.pid/redis_6379.pid/g' /etc/redis/6379.conf
sudo sed -i -e 's/^logfile .*/logfile "\/var\/log\/redis_6379.log"/g' /etc/redis/6379.conf
sudo sed -i -e 's/^dir .*/dir \/var\/redis\/6379/g' /etc/redis/6379.conf
sudo mkdir -p /var/redis/6379

echo
echo "Starting server"
sudo /etc/init.d/redis_6379 start

echo
echo "Checking logs"
sudo cat /var/log/redis_6379.log
redis-cli ping > /dev/null || exit 1

echo
echo "Checking installation"
redis-cli info |grep version

cd $TRAVIS_BUILD_DIR
