# ZIA Frontend — Angular 21

SPA (Single Page Application) de ZIA Carbon Control. Interfaz para captura de emisiones, dashboard de huella de carbono, agente IA conversacional y gestión administrativa.

## Stack

- **Angular 21** (standalone components, signals)
- **Angular Material** — design system
- **Vitest** — testing (via `@angular/build:unit-test`)
- **Nginx** — servidor en producción (dentro de Docker)

## Pantallas principales

| Ruta | Roles | Descripción |
|---|---|---|
| `/login` | — | Autenticación |
| `/dashboard` | todos | Resumen de huella de carbono por alcance |
| `/form` | user, admin | Formulario de captura de emisiones |
| `/smart-intake` | user, admin | Captura asistida por formularios dinámicos |
| `/live` | user, admin | Chat en tiempo real con el agente ZIA (SSE) |
| `/history` | todos | Historial de emisiones registradas |
| `/admin/companies` | admin, superadmin | Gestión de empresas y períodos |
| `/admin/users` | admin, superadmin | Gestión de usuarios |
| `/admin/sectors` | superadmin | Gestión de sectores económicos |
| `/admin/metadata` | superadmin | Categorías y factores de emisión |
| `/admin/units` | superadmin | Unidades de medida |
| `/admin/scopes` | superadmin | Alcances GHG |
| `/admin/audit` | superadmin | Logs de auditoría |

## Ejecutar en desarrollo (con hot-reload)

```bash
cd frontend
npm install
npm start          # http://localhost:4200
```

O con Docker (hot-reload incluido):

```bash
# Desde la raíz del monorepo
docker compose -f docker-compose.yml -f docker-compose.dev.yml up frontend
```

## Ejecutar en producción

```bash
# Desde la raíz del monorepo
docker compose up -d --build frontend
# Disponible en http://localhost:8080
```

## Tests

```bash
cd frontend
npx ng test --watch=false
```

- **89 tests** — cobertura ≥ 60% (statements, branches, functions, lines)
- Configuración de thresholds en `angular.json`

## Estructura relevante

```
src/app/
├── components/
│   ├── login/ register/     Autenticación
│   ├── dashboard/           Layout principal + resumen GEI
│   ├── form/                Captura manual de emisiones
│   ├── smart-intake/        Captura asistida con cuestionario dinámico
│   ├── zia-chat/            Chat flotante con agente ZIA
│   ├── zia-live/            Vista completa del agente ZIA (SSE)
│   └── admin/               Gestión administrativa (companies, users, metadata...)
├── services/
│   ├── auth.ts              Login, logout, token management
│   ├── carbon.service.ts    CRUD de emisiones y períodos
│   ├── dashboard.service.ts Datos del dashboard
│   ├── master-data.service.ts Factores, categorías, unidades
│   └── context.service.ts   Empresa activa (X-Company-ID header)
└── guards/
    ├── auth-guard.ts        Requiere sesión activa
    └── role-guard.ts        Requiere rol específico
```

## Documentación completa

Ver [`../docs/architecture/overview.md`](../docs/architecture/overview.md).
