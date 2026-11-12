@echo off
cd /d "%~dp0"
echo InfoFluxes 2 is starting at http://localhost:8001  (close this window to stop it)
php artisan serve --port=8001
