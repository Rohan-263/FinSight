@echo off
cd public

set DB_HOST=127.0.0.1
set DB_PORT=5432
set DB_NAME=finsight
set DB_USER=postgres
set DB_PASSWORD=postgres
set COOKIE_SECURE=false

php -S localhost:8000
pause