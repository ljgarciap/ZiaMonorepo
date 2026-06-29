# Docker — Referencia operacional

Guía de operación de los servicios ZIA Carbon Control en Docker.

---

## Servicios y puertos

| Contenedor | Imagen base | Puerto host | Puerto interno | Descripción |
|---|---|---|---|---|
| `zia_postgres` | `postgres:16-alpine` | — | 5432 | Base de datos PostgreSQL |
| `zia_redis` | `redis:7-alpine` | 6379 | 6379 | Caché y colas |
| `zia_backend` | PHP 8.4-fpm + Nginx | **8000** | 8000 | API Laravel |
| `zia_agent` | Python 3.12 + uvicorn | **8001** | 8001 | ZIA FastAPI |
| `zia_frontend` | Angular build + Nginx | **8080** | 80 | SPA producción |

En modo desarrollo (`docker-compose.dev.yml`), el frontend pasa al puerto **4200** (ng serve).

---

## Comandos de operación

### Iniciar

```bash
# Producción — construye imágenes y levanta en background
docker compose up -d --build

# Solo iniciar (sin rebuild)
docker compose up -d

# Desarrollo con hot-reload
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

### Parar

```bash
docker compose down          # para contenedores, preserva volúmenes (datos BD)
docker compose down -v       # para contenedores + elimina volúmenes (reset BD)
docker compose stop          # pausa sin eliminar contenedores
```

### Estado y logs

```bash
docker compose ps            # estado de todos los servicios
docker compose logs -f       # logs en tiempo real (todos)
docker compose logs -f backend      # solo backend
docker compose logs -f zia-agent    # solo agente
docker compose logs --tail=50 backend   # últimas 50 líneas
```

### Ejecutar comandos en contenedores

```bash
# Backend — Laravel artisan
docker compose exec backend php artisan migrate
docker compose exec backend php artisan migrate --seed
docker compose exec backend php artisan test
docker compose exec backend php artisan route:list
docker compose exec backend php artisan tinker

# Backend — shell
docker compose exec backend bash

# ZIA Agent — Python
docker compose exec zia-agent python -c "import main; print('OK')"
docker compose exec zia-agent bash

# Base de datos — psql
docker compose exec db psql -U zia_db_user -d zia_carbon
```

### Rebuild selectivo

```bash
# Solo rebuildar el servicio que cambió
docker compose build backend
docker compose up -d --no-deps backend

# Forzar rebuild sin caché
docker compose build --no-cache zia-agent
docker compose up -d --no-deps zia-agent
```

---

## Healthchecks

El servicio `zia-agent` tiene healthcheck configurado:

```yaml
healthcheck:
  test: ["CMD", "python", "-c", "import urllib.request; urllib.request.urlopen('http://localhost:8001/health')"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 20s
```

Verificar estado:
```bash
docker compose ps    # columna STATUS muestra "healthy" o "unhealthy"
curl http://localhost:8001/health   # {"status": "ok", "providers_ready": [...]}
```

---

## Volúmenes

| Volumen | Contenedor | Contenido |
|---|---|---|
| `postgres_data` | `zia_postgres` | Datos de PostgreSQL (BD completa) |
| `redis_data` | `zia_redis` | Datos Redis persistidos |

En modo desarrollo hay volúmenes de bind adicionales:
- `./backend` → `/var/www/html` en `zia_backend` (hot-reload PHP)
- `./frontend` → `/app` en `zia_frontend` (hot-reload Angular)
- `/app/node_modules` → volumen anónimo (evita sobreescribir node_modules del host)

---

## Red Docker interna

Todos los servicios están en la red por defecto de Compose (`zia_default`). Se comunican por nombre de contenedor:

| Comunicación | URL interna |
|---|---|
| `zia-agent` → backend | `http://backend:8000` |
| `backend` → zia-agent | `http://zia-agent:8001` |
| `backend` → db | `db:5432` |
| `backend` → redis | `redis:6379` |
| `frontend` → backend | Nginx proxy en producción / env var en dev |

El endpoint `/api/internal/calculate` en el backend **solo acepta** requests del la red interna Docker (valida `X-Internal-Secret`).

---

## Configuración por entorno

### Producción (`docker-compose.yml`)

```yaml
backend:
  environment:
    APP_ENV: production
    APP_DEBUG: false
```

### Desarrollo (`docker-compose.dev.yml` overlay)

```yaml
backend:
  environment:
    APP_ENV: local
    APP_DEBUG: 'true'
  volumes:
    - ./backend:/var/www/html    # código montado en vivo

frontend:
  build:
    dockerfile: Dockerfile.dev   # usa ng serve en lugar de build estático
  ports:
    - "4200:4200"               # puerto de ng serve
  command: ["npm", "run", "start", "--", "--host", "0.0.0.0"]
```

Para activar el overlay de desarrollo:
```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

---

## Troubleshooting

### Puerto ocupado

```bash
# Identificar qué usa el puerto
lsof -i :8000

# Si es otro proyecto Docker
docker ps -a   # ver contenedores de otros proyectos
docker stop <container>
```

### Contenedor en restart loop

```bash
docker compose logs backend --tail=30   # ver el error
```

Causas comunes:
- `APP_KEY` no configurada → `docker compose exec backend php artisan key:generate`
- BD no disponible → esperar que `zia_postgres` esté `healthy`
- Error de syntax PHP → revisar últimos cambios en `backend/`

### Base de datos corrupta o estado inconsistente

```bash
docker compose down -v   # eliminar volúmenes
docker compose up -d --build
docker compose exec backend php artisan migrate --seed
```

> ⚠️ Esto borra TODOS los datos. Solo usar en desarrollo.

### ZIA Agent no responde

```bash
# Verificar health
curl http://localhost:8001/health

# Si falla, ver logs
docker compose logs zia-agent --tail=50

# Causas comunes:
# - MISTRAL_API_KEY no configurada (el agente arranca pero no puede servir chat)
# - INTERNAL_API_SECRET distinto entre backend y zia-agent
```

### Frontend muestra página en blanco

```bash
docker compose logs frontend --tail=20

# En dev: verificar que el proxy a :8000 esté configurado en angular.json / proxy.conf.json
```

---

## Dockerfile de cada servicio

| Servicio | Archivo | Notas |
|---|---|---|
| Backend | `backend/Dockerfile` | PHP 8.4-fpm-alpine + Nginx |
| Backend dev | (sin Dockerfile.dev — usa el mismo con volumen) | — |
| ZIA Agent | `zia-agent/Dockerfile` | Python 3.12-slim |
| Frontend prod | `frontend/Dockerfile` | `ng build` → Nginx alpine |
| Frontend dev | `frontend/Dockerfile.dev` | Node.js + `ng serve` |
