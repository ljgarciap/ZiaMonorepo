# Review: Infraestructura del monorepo (tareas #1-#4 del PM)

**Date**: 2026-06-16
**Reviewer**: Senior Reviewer
**Decision**: ✅ Approved (tras corregir 1 blocker durante la revisión)

## Summary
Cambios puramente de infraestructura: secretos movidos fuera del
`docker-compose.yml` versionado, pipeline de CI nuevo, overlay de
desarrollo con hot-reload y README raíz. No hay lógica de aplicación
afectada. Se encontró y corrigió un blocker de seguridad antes de
aprobar; el resto del trabajo está bien ejecutado y validado contra
configuración real.

## Findings

### 🔴 Blockers (corregido en esta revisión)
1. **`docker-compose.yml` (versión previa a esta revisión)** — los
   valores `${VAR:-valor_literal}` dejaban el secreto real (`APP_KEY`,
   `DB_PASSWORD`, `DB_USERNAME`, `DB_DATABASE`) commiteado como
   fallback dentro del archivo versionado. Esto contradice la regla
   explícita de `devops.md`: *"Never store secrets in code or
   docker-compose files"*. La tarea pedía mover los secretos a `.env`,
   no duplicarlos como default.
   **Fix aplicado**: se quitaron los defaults de las 4 variables
   sensibles (`${VAR}` sin fallback); `APP_ENV`/`APP_DEBUG` conservan
   default porque no son secretos. Verificado con `docker compose
   config`: con `.env` presente resuelve igual que antes; sin `.env`,
   compose emite un warning y usa cadena vacía en vez de exponer el
   valor real.

### 🟡 Suggestions (no bloqueantes)
1. **`docker-compose.yml:57`** — el mapeo `8080:80` del servicio
   `frontend` queda sin uso bajo `docker-compose.dev.yml` (el
   contenedor de dev corre `ng serve` en 4200, nada escucha en 80).
   Es inocuo pero podría confundir a un dev nuevo. Documentado en
   README; no amerita complejidad adicional (`!reset` de Compose no
   permite reemplazar y repoblar la misma clave en un solo paso).
2. **`.github/workflows/ci.yml`** — no hay `lint` para frontend (el
   `package.json` no define ese script) ni cache de Composer. No es un
   gap introducido por esta tarea — el lint nunca existió — pero
   queda como ítem de backlog si se quiere endurecer el CI.
3. **No pude validar la ejecución real de `php artisan test` ni
   `ng test`** en este entorno: PHP/Composer no están instalados
   localmente, la versión de Node local (v20.9.0) es menor al mínimo
   que exige Angular 21 (v20.19+), y el daemon de Docker no estaba
   corriendo (no se pudo hacer `docker build`/`up`). Lo que sí se
   validó exhaustivamente fue `docker compose config` (con y sin
   overlay de dev, con y sin `.env`) — la configuración resuelve
   correctamente en todos los casos. **Recomiendo que la primera
   ejecución real del workflow de CI en GitHub Actions (al hacer push)
   sea tratada como el gate de validación pendiente**, ya que es el
   único entorno disponible con PHP/Node correctos para correr los
   tests de verdad.

### 🟢 Well done
- Uso de `${VAR:-default}` para variables no sensibles (`APP_ENV`,
  `APP_DEBUG`) que preserva compatibilidad sin reintroducir secretos.
- `frontend/Dockerfile.dev` + volumen anónimo en `/app/node_modules`
  es el patrón correcto para que el bind mount de desarrollo no borre
  las dependencias instaladas en la imagen.
- README documenta honestamente las diferencias entre stack de
  producción y de desarrollo, incluyendo el caveat del puerto 8080.
- `.env` y `.env.example` separan claramente secretos de Compose vs.
  configuración completa de Laravel (`backend/.env`), sin mezclarlos.

## Next steps
Ready for QA — con la advertencia de que QA debe ejecutar (o esperar)
la primera corrida real de `.github/workflows/ci.yml` para confirmar
que los tests pasan de verdad, ya que este entorno no pudo correrlos.
