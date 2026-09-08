#!/bin/sh
# Runs after serversideup's 50-laravel-automations.sh (which already ran
# `php artisan migrate --force` and cached config/routes/views).

ARTISAN="php /var/www/html/artisan"

# Seed only into an empty database: dev users + the legacy import.
# The seeders are idempotent but slow, so don't re-run them on every deploy.
USERS=$($ARTISAN tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n 1)
if [ "$USERS" = "0" ]; then
    echo "Empty database detected — running db:seed"
    $ARTISAN db:seed --force
fi

# Pantone reference book — its seeder exits instantly once populated,
# so this also backfills databases seeded before the table existed.
$ARTISAN db:seed --class=PantoneColourSeeder --force

# Laravel scheduler in the same container (airtable:sync-orders every 15 min).
# Needs an always-on instance; on a free plan it sleeps with the service.
$ARTISAN schedule:work >/dev/null 2>&1 &
