@echo off
title MetaLink Development Server
color 0A
cls

echo ==========================================
echo        🚀  MetaLink Dev Server
echo ==========================================
echo.

:: Detect WiFi IPv4
for /f "tokens=14" %%a in ('ipconfig ^| findstr /i "IPv4"') do (
    set WIFI_IP=%%a
    goto :found
)

:found
set WIFI_IP=%WIFI_IP: =%

if "%WIFI_IP%"=="" (
    echo ❌ Could not detect WiFi IP.
    pause
    exit
)

set URL=http://%WIFI_IP%:9000

echo ✅ Detected IP: %WIFI_IP%
echo 🌐 Server URL: %URL%
echo.

echo Starting background services...
start "" php server.php
timeout /t 1 >nul
start "" php -S %WIFI_IP%:9000

echo.
echo Generating QR Code...
echo.

:: Open QR code in browser
start "" "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=%URL%"

echo.
echo ==========================================
echo  📱 Scan the QR Code to open on mobile
echo ==========================================
echo.
pause