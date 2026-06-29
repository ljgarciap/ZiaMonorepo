# ZIA Backend — Laravel 11

API REST del sistema ZIA Carbon Control. Gestiona empresas, períodos, emisiones de carbono, master data y expone endpoints para el agente IA.

## Stack

- **Laravel 11** (PHP 8.4-fpm-alpine)
- **PostgreSQL 16** — base de datos principal
- **Redis 7** — caché y colas
- **Laravel Passport** — autenticación OAuth2 / JWT
- **Maatwebsite Excel** + **Barryvdh DomPDF** — exportación de reportes

## Ejecutar en Docker (recomendado)

Desde la raíz del monorepo:

```bash
cp .env.example .env        # ajustar valores
docker compose up -d --build
docker compose exec backend php artisan migrate --seed
```

La API queda disponible en `http://localhost:8000`.

## Ejecutar en local (sin Docker)

```bash
cd backend
composer install
cp .env.example .env        # ajustar DB_HOST=127.0.0.1
php artisan key:generate
php artisan migrate --seed
php artisan serve           # http://localhost:8000
```

Requiere PostgreSQL 16 y Redis corriendo localmente.

## Tests

```bash
# Dentro de Docker
docker compose exec backend php artisan test

# En local
cd backend && php artisan test
```

- Suite: PHPUnit con SQLite en memoria (no requiere PostgreSQL)
- **176 tests / 434 assertions**
- Cobertura: backend excluye controladores con dependencias externas (AI, PDF, IoT); ver `phpunit.xml`

## Estructura relevante

```
app/
├── Http/Controllers/Api/
│   ├── Admin/              Gestión de master data, usuarios, empresas (superadmin/admin)
│   ├── AISidecarController Proxy SSE hacia zia-agent
│   ├── CarbonEmissionController
│   ├── DashboardController
│   ├── InternalCalculationController  Endpoint interno para zia-agent tools
│   └── ReportController    PDF y Excel
├── Models/                 16 modelos Eloquent (ver docs/architecture/data-model.md)
├── Services/
│   ├── CarbonFootprintService    Motor de cálculo GWP (AR6)
│   ├── FormulaEvaluationService  Evaluador de fórmulas dinámicas
│   ├── AI/                       MistralAIService, GeminiAIService, AIManager
│   └── ThingsBoardService        Ingesta IoT
└── Console/Commands/
    └── SyncTelemetryCommand      Cron zia:sync-telemetry (cada 15 min)
```

## Autenticación

Bearer JWT (Laravel Passport). Todas las rutas bajo `/api/` excepto `/api/login`, `/api/register` y `/api/health` requieren `Authorization: Bearer <token>`.

Las rutas de contexto de empresa requieren además el header `X-Company-ID` con el ID de la empresa activa.

## Documentación completa

Ver [`../docs/architecture/overview.md`](../docs/architecture/overview.md) y [`../docs/architecture/api-laravel.md`](../docs/architecture/api-laravel.md).
