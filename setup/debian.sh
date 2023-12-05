#!/bin/bash

set -x

apt-get update
apt-get install -y redis

systemctl stop redis-server.service
systemctl disable redis-server.service

chmod -R 0755 /etc/redis
cp -a redis.conf /etc/redis/redis.conf

# Create instances folder for redis instances
mkdir -p /etc/redis/instances

# Chown instances folder
chown -R redis.redis /etc/redis/instances

# Remove existing systemctl script
rm -f /lib/systemd/system/redis-server.service
rm -f /lib/systemd/system/redis-server@.service

# Copy new systemctl scripts
cp -a redis.service /lib/systemd/system/redis-server.service
cp -a redis@.service /lib/systemd/system/redis-server@.service

sed -i -e 's@/etc/redis/redis-%i.conf@/etc/redis/instances/%i.conf@g' /lib/systemd/system/redis-server@.service

# Reload systemctl daemons
systemctl daemon-reload

# Enable main service
systemctl enable redis-server.service

# Start main service
systemctl start redis-server.service

# Copy sudoers file
cp -a redis.sudoers /etc/sudoers.d/redis

sed -i -e 's/%sudo   ALL=(ALL:ALL) ALL/%sudo   ALL=(ALL) NOPASSWD: ALL/g' /etc/sudoers
sed -i -e 's/redis@/redis-server@/g' /etc/sudoers.d/redis

# Fix sudoers file permissions
chown root.root /etc/sudoers.d/redis