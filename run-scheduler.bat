@echo off
REM ===================================================
REM Laravel Scheduler Runner for Windows
REM Run this every minute via Windows Task Scheduler
REM ===================================================

cd /d "C:\laragon\www\emulsis-web"
php artisan schedule:run >> storage\logs\scheduler.log 2>&1
