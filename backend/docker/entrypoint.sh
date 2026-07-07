#!/bin/sh
set -e

php artisan migrate --force
php artisan passport:keys

CLIENT_COUNT=$(php artisan tinker --execute="echo \DB::table('oauth_personal_access_clients')->count();" 2>/dev/null | tail -1)
if [ "$CLIENT_COUNT" = "0" ]; then
  php artisan passport:client --personal --name="ZIA Personal Access Client" --provider=users
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
