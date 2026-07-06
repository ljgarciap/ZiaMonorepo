# ADR-001 — Docker Compose como orquestador del monorepo

**Estado:** Aceptado  
**Fecha:** 2026-06-29  
**Autores:** Arquitecto, DevOps  
**Revisado por:** Luis García

---

## Contexto

ZIA Carbon Control es un monorepo con tres servicios de runtime distintos:

- **Backend:** Laravel 11 (PHP 8.4) — requiere PHP-FPM, Nginx, PostgreSQL 16, Redis
- **ZIA Agent:** FastAPI (Python 3.12) — requiere Python runtime, acceso HTTP al backend
- **Frontend:** Angular 21 SPA — requiere Node.js para compilar, Nginx para servir

El proyecto es un MVP activo desarrollado por un equipo pequeño, con foco en velocidad de iteración, no en escala operativa inmediata.

Se evaluaron tres opciones de orquestación:

1. **Docker Compose** — archivo declarativo, gestiona todos los servicios como un stack local
2. **Kubernetes (k8s local vía minikube/kind)** — orquestación completa con pods, services, ingress
3. **Scripts shell ad-hoc** — cada dev levanta los servicios manualmente

---

## Decisión

**Se usa Docker Compose v2** como único orquestador para entornos de desarrollo y CI.

Se mantienen dos archivos:
- `docker-compose.yml` — configuración base (producción/CI)
- `docker-compose.dev.yml` — overlay de desarrollo (volúmenes montados, hot-reload)

---

## Razones

### A favor de Docker Compose

**1. Curva de entrada mínima.**  
Un solo comando (`docker compose up -d --build`) levanta los 5 servicios. No requiere conocimiento de Kubernetes, Helm, Ingress controllers ni kubectl.

**2. Parity entre entornos.**  
Todos los devs y CI usan exactamente el mismo `docker-compose.yml`. No hay diferencias de configuración entre máquinas.

**3. El overlay `dev` resuelve el problema de hot-reload.**  
El `docker-compose.dev.yml` monta código fuente directamente, sin rebuilds de imagen. El backend detecta cambios PHP en vivo; el frontend corre `ng serve` en el mismo contenedor.

**4. Red interna sin configuración.**  
Docker Compose crea automáticamente una red privada (`zia_default`). Los servicios se comunican por nombre (`http://backend:8000`, `db:5432`) sin configurar DNS, Ingress, ni puertos cruzados.

**5. Alineado con el horizonte del proyecto.**  
El MVP no tiene requisitos de alta disponibilidad, auto-scaling ni rolling deployments en esta etapa. Introducir Kubernetes resolvería problemas que aún no existen.

### Por qué no Kubernetes

- Requiere entre 4–8 horas de setup inicial (minikube, Helm charts, Ingress, Secrets) que no aportan valor al MVP
- La experiencia de debugging es más compleja (`kubectl exec`, `kubectl logs`, etc. vs `docker compose logs`)
- Hot-reload de código con volúmenes en k8s local (minikube mount) es inestable en macOS
- Si el proyecto crece a producción en cloud, se migra directamente a AWS ECS/Fargate o EKS — no a k8s local

### Por qué no scripts ad-hoc

- No reproducibles: cada dev tiene su propio estado de servicios
- Sin aislamiento de red entre proyectos
- Difícil de documentar y onboardear nuevos devs

---

## Consecuencias

**Positivas:**
- Setup en ~10 minutos para un dev nuevo (ver `docs/guides/getting-started.md`)
- CI usa el mismo `docker-compose.yml` — no hay "funciona en mi máquina"
- Fácil de extender: agregar un servicio nuevo es agregar un bloque en el YAML

**Negativas / limitaciones aceptadas:**
- No hay auto-restart inteligente ni circuit breakers entre servicios
- Si un servicio cae, hay que reiniciarlo manualmente (`docker compose restart <servicio>`)
- No apto para producción cloud sin migración a ECS o k8s — eso es intencional

**Deuda técnica conocida:**
- Si ZIA llega a producción, el paso natural es AWS ECS Fargate (ver `docs/adr/ADR-002` cuando se decida). El `docker-compose.yml` actual puede convertirse a Task Definitions de ECS con herramientas como `ecs-cli compose`.

---

## Alternativas no elegidas

| Alternativa | Por qué se descartó |
|---|---|
| Kubernetes local (minikube) | Overhead de setup vs beneficio en MVP; hot-reload problemático en macOS |
| Docker Compose + Traefik | Traefik agrega complejidad de routing no necesaria con 3 servicios y puertos fijos |
| Scripts shell por servicio | No reproducibles, sin aislamiento, difícil de documentar |
| Sin Docker (todo local) | Cada dev necesita instalar PHP 8.4, Python 3.12, PostgreSQL, Redis — divergencia de versiones |

---

## Referencias

- `docker-compose.yml` — configuración base
- `docker-compose.dev.yml` — overlay de desarrollo
- `docs/ops/docker.md` — referencia operacional
- `docs/guides/getting-started.md` — guía de configuración inicial

---

## Adenda 2026-07-05 — Por qué un solo repositorio Git (no repos separados)

**Contexto adicional**: el documento de requerimientos técnicos del
proyecto (`Emanuel_Requerimientos_de_la_plataforma_de_Zia.md`, sección
1) pide explícitamente "repositorios separados para el frontend y
backend, cada uno con ramas que Coolify pueda jalar automáticamente
para el despliegue continuo". Esta ADR ya justificaba Docker Compose
como orquestador, pero no abordaba directamente esa pregunta específica
— esta adenda la cierra.

**Decisión**: un solo repositorio Git (`ZiaMonorepo`) con `backend/`,
`frontend/` y `zia-agent/` como carpetas de primer nivel, cada una con
su propio `Dockerfile` independiente.

**Por qué esto no compromete el objetivo real del requerimiento**: la
razón detrás de "repos separados" en un contrato de este tipo casi
siempre es **poder desplegar cada servicio de forma independiente**,
sin que un cambio de frontend obligue a rebuildear/redeployar el
backend (y viceversa). Eso se logra igual en un monorepo si el
orquestador de despliegue (Coolify) trata cada carpeta como un recurso
independiente, disparado solo por cambios en su propio path — un
patrón de "monorepo con despliegues independientes" ampliamente
soportado por Coolify y no exclusivo de repos separados.

**Por qué se prefirió el monorepo en la práctica**: durante el
desarrollo de este proyecto, la gran mayoría de los cambios funcionales
tocan frontend y backend **a la vez** (un endpoint nuevo casi siempre
viene con su consumo en la UI en el mismo cambio). Con repos separados,
cada uno de esos cambios requeriría dos PRs, dos revisiones, y
coordinar manualmente que ambos lados queden en versiones compatibles
— con el riesgo real de desincronización (backend en v12, frontend
todavía esperando la v11). Un monorepo con commits atómicos across
frontend/backend elimina esa clase de bug por construcción.

**Mejora concreta sobre la alternativa contractual**: ningún commit de
este proyecto puede dejar el backend y el frontend en un estado
mutuamente incompatible sin que se note en el mismo diff — la revisión
de código ve ambos lados del cambio junto. Con repos separados, esa
garantía depende de disciplina humana (recordar actualizar el otro
repo), no de la estructura del proyecto.

**Lo que falta para cerrar esto del todo**: confirmar con quien
administre la instancia de Coolify si, en efecto, está configurada con
un recurso por carpeta (despliegue independiente) o si cualquier push
dispara un rebuild de los tres servicios. Si es lo segundo, ahí sí hay
una brecha real que vale la pena resolver — moviendo la configuración
de Coolify a "watch paths" por carpeta, sin necesidad de separar los
repos Git.
