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
