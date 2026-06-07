#!/usr/bin/env sh
set -eu

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ "${WAIT_FOR_DB:-true}" != "false" ]; then
  php -r '
    $host = getenv("DB_HOST") ?: "postgres";
    $port = getenv("DB_PORT") ?: "5432";
    $db = getenv("DB_DATABASE") ?: "giayohoho";
    $user = getenv("DB_USERNAME") ?: "giayohoho";
    $pass = getenv("DB_PASSWORD") ?: "giayohoho";
    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

    for ($i = 1; $i <= 60; $i++) {
        try {
            new PDO($dsn, $user, $pass);
            echo "Database is ready.\n";
            exit(0);
        } catch (Throwable $e) {
            echo "Waiting for database ({$i}/60)...\n";
            sleep(1);
        }
    }

    fwrite(STDERR, "Database is not reachable.\n");
    exit(1);
  '
fi

php artisan config:clear --ansi
php artisan migrate --force --ansi

if [ "${RUN_SEEDERS:-true}" = "true" ]; then
  php artisan db:seed --force --ansi
fi

php artisan storage:link --ansi || true

exec "$@"
