@echo off
title Stocks Algo System

echo ===================================================
echo Starting Stocks Algo System...
echo ===================================================

:: 1. Start PHP Web Server (Background/New Window)
echo [1/3] Starting Web Server on http://localhost:8000...
start "Stocks Algo Server" /MIN php -S localhost:8000 -t public

:: 2. Open Dashboard in Browser
echo [2/3] Opening Dashboard...
timeout /t 2 >nul
start http://localhost:8000

:: 3. Start Trading Bot (Interactive Window)
echo [3/3] Starting Trading Bot...
echo.
echo NOTE: You can close the Server window to stop the website.
echo.

:: Pass arguments to run_bot if provided, else default to QQQ 1min
if "%~1"=="" (
    call run_bot.bat QQQ 1min
) else (
    call run_bot.bat %1 %2
)
