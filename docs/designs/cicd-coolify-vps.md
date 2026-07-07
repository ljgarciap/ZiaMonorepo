# Diseño técnico: CI/CD automático — Coolify sobre VPS Hostinger

**Fecha**: 2026-07-06
**Spec**: `docs/specs/cicd-coolify-vps.md`
**Estado**: Implementado y validado end-to-end (2026-07-06) — ver `docs/ops/deploy-vps-coolify.md` para el estado operativo actual

## Decisión de arquitectura clave

**Deploy vía webhook de Coolify, no SSH crudo desde GitHub Actions.**
Coolify expone, por cada aplicación/stack que gestiona, una URL de deploy
webhook + token de API. GitHub Actions solo construye imágenes, las
publica en GHCR, y llama ese webhook — nunca tiene una llave SSH del VPS.
Esto reduce la superficie de ataque (un secret de Coolify con alcance
limitado, en vez de una llave root) y usa Coolify para lo que Coolify
hace bien: pull de imagen, restart, SSL, healthchecks, rollback manual
desde su dashboard.

Migraciones (`php artisan migrate --force`) corren como **post-deployment
command** nativo de Coolify (se configura una vez en el dashboard, no en
el workflow) — así el pipeline no necesita SSH tampoco para eso.

## Componentes afectados o creados

| Componente | Acción |
|---|---|
| `.github/workflows/deploy-vps.yml` | **Nuevo.** Build + push a GHCR + trigger webhook Coolify + verificación post-deploy |
| `docker-compose.prod.yml` | **Nuevo.** Variante de `docker-compose.yml` que referencia imágenes `ghcr.io/...:latest` en vez de `build:`, con healthchecks en backend y frontend. Este es el archivo que Coolify importa. |
| `docker-compose.yml` | Sin cambios — sigue siendo el compose de desarrollo/build local |
| `backend/routes/api.php` (`/api/health`) | Extender para verificar conexión a DB (`DB::connection()->getPdo()`), no solo devolver `ok` fijo |
| Coolify (config manual en dashboard, no vive en el repo) | Importar `docker-compose.prod.yml`, configurar dominios + SSL, variables de entorno/secrets, credencial de registry GHCR, post-deploy command |
| `docs/deploy-production-migration.md` | **Nuevo** (Tech Writer) — cómo reapuntar este mismo pipeline al VPS de producción cuando esté listo |

## Data model changes
Ninguno.

## API contract changes
Ninguno nuevo. Se reutiliza `/api/health` existente, con la extensión
de chequeo de DB mencionada arriba (mismo contrato de respuesta,
`status` pasa a poder ser `"error"` con detalle si la DB no responde).

## Flujo end-to-end

```
push a master
   │
   ▼
ci.yml (ya existe) — tests backend/agent/frontend
   │ (solo si los 3 jobs pasan)
   ▼
deploy-vps.yml (nuevo, workflow_run: ["CI"], types: [completed], if success)
   │
   ├─ build-backend  → push ghcr.io/.../zia-backend:{sha,latest}
   ├─ build-agent    → push ghcr.io/.../zia-agent:{sha,latest}
   ├─ build-frontend → push ghcr.io/.../zia-frontend:{sha,latest}
   │
   ▼
curl -X POST $COOLIFY_WEBHOOK_URL (Authorization: Bearer $COOLIFY_API_TOKEN)
   │
   ▼
Coolify: pull imágenes :latest → docker compose up -d → post-deploy command (migrate --force)
   │
   ▼
deploy-vps.yml: poll a https://api.zia.softclass.co/api/health (reintentos con backoff)
   │
   ├─ 200 OK → job termina en verde, notify.sh "✅ Deploy exitoso"
   └─ falla tras N reintentos → job falla en rojo, notify.sh "🚨 Deploy falló — revisar Coolify"
```

`zia-agent` se construye y publica igual que los otros dos, pero en
`docker-compose.prod.yml` no se le asigna dominio/puerto público — solo
queda en la red interna del stack, consumido por `backend` via
`ZIA_BACKEND_URL`/llamada interna.

## Secrets y dónde viven

| Secret | Dónde |
|---|---|
| `GITHUB_TOKEN` (push a GHCR) | Ya existe, automático — solo se agrega `permissions: packages: write` al job |
| `COOLIFY_WEBHOOK_URL`, `COOLIFY_API_TOKEN` | GitHub Actions secrets (repo settings) |
| GHCR pull credential (PAT con `read:packages`) | Registry credential dentro de Coolify — no en el repo |
| `DB_PASSWORD`, `APP_KEY`, `ANTHROPIC_API_KEY`, `MISTRAL_API_KEY`, `INTERNAL_API_SECRET`, `LANGFUSE_*` | Variables de entorno del recurso en Coolify |
| `FRONTEND_URL=https://zia.softclass.co` | Variable de entorno en Coolify (ya está contemplado en `cors.php`, que además ya tiene `https://zia.softclass.co` hardcodeado en `allowed_origins`) |

Nada de esto queda en el repo ni en las imágenes Docker.

## Dependencias entre tareas (para que el PM secuencie)

1. **DevOps** — Provisionar VPS Hostinger, instalar Docker + Coolify → sin dependencias
2. **DevOps** — DNS: `zia.softclass.co` y `api.zia.softclass.co` → IP del VPS → depende de (1)
3. **DevOps** — Crear `docker-compose.prod.yml` con healthchecks → sin dependencias, en paralelo
4. **Backend Dev** — Extender `/api/health` con chequeo de DB → sin dependencias, en paralelo
5. **DevOps** — Configurar Coolify: importar compose, dominios+SSL, env vars, registry credential, post-deploy command → depende de (1), (2), (3)
6. **DevOps** — Escribir `.github/workflows/deploy-vps.yml` → depende de (3) para nombres/tags de imagen
7. **DevOps** — Generar `COOLIFY_WEBHOOK_URL`/`COOLIFY_API_TOKEN` en Coolify, cargarlos como secrets en GitHub → depende de (5)
8. **Validación end-to-end** — push de prueba a master, seguir el pipeline completo → depende de todo lo anterior
9. **Tech Writer** — documentar cómo reapuntar a producción, en paralelo desde que (5) esté definido
10. **Cybersecurity** — revisión de hardening (ver riesgos abajo) → puede correr en paralelo desde (1)

## Riesgos y mitigación

| Riesgo | Severidad | Mitigación |
|---|---|---|
| Sin ambiente de staging: push directo a master corre migraciones automáticas en "producción" (aunque sea la VPS de pruebas). Un bug en una migration rompe el ambiente sin gate manual. | **Alto** | Health check extendido detecta DB caída post-deploy y notifica por Telegram de inmediato. Ya es decisión explícita de Luis (sin staging por ahora); no se bloquea el diseño por esto, se deja documentado como riesgo aceptado. |
| 2 vCPU/8GB bajo carga concurrente real con RAG (embeddings + llamadas a Anthropic/Mistral simultáneas) | Medio | Es ambiente de pruebas — no se invitan usuarios reales. Documentado en la spec. |
| GHCR privado — Coolify necesita credencial para poder hacer pull | Medio | PAT con `read:packages`, cargado como registry credential *dentro* de Coolify, nunca en el repo |
| Acumulación de imágenes/tags en GHCR con cada push | Bajo | Configurar retención de versions en GitHub Packages (no bloqueante, puede ir después) |
| Dashboard de Coolify o SSH del VPS expuestos a internet sin hardening | Medio-Alto | Cybersecurity define: SSH solo con llave (sin password auth), firewall (ufw) restringiendo puertos, considerar IP allowlist para el dashboard de Coolify si el plan de Hostinger lo permite |

## Estimación de esfuerzo

| Tarea | Estimado |
|---|---|
| Provisionar VPS + instalar Coolify | 1–2h |
| DNS | 15 min + propagación |
| `docker-compose.prod.yml` + healthchecks | 1h |
| Extender `/api/health` (Backend Dev) | 30 min |
| Configurar Coolify completo | 1.5–2h |
| `deploy-vps.yml` | 1.5h |
| Validación end-to-end | 1h |
| Documentación migración a producción | 30 min |
| **Total** | **~7–9h**, mayormente DevOps |

## Abierto para Luis
- ¿Apruebas el enfoque de webhook de Coolify (sin SSH crudo desde GitHub
  Actions)? Es la decisión de arquitectura central de este diseño.
- ¿Confirmas que el riesgo de "migraciones automáticas sin staging" es
  aceptable por ahora, tal como quedó en la spec?
