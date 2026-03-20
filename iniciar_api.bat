@echo off
title API cuitonline - Node.js
color 0A
echo.
echo  ==========================================
echo   API cuitonline - Servidor Node.js
echo  ==========================================
echo.
echo  Iniciando servidor en http://localhost:3000
echo  Presiona CTRL+C para detenerlo.
echo.

cd /d "%~dp0"

where node >nul 2>&1
if %errorlevel% neq 0 (
  echo  [ERROR] Node.js no encontrado. Instala desde https://nodejs.org
  pause
  exit /b 1
)

if not exist "node_modules\cuitonline" (
  echo  Instalando dependencias...
  npm install cuitonline express
  echo.
)

node api_server.js
pause