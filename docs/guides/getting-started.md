# Getting Started — ZIA Carbon Control

Guía de configuración inicial del proyecto desde cero.

---

## Prerequisitos

| Herramienta | Versión mínima | Verificar |
|---|---|---|
| Docker | 24+ | `docker --version` |
| Docker Compose | v2 (plugin) | `docker compose version` |
| Git | 2.x | `git --version` |
| Node.js | 20+ (solo dev sin Docker) | `node --version` |
| PHP | 8.2+ (solo dev sin Docker) | `php --version` |
| Python | 3.12+ (solo dev sin Docker) | `python3 --version` |

Para el flujo de trabajo recomendado (Docker) solo se necesitan Docker y Git.

---

## Configuración inicial (5 pasos)

### 1. Clonar el repositorio

```bash
git clone <repo-url>
cd ZiaMonorepo
```

### 2. Crear archivo de variables de entorno

```bash
cp .env.example .env
```

Editar `.env` y ajustar como mínimo:

```dotenv
DB_DATABASE=zia_carbon
DB_USERNAME=zia_db_user
DB_PASSWORD=zia_db_password        # cambiar en producción

INTERNAL_API_SECRET=dev-secret     # clave entre backend y zia-agent

MISTRAL_API_KEY=sk-...             # o ANTHROPIC_API_KEY para el fallback
```

> La variable `THINGSBOARD_MOCK=true` ya viene en `.env.example`. No se necesita ThingsBoard para desarrollo.

Ver la referencia completa de variables en [`../ops/env-vars.md`](../ops/env-vars.md).

### 3. Levantar los servicios

```bash
docker compose up -d --build
```

Esto construye y levanta 5 contenedores:

| Contenedor | Servicio | Puerto |
|---|---|---|
| `zia_postgres` | PostgreSQL 16 | 5432 (interno) |
| `zia_redis` | Redis 7 | 6379 |
| `zia_backend` | Laravel API | 8000 |
| `zia_agent` | ZIA FastAPI | 8001 |
| `zia_frontend` | Angular + Nginx | 8080 |

### 4. Migrar la base de datos

```bash
docker compose exec backend php artisan migrate --seed
```

El seeder crea:
- Superadmin: `admin@zia.co` / `password`
- 3 scopes GHG (Alcance 1, 2, 3)
- Sectores económicos (servicios, industria, transporte, energía, público, tecnología)
- Empresa demo: **ECONOVA** (sector servicios)
- Factores de emisión base (electricidad, gasolina, gas natural, residuos)

### 5. Verificar que todo funciona

```bash
# API backend
curl http://localhost:8000/api/health

# Agente ZIA
curl http://localhost:8001/health

# Frontend
open http://localhost:8080   # o abrir en navegador
```

Iniciar sesión con `admin@zia.co` / `password`.

---

## Modo desarrollo (hot-reload)

Para desarrollar con recarga automática de cambios:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

| Servicio | Modo dev | Puerto |
|---|---|---|
| Backend | Volumen montado (`./backend:/var/www/html`) | 8000 |
| Frontend | `ng serve` con hot-reload | **4200** (no 8080) |

En modo dev, el frontend está en `http://localhost:4200`.

---

## Primer login y flujo básico

1. Abrir `http://localhost:8080` (o `:4200` en modo dev)
2. Login: `admin@zia.co` / `password`
3. Seleccionar empresa **ECONOVA** en el selector de contexto
4. Ir a **Emisiones → Captura manual** para registrar tu primera emisión
5. El dashboard se actualiza automáticamente

---

## Comandos útiles

```bash
# Ver logs de un servicio
docker compose logs -f backend
docker compose logs -f zia-agent
docker compose logs -f frontend

# Entrar al contenedor backend
docker compose exec backend bash

# Ejecutar artisan
docker compose exec backend php artisan <comando>

# Parar todos los servicios
docker compose down

# Parar y eliminar volúmenes (reset BD)
docker compose down -v
```

---

## Solución de problemas frecuentes

**Puerto 8000 ocupado**
```bash
lsof -i :8000   # identificar qué proceso lo usa
# Si es otro proyecto: docker compose -p <otro-proyecto> down
```

**Error `APP_KEY` no configurada**
```bash
docker compose exec backend php artisan key:generate
```

**Migrations fallan (BD no disponible)**
```bash
# Esperar que postgres_db esté healthy
docker compose ps
docker compose exec backend php artisan migrate
```

**`numero_documento` error en login**  
No es un bug de ZIA — ocurre si hay otra app en el mismo Docker network que inyecta campos extra. Revisar que solo están corriendo los contenedores de ZIA.

---

## Próximos pasos

- Referencia de API: [`../architecture/api-laravel.md`](../architecture/api-laravel.md)
- Arquitectura general: [`../architecture/overview.md`](../architecture/overview.md)
- Cómo contribuir: [`contributing.md`](contributing.md)
- Cómo ejecutar tests: [`testing.md`](testing.md)
