@echo off
REM Arranca el Sync Agent. Crea un acceso directo a este .bat en shell:startup para auto-inicio.
cd /d "%~dp0"
if not exist .env (
  echo FALTA .env - copialo de .env.example y completalo.
  pause
  exit /b 1
)
node src\server.js
pause
