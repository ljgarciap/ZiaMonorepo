# ZIA Carbon Control — Monorepo

Backend (Laravel 11) + Frontend (Angular 21) + PostgreSQL 16 + Redis 7,
integrados en un solo repositorio y orquestados con Docker Compose.

## Estructura

```
ZiaMonorepo/
├── backend/    Laravel 11 (PHP 8.4-fpm-alpine + nginx + supervisor)
├── frontend/   Angular 21, servido por nginx en producción
├── docker-compose.yml       Stack de producción/staging
├── docker-compose.dev.yml   Overlay de desarrollo (hot-reload)
└── docs/specs/               Specs de features (formato Analista)
```

## Levantar el stack (producción/staging)

```bash
cp .env.example .env   # ajustar valores si hace falta
docker compose up -d --build
```

Servicios y puertos:

| Servicio | Puerto host | Descripción |
|---|---|---|
| frontend | 8080 → 80 | Angular compilado, servido por nginx |
| backend  | 8000 | API Laravel (php-fpm + nginx + supervisor) |
| db       | 5432 | PostgreSQL 16 |
| redis    | 6379 | Cache / colas |

## Levantar el stack (desarrollo, con hot-reload)

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build
```

Diferencias respecto a producción:
- **backend**: monta `./backend` como volumen sobre `/var/www/html` — los
  cambios en código PHP se reflejan sin rebuild. `APP_ENV=local`,
  `APP_DEBUG=true`.
- **frontend**: usa `frontend/Dockerfile.dev` (`node:20-alpine` + `ng serve`)
  en vez del build de producción con nginx. Expone `4200:4200` con
  recarga en caliente de Angular. El mapeo `8080:80` heredado de
  `docker-compose.yml` queda sin uso en este modo (no hay nada
  escuchando en el puerto 80 del contenedor de dev).

## Variables de entorno

- **Raíz (`/.env`, ver `.env.example`)**: variables que consume
  `docker-compose.yml` — `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `DB_DATABASE`,
  `DB_USERNAME`, `DB_PASSWORD`. Si no se define `.env`, cada variable
  cae a un valor por defecto definido inline en `docker-compose.yml`.
- **`backend/.env`**: configuración completa de Laravel (ver
  `backend/.env.example`) — incluye integración IoT (ThingsBoard) y
  proveedores de AI (Mistral/Gemini) usados por el backend directamente,
  no por Docker Compose.

Ninguno de los dos `.env` se commitea (ambos están en `.gitignore`).

## Tests y CI

- Backend: `php artisan test` (PHPUnit, ver `backend/phpunit.xml` — usa
  SQLite en memoria, no requiere Postgres levantado).
- Frontend: `npm test` (`@angular/build:unit-test`, basado en Vitest).
- CI: `.github/workflows/ci.yml` corre ambos en cada push/PR a
  `master`/`main`.
