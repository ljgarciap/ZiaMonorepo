# Guía de contribución — ZIA Carbon Control

Flujo de trabajo, convenciones y proceso de revisión para el equipo de desarrollo.

---

## Flujo de trabajo del equipo

```
Luis (Product Owner)
  │
  ├─ Analista     → clarifica requerimiento, produce spec técnica (docs/specs/)
  ├─ Arquitecto   → aprueba diseño, coordina al equipo
  ├─ PM           → desglosa en tareas, asigna a Backend/Frontend/DevOps
  │
  ├─ Backend Dev  ─┐
  ├─ Frontend Dev  ├─ implementan en paralelo
  ├─ DevOps        ┘
  │
  ├─ Senior Reviewer → revisa código, valida arquitectura
  └─ QA              → tests, cobertura, validación de output
```

**Regla de oro:** Ningún código se implementa sin spec aprobada del Analista y diseño aprobado del Arquitecto.

---

## Convenciones de commits

Formato: `tipo(scope): descripción en español`

| Tipo | Cuándo usarlo |
|---|---|
| `feat` | Nueva funcionalidad |
| `fix` | Corrección de bug |
| `docs` | Cambios solo en documentación |
| `pruebas` | Agregar o corregir tests |
| `refactor` | Cambio de código sin cambio de comportamiento |
| `estilo` | Formato, tema visual, CSS |
| `chore` | Tareas de mantenimiento (deps, config) |

**Scopes comunes:** `backend`, `frontend`, `zia-agent`, `docker`, `ci`, `sprint-N`

Ejemplos:
```
feat(backend): agregar endpoint de exportación Excel por empresa
fix(zia-agent): corregir normalización de historial Anthropic con tool_call vacío
pruebas(frontend): cobertura 60% en servicios de dashboard
docs(arquitectura): agregar diagrama de flujo IoT
```

---

## Estructura de ramas

| Rama | Propósito |
|---|---|
| `master` | Producción. Requiere Senior Reviewer. |
| `dev` | Desarrollo activo. Sandbox puede pushear directamente. |
| `feature/<nombre>` | Features individuales — merge a `dev` |
| `fix/<nombre>` | Bug fixes |

**Nunca** pushear directamente a `master` sin revisión.

---

## Agregar una nueva feature

### 1. Spec del Analista

Crear `docs/specs/<nombre>.md` con:
- Contexto y motivación
- Requerimientos funcionales (lista numerada)
- Criterios de aceptación
- Impacto en BD (si aplica)
- Endpoints o componentes nuevos

### 2. Diseño del Arquitecto

El Arquitecto revisa la spec y aprueba o devuelve con ajustes antes de escribir código.

### 3. Implementación

**Backend (Laravel):**
```bash
# Crear migration
docker compose exec backend php artisan make:migration create_<tabla>_table

# Crear model
docker compose exec backend php artisan make:model <Model> --resource --requests

# Registrar ruta en routes/api.php
# Agregar tests en tests/Feature/<Controller>Test.php
```

**Frontend (Angular):**
```bash
cd frontend
npx ng generate component components/<nombre>/<nombre>
npx ng generate service services/<nombre>
```

**ZIA Agent (Python):**
```bash
cd zia-agent
# Agregar nueva tool en main.py (tools array + execute_tool handler)
# Agregar tests en tests/
```

### 4. Tests

Todo código nuevo requiere tests. Ver [`testing.md`](testing.md) para cómo ejecutarlos.  
Cobertura mínima:
- Backend: no baja el número de tests existentes
- Frontend: ≥ 60% (statements, branches, functions, lines)
- ZIA Agent: tests de happy path y error path para cada feature nueva

### 5. Senior Reviewer

El Senior Reviewer revisa antes del merge a `master`. Categorías:
- 🔴 **Blocker** — debe corregirse antes del merge
- 🟡 **Suggestion** — recomendado pero no bloqueante
- 🟢 **Approved** — sin observaciones

Los reports de revisión van a `docs/reviews/sprint-N.md` (gitignoreado — solo uso interno).

---

## Convenciones de código

### Backend (Laravel / PHP)

- Controllers: retornar `JsonResponse` tipado
- Validación: `$request->validate([...])` para inputs de usuario; `Validator::make()` solo cuando la lógica lo exige
- Mass assignment: **siempre** `$request->only([...])` — nunca `$request->all()` ni `$request->validated()` sin whitelist explícita
- Modelos: usar SoftDeletes en todos los modelos de master data
- Migraciones: nunca editarlas una vez commiteadas — crear una nueva
- Auth: Bearer token via Laravel Passport; contexto empresa via `X-Company-ID`

### Frontend (Angular)

- Standalone components (no NgModules salvo app.config.ts)
- Signals para estado reactivo
- Servicios para toda comunicación con la API (sin `fetch` directo en componentes)
- `AuthGuard` y `RoleGuard` en todas las rutas protegidas

### ZIA Agent (Python)

- Typing explícito en todas las funciones
- Las tools siempre retornan `dict` — nunca lanzar excepción no capturada desde una tool
- `normalize_history_for_anthropic` / `normalize_history_for_mistral` son idempotentes — llamar siempre antes de cada request al LLM

---

## Variables de entorno

No commitear `.env` ni valores de API keys reales. Usar `.env.example` como plantilla documentada.  
Si se agrega una variable nueva: actualizar `.env.example` y `docs/ops/env-vars.md`.

---

## Documentación

| Tipo de cambio | Qué actualizar |
|---|---|
| Nuevo endpoint API | `docs/architecture/api-laravel.md` |
| Nuevo modelo / migración | `docs/architecture/data-model.md` |
| Cambio en ZIA agent o tools | `docs/architecture/ai-agent.md` |
| Cambio en infraestructura Docker | `docs/ops/docker.md` |
| Variable de entorno nueva | `docs/ops/env-vars.md` + `.env.example` |
| Decisión técnica importante | `docs/adr/ADR-XXX-<titulo>.md` |
| Cambio arquitectónico | Actualizar `CLAUDE.md` del proyecto |

---

## Notificaciones

Al terminar una tarea, notificar via `.claude/scripts/notify.sh`. Luis recibe el aviso en Telegram y abre Claude Code para revisar.
