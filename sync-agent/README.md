# EcoRuta · Sync Agent (intermediario PC)

Servicio Node.js que corre **dentro de la PC** y actúa como intermediario entre tu
**servidor web** y tu **servidor local (XAMPP)**.

## Cómo evita tus dos errores

1. **Facturas duplicadas → clave de idempotencia `sync_uuid`**
   - Cada venta lleva un UUID v4 generado en origen.
   - `POST /api/ventas` y `POST /api/sync/push` buscan primero por `sync_uuid`:
     si ya existe, devuelven la existente **sin insertar de nuevo**.
   - Segunda barrera: `UNIQUE(numero_factura)`. Si el folio choca con otra venta,
     la web **reasigna el siguiente folio libre** y el agente corrige el local.
     Nunca hay dos filas con el mismo folio ni con el mismo uuid.

2. **Stock negativo → validación autoritativa + triggers**
   - El PHP valida con `SELECT ... FOR UPDATE` dentro de transacción
     (`VentaService::registrarVenta` ya lo hacía; el sync lo reutiliza).
   - Migración 44 agrega triggers `trg_insumo_no_negativo` y
     `trg_stockprod_no_negativo`: aunque algo intente restar de más, MySQL aborta.
   - Si la web no tiene stock suficiente para una venta offline, el push responde
     `conflicto_stock`: la venta **no se descuenta en la web**, queda en el panel
     para revisión manual (anular local o reponer stock). Nunca resta a ciegas.

## Flujo

```
Frontend  --->  http://localhost:18650  (este agente)
                    |  web online? --sí-->  WEB (y pull posterior al local)
                    |  web caída?  --no-->  LOCAL XAMPP + outbox/*.json
Al volver internet: push outbox -> web (/api/sync/push idempotente)
                    pull web -> local (/api/sync/pull?since=)
```

## Instalación (una vez)

1. Backend **local y web**: misma versión del código (con `SyncController`,
   `SyncAuthMiddleware`, rutas `/api/sync/*` y parche de `VentaRepository`).
2. En ambos `.env`: misma `SYNC_API_KEY` (genera con
   `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`).
3. Corre la migración 44 en **ambas** DBs:
   `php backend/database/migrations/44_add_sync_idempotency.php`
4. Agente:
   ```
   cd sync-agent
   cp .env.example .env   # y completa WEB_BASE_URL + claves
   npm install
   npm start
   ```
   Panel: http://localhost:18650
5. Frontend: cambia `VITE_API_URL` a `http://localhost:18650` y rebuild.
   (Así todas las ventas pasan por el intermediario.)

## Operación diaria

- El agente sincroniza solo cada `POLL_SECONDS` (15 s).
- Botón **Sincronizar ahora** para forzar.
- **Cola pendiente**: ventas offline esperando subir.
- **Conflictos**: solo `stock` (revisar) o `folio_reasignado` (auto-corregido).
- Si un conflicto de stock aparece: decide en el panel → anula la venta local
  (`DELETE /api/ventas/{id}`) o carga stock y reencola.

## Arranque automático en Windows

Opción A (simple): `shell:startup` → acceso directo a `start-agent.bat`
Opción B (servicio): NSSM → `nssm install EcoRutaSync "C:\Program Files\nodejs\node.exe" "C:\xampp\htdocs\ecoruta\sync-agent\src\server.js"`

## Archivos

- `src/server.js` — panel + scheduler
- `src/proxy.js` — proxy offline-first (inyecta `sync_uuid`)
- `src/sync.js` — push/pull idempotente
- `src/webClient.js`, `src/localDb.js`, `src/store.js`, `src/config.js`
- `data/` — `outbox.json`, `state.json`, `sync.log` (se crean solos; haz backup de `data/`)
