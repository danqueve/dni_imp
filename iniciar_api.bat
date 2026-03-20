@echo off
title API cuitonline - Node.js
color 0A
echo.
echo  ==========================================
echo   API cuitonline - Servidor Node.js
echo  ==========================================
echo.

cd /d "%~dp0"

where node >nul 2>&1
if %errorlevel% neq 0 (
  echo  [ERROR] Node.js no encontrado. Instala desde https://nodejs.org
  pause
  exit /b 1
)

where npm >nul 2>&1
if %errorlevel% neq 0 (
  echo  [ERROR] npm no encontrado.
  pause
  exit /b 1
)

if not exist "node_modules\express-rate-limit" (
  echo  Instalando dependencias...
  npm install
  if %errorlevel% neq 0 (
    echo  [ERROR] Fallo en npm install. Revisa la conexion a internet.
    pause
    exit /b 1
  )
  echo.
)

if not exist ".env" (
  if exist ".env.example" (
    echo  [INFO] Creando .env desde .env.example...
    copy .env.example .env >nul
    echo  [INFO] Edita el archivo .env con tus credenciales antes de continuar.
    echo.
  )
)

echo  Iniciando servidor en http://localhost:3000
echo  Presiona CTRL+C para detenerlo.
echo.

node api_server.js
if %errorlevel% neq 0 (
  echo.
  echo  [ERROR] El servidor cerro con error %errorlevel%
)
pause
