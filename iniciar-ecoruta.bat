@echo off
REM EcoRuta - Inicializador del sistema (doble clic).
REM Levanta MySQL, API local, Sync Agent y Web local, cada uno en su ventana.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar-ecoruta.ps1" %*
if errorlevel 1 pause
