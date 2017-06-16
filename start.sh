#!/bin/bash
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH

echo "start..."

/usr/local/php/bin/php /web/wuhan/workerman/bin/start_register.php start &
/usr/local/php/bin/php /web/wuhan/workerman/bin/start_gateway.php start &
/usr/local/php/bin/php /web/wuhan/workerman/bin/start_businessworker.php start &

echo "end..."

ps aux |grep php
