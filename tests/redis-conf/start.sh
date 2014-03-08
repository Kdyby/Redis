#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "Starting redis-server on port 6380"
redis-server $DIR/6380.conf

echo "Starting redis-server on port 6381"
redis-server $DIR/6381.conf

echo "Starting redis-server on port 6382"
redis-server $DIR/6382.conf

echo "Starting redis-server on port 6383"
redis-server $DIR/6383.conf

echo "Starting redis-server on port 6384"
redis-server $DIR/6384.conf

echo "Starting redis-server on port 6385"
redis-server $DIR/6385.conf

echo "Starting redis-server on port 6386"
redis-server $DIR/6386.conf

echo "Starting redis-server on port 6387"
redis-server $DIR/6387.conf

echo "Starting redis-server on port 6388"
redis-server $DIR/6388.conf
