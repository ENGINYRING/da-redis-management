#!/bin/bash

set -x

# Create log directory
mkdir -p /usr/local/directadmin/plugins/redis_management/logs

# Create data directory
mkdir -p /usr/local/directadmin/plugins/redis_management/data

# Fix ownerships
chown -R diradmin.diradmin /usr/local/directadmin/plugins/redis_management
chown -R redis.redis /usr/local/directadmin/plugins/redis_management/data

# Fix permissions
chmod -R 0775 /usr/local/directadmin/plugins/redis_management/admin/*
chmod -R 0775 /usr/local/directadmin/plugins/redis_management/user/*

sed -i 's@/etc/redis.conf@/etc/redis/redis.conf@g' /usr/local/directadmin/plugins/redis_management/php/Templates/redis-instance.conf
sed -i 's/redis@/redis-server@/g' /usr/local/directadmin/plugins/redis_management/php/Controllers/RedisController.php

# Inject user_destroy_post script
if [ ! -f "/usr/local/directadmin/scripts/custom/user_destroy_post.sh" ]; then
    echo -e "#!/bin/bash" > /usr/local/directadmin/scripts/custom/user_destroy_post.sh
    chmod +x /usr/local/directadmin/scripts/custom/user_destroy_post.sh
fi
if [ ! "$(cat /usr/local/directadmin/scripts/custom/user_destroy_post.sh | grep redis_management)" ]; then
    echo -e '\n/usr/local/directadmin/plugins/redis_management/php/Hooks/DirectAdmin/userDestroyPost.php "$username"' >> /usr/local/directadmin/scripts/custom/user_destroy_post.sh
fi

# Make userDestroyPost.php script executable
chmod +x /usr/local/directadmin/plugins/redis_management/php/Hooks/DirectAdmin/userDestroyPost.php
