# EcoRuta · Sync Agent (intermediario PC)

Servicio Node.js que corre **dentro de la PC** y actúa como intermediario entre tu
**servidor web** y tu **servidor local (XAMPP)** para el módulo **delivery**
(pedidos y pagos por envío en `ecoruta_db`).

## Cómo evita tus dos errores

1. **Pedidos duplicados → clave de idempotencia `sync_uuid`**
   - Cada pedido lleva un UUID v4 generado en origen (migración 45).
   - `POST /api/orders` vía agente y `POST /api/sync/push` buscan primero por
     `sync_uuid`: si ya existe, devuelven el existente **sin insertar de nuevo**.
   - Segunda barrera: `UNIQUE(uq_pedidos_sync_uuid)` a nivel DB.

2. **Cobros errados → regla de dinero**
   - Un pedido que ya figura `pagado=1` en el servidor **jamás se revierte a 0**
     por un reintento: el reintento con el mismo `sync_uuid` responde
     `duplicado` sin tocar nada.
   - Cambios de estado/pago offline viajan como replay y el servidor los valida
     con sus reglas (ej: un pedido entregado+pagado no se puede revertir).

## Flujo

```
Frontend  --->  http://localhost:18650  (este agente)
                    |  web online? --sí-->  WEB (y pull posterior al local)
                    |  web caída?  --no-->  LOCAL XAMPP + outbox/*.json
Al volver internet: push outbox -> web (/api/sync/push idempotente)
                    pull web -> local (/api/sync/pull?since=)
```

## Instalación (una vez)

1. Backend **local y web**: mismo código (con `SyncController`,
   rutas `/api/sync/*` en `backend/index.php`).
2. En ambos `.env`: misma `SYNC_API_KEY` (genera con
   `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`)
   y `SYNC_DB_NAME` con el nombre de la base que tiene `pedidos`.
3. Corre la migración 45 en **ambas** DBs:
   `php backend/database/migrations/45_add_sync_pedidos.php [nombre_db]`
   (La 44 es solo para el módulo tienda; en `ecoruta_db` no toca nada.)
4. Agente:
   ```
   cd sync-agent
   cp .env.example .env   # y completa WEB_BASE_URL + claves
   npm install
   npm start
   ```
   Panel: http://localhost:18650
5. Frontend: `VITE_API_URL` hacia `http://localhost:18650` (ya configurado en
   `frontend/.env` y `EcoRuta-app/.env`) y rebuild.
   (Así todos los pedidos pasan por el intermediario.)

## Operación diaria

- El agente sincroniza solo cada `POLL_SECONDS` (15 s).
- Botón **Sincronizar ahora** para forzar.
- **Cola pendiente**: pedidos offline esperando subir.
- **Conflictos**: solo `pago` (revisar) — un pedido pagado nunca se desmarca solo.
- Si un conflicto de pago aparece: decide en el panel → el servidor manda
  (conserva `pagado=1`) y se marca como revisado.

## Arranque automático en Windows

Opción A (simple): `shell:startup` → acceso directo a `start-agent.bat`
Opción B (servicio): NSSM → `nssm install EcoRutaSync "C:\Program Files\nodejs\node.exe" "C:\xampp\htdocs\ecoruta\sync-agent\src\server.js"`

## Archivos

- `src/server.js` — panel + scheduler
- `src/proxy.js` — proxy offline-first (inyecta `sync_uuid`)
- `src/sync.js` — push/pull idempotente
- `src/webClient.js`, `src/localDb.js`, `src/store.js`, `src/config.js`
- `data/` — `outbox.json`, `state.json`, `sync.log` (se crean solos; haz backup de `data/`)
