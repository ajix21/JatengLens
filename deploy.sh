#!/bin/bash
set -e

echo "=== [1/4] Menarik kode terbaru dari GitHub ==="
git pull

echo "=== [2/4] Rebuild & restart container app (low-RAM) ==="
docker compose up -d --build app

echo "=== [3/4] Menjalankan migrasi database ==="
docker compose exec -T -u www-data app php artisan migrate --force

echo "=== [4/4] Optimasi cache Laravel ==="
docker compose exec -T -u www-data app php artisan optimize

echo "=== Selesai! Status container & pemakaian RAM ==="
docker stats --no-stream sociowatch_app sociowatch_db 2>/dev/null || docker ps --filter "name=sociowatch"
