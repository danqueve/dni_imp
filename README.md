# Consulta DNI Argentina

Sistema para consultar datos de personas argentinas por DNI usando [cuitonline.com](https://cuitonline.com), con historial en MySQL.

## Stack

- **Frontend/Backend**: PHP + HTML/CSS/JS (WAMP)
- **API**: Node.js + Express
- **Base de datos**: MySQL

## Requisitos

- [WAMP](https://wampserver.com/) (Apache + PHP + MySQL)
- [Node.js](https://nodejs.org/) 18+

## Instalación

```bash
# 1. Instalar dependencias
npm install

# 2. Copiar configuración
cp .env.example .env
# Editar .env con tus credenciales de BD

# 3. Crear base de datos en MySQL
CREATE DATABASE consultas_dni;
USE consultas_dni;
CREATE TABLE personas (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  dni             VARCHAR(8)   NOT NULL UNIQUE,
  nombre          VARCHAR(200),
  cuit_cuil       VARCHAR(15),
  tipo_persona    VARCHAR(50),
  genero          VARCHAR(20),
  nacionalidad    VARCHAR(100),
  localidad       VARCHAR(200),
  empleador       VARCHAR(200),
  ip_consulta     VARCHAR(45),
  fecha_consulta  DATETIME DEFAULT NOW()
);

# 4. Iniciar la API Node.js
npm start
# o en desarrollo:
npm run dev

# 5. Abrir en el navegador
http://localhost/init/
```

En Windows también podés usar `iniciar_api.bat` para iniciar la API.

## Endpoints API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/dni/:dni` | Busca persona por DNI (7-8 dígitos) |
| GET | `/api/nombre/:nombre` | Busca personas por nombre (mín. 3 caracteres) |
| GET | `/api/status` | Estado de la API |

**Rate limit:** 60 req/min por IP.

## Variables de entorno (`.env`)

| Variable | Default | Descripción |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | Host MySQL |
| `DB_USER` | `root` | Usuario MySQL |
| `DB_PASS` | _(vacío)_ | Contraseña MySQL |
| `DB_NAME` | `consultas_dni` | Nombre de la BD |
| `API_URL` | `http://localhost:3000` | URL de la API Node.js |
| `PORT` | `3000` | Puerto de la API |
| `CORS_ORIGIN` | `http://localhost` | Origen CORS permitido |
| `RATE_LIMIT_MAX` | `60` | Máx. requests por minuto |
