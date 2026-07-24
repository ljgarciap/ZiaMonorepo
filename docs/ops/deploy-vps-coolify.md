# Deploy al VPS vía Coolify

**Última actualización:** 2026-07-06 | **Responsable:** DevOps

Documenta el pipeline de CI/CD que construye las 3 imágenes Docker y las despliega
en un VPS self-hosted con Coolify, y qué hay que cambiar para reapuntarlo a un
VPS de producción cuando esté listo. Ver también `docs/designs/cicd-coolify-vps.md`
y `docs/specs/cicd-coolify-vps.md` (diseño original) y `.github/workflows/deploy-aws.yml`
(camino alterno a AWS ECS, sin tocar, para cuando se decida esa ruta en vez de VPS).

---

## Estado actual (VPS de pruebas)

| Ítem | Valor |
|---|---|
| Proveedor | Hostinger |
| Specs | 2 vCPU / 8 GB RAM / 100 GB NVMe |
| OS | Ubuntu 24.04 LTS |
| IP | `2.25.79.64` |
| Coolify | v4.1.2, self-hosted en el mismo VPS |
| Dashboard Coolify | `https://coolify.softclass.co` (puerto 8000 cerrado a internet, solo accesible vía este dominio) |
| Proyecto Coolify | `zia-carbon-control` |
| Servicio Coolify (docker-compose) | `zia-carbon-control-test` |
| Frontend | `https://ziaccb.softclass.co` |
| Backend | `https://apiziaccb.softclass.co` |
| SSH | Solo llave (`PasswordAuthentication no`), root vía `~/.ssh/id_ed25519` |
| Firewall | `ufw` activo (22/80/443), + regla `iptables` en `DOCKER-USER` bloqueando el 8000 publicado por Docker (ufw por sí solo no cubre puertos publicados por Docker — bypassea `ufw` vía DNAT propio) |

---

## Cómo funciona el pipeline

```
push a master
   │
   ▼
ci.yml — tests backend/agent/frontend
   │ (solo si los 3 jobs pasan)
   ▼
deploy-vps.yml (workflow_run: ["CI"], types: [completed], if success)
   │
   ├─ build-backend  → push ghcr.io/ljgarciap/zia-backend:{sha,latest}
   ├─ build-agent    → push ghcr.io/ljgarciap/zia-agent:{sha,latest}
   ├─ build-frontend → push ghcr.io/ljgarciap/zia-frontend:{sha,latest}
   │
   ▼
deploy: sincroniza docker-compose.prod.yml → PATCH a la API de Coolify
   │  (Coolify guarda su propia copia del compose; si no se sincroniza
   │   en cada deploy, los cambios al archivo del repo nunca se aplican)
   ▼
GET /api/v1/deploy?uuid=<service_uuid>&force=true (Bearer token) → Coolify hace pull + up -d
   │
   ▼
poll versión real: backend (/api/health, campo "version") y frontend
(/version.txt) hasta que ambos devuelvan el IMAGE_TAG de este build,
no solo un 200 (o falla tras reintentos)
```

Migraciones (`php artisan migrate --force`) corren en el **entrypoint del
contenedor backend** (`backend/docker/entrypoint.sh`), no como post-deployment
command de Coolify — ese feature de Coolify solo existe para recursos tipo
"Application" (Dockerfile/imagen único), no para "Services" (docker-compose de
varios contenedores), que es lo que usamos aquí.

**Incidente 2026-07-24 y por qué el health check ya no alcanza con "responde
200":** la credencial de GHCR guardada en el VPS (`docker login`) se venció
entre el 18 y el 24 de julio. Desde entonces, `docker compose pull` fallaba en
silencio en cada deploy — Coolify seguía sirviendo los 3 contenedores viejos
(backend, agent, frontend) mientras el pipeline reportaba "exitoso" 2 veces
seguidas, porque el único chequeo era que `/api/health` respondiera 200 (lo
cual el contenedor viejo, nunca tocado, seguía haciendo perfectamente). Fix
aplicado:
- `backend/Dockerfile` y `frontend/Dockerfile` reciben `IMAGE_TAG` como
  `--build-arg` y lo hornean en la imagen (`/api/health` → campo `version`;
  frontend → archivo estático `version.txt`).
- `deploy-vps.yml` ahora hace poll de esa versión, no solo del código HTTP —
  si el pull falló (credencial vencida, rate limit, lo que sea), el pipeline
  falla en rojo en vez de mentir en verde.
- Mitigación de re-ocurrencia: si la credencial de GHCR del VPS vuelve a
  vencer, este chequeo lo va a detectar solo — no depende de que alguien
  note manualmente que el sitio se ve "viejo".

---

## Secrets/Variables en GitHub Actions

Todos guardados como **Secrets** (no Variables) en Settings → Secrets and
variables → Actions del repo:

| Nombre | Qué es |
|---|---|
| `COOLIFY_API_TOKEN` | Bearer token de Coolify, generado en el dashboard → Keys & Tokens. Usar uno dedicado a CI (`github-actions-deploy`), permisos `read` + `deploy` (no admin completo) |
| `COOLIFY_URL` | Base URL de la API de Coolify — `https://coolify.softclass.co` (el mismo dominio del dashboard; Traefik enruta `/api/v1/*` al contenedor de Coolify). **No** usar `http://2.25.79.64:8000` — ese puerto quedó cerrado por firewall tras el hardening de seguridad, y de hecho causó un deploy fallido real hasta corregirlo (ver commit del 2026-07-07) |
| `COOLIFY_SERVICE_UUID` | UUID del servicio docker-compose en Coolify (`nfzjonvadjqzshta8jzx5ayz` en el VPS de pruebas) |
| `BACKEND_HEALTH_URL` | `https://apiziaccb.softclass.co/api/health` |
| `FRONTEND_URL` | `https://ziaccb.softclass.co` (sin barra final — el workflow le agrega `/version.txt`) |

Variables de entorno de la app (DB, API keys, etc.) viven **dentro de Coolify**
(el servicio → pestaña Environment Variables), no en GitHub — ver
`docs/ops/env-vars.md` para el inventario completo de qué variables necesita
cada servicio.

---

## Cómo reapuntar a un VPS de producción

Cuando el VPS de producción esté listo (mismo Coolify u otra instancia):

1. **Si es un VPS nuevo con su propia instancia de Coolify:**
   - Repetir el setup: Docker (Hostinger ya lo trae, verificar en otros
     proveedores), instalar Coolify (`curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash`).
   - Repetir hardening: SSH solo llave, `ufw` (22/80/443), regla
     `iptables -I DOCKER-USER -p tcp -m conntrack --ctorigdstport 8000 -j DROP`
     + `netfilter-persistent save`, dominio propio para el dashboard
     (Settings → Configuration → General → URL) en vez de exponer :8000.
   - Crear proyecto + servicio nuevo vía API (`POST /projects`, `POST /services`
     con `docker_compose_raw` en base64 — ver `docker-compose.prod.yml`).
   - `docker login ghcr.io` en el nuevo VPS con un PAT `read:packages`.
   - Cargar las env vars del servicio (ver `docs/ops/env-vars.md`).
   - DNS: apuntar los dominios de producción (decidir si se reutiliza
     `zia.softclass.co`/`api.zia.softclass.co` — hoy ocupados por otro
     servicio, revisar antes de asumirlo — o un dominio nuevo).

2. **Actualizar en GitHub Actions** (Settings → Secrets and variables → Actions):
   - `COOLIFY_URL` → nueva URL de la API de Coolify de producción
   - `COOLIFY_SERVICE_UUID` → UUID del nuevo servicio
   - `COOLIFY_API_TOKEN` → token nuevo (no reutilizar el de pruebas)
   - `BACKEND_HEALTH_URL` → nuevo dominio de producción
   - `FRONTEND_URL` → nuevo dominio de producción del frontend

3. **No hay que tocar el workflow** (`deploy-vps.yml`) ni `docker-compose.prod.yml`
   — todo el cambio de entorno vive en los 4 secrets de arriba. Si en cambio se
   decide ir por AWS ECS en vez de VPS, usar `deploy-aws.yml` (independiente,
   sin relación con este pipeline).

4. **Riesgo aceptado, no resuelto:** no hay ambiente de staging — push a
   `master` despliega directo. Ver `docs/specs/cicd-coolify-vps.md` sección
   "Riesgos y mitigación".
