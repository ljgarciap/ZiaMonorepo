# Variables de entorno

**Última actualización:** 2026-06-29 | **Responsable:** DevOps / AWS Architect

Inventario completo de variables de entorno requeridas para ejecutar el stack ZIA Carbon Control.
Ningún archivo `.env` se commitea — ver `.env.example` en la raíz y `backend/.env.example`.

---

## Resumen por servicio

| Variable | Raíz `.env` | `backend/.env` | `zia-agent` | Requerida |
|---|:---:|:---:|:---:|:---:|
| `APP_KEY` | ✅ | ✅ | — | **Sí** |
| `APP_ENV` | ✅ | ✅ | — | Sí |
| `APP_DEBUG` | ✅ | ✅ | — | Sí |
| `DB_DATABASE` | ✅ | ✅ | — | **Sí** |
| `DB_USERNAME` | ✅ | ✅ | — | **Sí** |
| `DB_PASSWORD` | ✅ | ✅ | — | **Sí** |
| `INTERNAL_API_SECRET` | ✅ | ✅ | ✅ | **Sí** |
| `MISTRAL_API_KEY` | ✅ | ✅ | ✅ | Sí* |
| `ANTHROPIC_API_KEY` | ✅ | — | ✅ | Sí* |
| `MISTRAL_MODEL` | — | — | ✅ | No |
| `ZIA_AGENT_URL` | — | ✅ | — | No |
| `AI_PRIMARY_PROVIDER` | — | ✅ | — | No |
| `AI_FALLBACK_PROVIDER` | — | ✅ | — | No |
| `GEMINI_API_KEY` | — | ✅ | — | No |
| `THINGSBOARD_HOST` | — | ✅ | — | No |
| `THINGSBOARD_USERNAME` | — | ✅ | — | No** |
| `THINGSBOARD_PASSWORD` | — | ✅ | — | No** |
| `THINGSBOARD_MOCK` | — | ✅ | — | No |
| `THINGSBOARD_ENERGY_DEVICE_ID` | — | ✅ | — | No** |
| `THINGSBOARD_WATER_DEVICE_ID` | — | ✅ | — | No** |

\* Al menos un proveedor de IA debe estar configurado para que el agente ZIA funcione.
\*\* Requerido solo si `THINGSBOARD_MOCK=false`.

---

## Variables de la raíz (`.env`) — Docker Compose

Estas variables son leídas directamente por `docker-compose.yml` para configurar los contenedores.
Copiar desde `.env.example` y ajustar antes de levantar el stack.

### Aplicación Laravel

| Variable | Descripción | Ejemplo | Requerida |
|---|---|---|:---:|
| `APP_ENV` | Entorno de ejecución | `production` / `local` | Sí |
| `APP_DEBUG` | Mostrar errores detallados | `false` | Sí |
| `APP_KEY` | Clave de cifrado de Laravel (base64) | `base64:...` | **Sí** |

Generar `APP_KEY`: `docker compose exec backend php artisan key:generate --show`

### Base de datos (PostgreSQL)

| Variable | Descripción | Ejemplo | Requerida |
|---|---|---|:---:|
| `DB_DATABASE` | Nombre de la base de datos | `zia_carbon` | **Sí** |
| `DB_USERNAME` | Usuario de PostgreSQL | `zia_db_user` | **Sí** |
| `DB_PASSWORD` | Contraseña del usuario | `s3cur3_p4ss` | **Sí** |

### Seguridad interna

| Variable | Descripción | Ejemplo | Requerida |
|---|---|---|:---:|
| `INTERNAL_API_SECRET` | Shared secret entre backend y zia-agent para endpoints `/api/internal/*` | `random-32-chars` | **Sí** |

Generar: `openssl rand -base64 32`

### IA — Agente ZIA

| Variable | Descripción | Ejemplo | Requerida |
|---|---|---|:---:|
| `MISTRAL_API_KEY` | API key de Mistral AI (proveedor primario del agente) | `...` | Sí* |
| `ANTHROPIC_API_KEY` | API key de Anthropic Claude (fallback del agente) | `sk-ant-...` | Sí* |

\* El agente ZIA requiere al menos uno. Con ambos, usa Mistral como primario y Anthropic como fallback automático (3 reintentos con backoff).

---

## Variables de backend (`backend/.env`)

El archivo `backend/.env` extiende la configuración de Docker Compose con parámetros específicos de Laravel. En producción Docker, la mayoría de estas variables se pasan a través de `docker-compose.yml`; el `backend/.env` es necesario para desarrollo local fuera de Docker.

### Conexión de base de datos completa

| Variable | Default | Descripción |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | Driver de BD |
| `DB_HOST` | `db` (Docker) / `127.0.0.1` (local) | Host de PostgreSQL |
| `DB_PORT` | `5432` | Puerto |
| `DB_DATABASE` | `zia_carbon` | Nombre de BD |
| `DB_USERNAME` | — | Usuario |
| `DB_PASSWORD` | — | Contraseña |

### Redis

| Variable | Default | Descripción |
|---|---|---|
| `REDIS_HOST` | `redis` (Docker) / `127.0.0.1` (local) | Host de Redis |
| `REDIS_PORT` | `6379` | Puerto |
| `REDIS_PASSWORD` | `null` | Contraseña (vacío en dev) |

### IA — Backend (AIManager, MistralAIService, GeminiAIService)

El backend tiene su propia capa de IA independiente del agente ZIA, usada para recomendaciones y análisis directo.

| Variable | Default | Descripción |
|---|---|---|
| `AI_PRIMARY_PROVIDER` | `mistral` | Proveedor primario del backend: `mistral` \| `gemini` |
| `AI_FALLBACK_PROVIDER` | `gemini` | Proveedor fallback del backend |
| `MISTRAL_API_KEY` | — | Clave Mistral (compartida con el agente si se usa el mismo) |
| `GEMINI_API_KEY` | — | API key de Google Gemini |

### Agente ZIA (URL de conexión)

| Variable | Default | Descripción |
|---|---|---|
| `ZIA_AGENT_URL` | `http://zia-agent:8001` | URL del servicio zia-agent. En desarrollo local fuera de Docker: `http://localhost:8001` |

### IoT — ThingsBoard

El comando `zia:sync-telemetry` se ejecuta cada 15 minutos vía cron y lee lecturas de energía y agua desde ThingsBoard.

| Variable | Default | Descripción |
|---|---|---|
| `THINGSBOARD_MOCK` | `true` | `true` = usar datos simulados (no requiere ThingsBoard real) |
| `THINGSBOARD_HOST` | `https://thingsboard.cloud` | URL de la instancia ThingsBoard |
| `THINGSBOARD_USERNAME` | — | Email de login en ThingsBoard |
| `THINGSBOARD_PASSWORD` | — | Contraseña de ThingsBoard |
| `THINGSBOARD_ENERGY_DEVICE_ID` | `energy_econova_device` | ID del dispositivo de energía en ThingsBoard |
| `THINGSBOARD_WATER_DEVICE_ID` | `water_econova_device` | ID del dispositivo de agua en ThingsBoard |

---

## Variables del agente ZIA (`zia-agent`)

El contenedor `zia-agent` lee estas variables directamente. Se pasan desde `docker-compose.yml`.

| Variable | Default | Descripción |
|---|---|---|
| `ZIA_BACKEND_URL` | `http://backend:8000` | URL interna al backend Laravel para tool calls |
| `INTERNAL_API_SECRET` | — | Debe coincidir exactamente con el `INTERNAL_API_SECRET` del backend |
| `ANTHROPIC_API_KEY` | — | API key de Anthropic (fallback) |
| `MISTRAL_API_KEY` | — | API key de Mistral (primario) |
| `MISTRAL_MODEL` | `mistral-small-latest` | Modelo de Mistral a usar. Otros valores: `mistral-medium-latest`, `mistral-large-latest` |

---

## Setup mínimo para desarrollo

El setup mínimo funcional requiere solo 6 variables en el `.env` raíz:

```bash
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:...           # generar con php artisan key:generate
DB_DATABASE=zia_carbon
DB_USERNAME=zia_db_user
DB_PASSWORD=mi_password_local
INTERNAL_API_SECRET=dev-secret-no-usar-en-prod
MISTRAL_API_KEY=...          # o ANTHROPIC_API_KEY si no tienes Mistral
```

Con `THINGSBOARD_MOCK=true` (default) no se necesita ThingsBoard.
Sin `MISTRAL_API_KEY` ni `ANTHROPIC_API_KEY`, el agente ZIA responderá con error 503 pero el resto del sistema funciona.
