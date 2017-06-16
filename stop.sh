#!/bin/bash
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH

echo "start...";

ps -ef|grep php|grep -v grep|cut -c 9-15|xargs kill -9
/usr/local/php/sbin/php-fpm

echo "end...";

ps aux |grep php
