@echo off
REM EcoRuta - Detiene las ventanas del sistema (API, Sync, Web).
echo Deteniendo EcoRuta-API, EcoRuta-Sync y EcoRuta-Web...
taskkill /FI "WINDOWTITLE eq EcoRuta-API" /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq EcoRuta-Sync" /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq EcoRuta-Web" /F >nul 2>&1
echo Listo.
pause
