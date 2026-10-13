# EcoRuta Sync Hub (servicio aparte)

Hub central e independiente: **cualquier sistema web se conecta a él** para
intercambiar cambios sin duplicar. No depende del backend EcoRuta ni de ningún
sistema; si un sistema se cae, el hub sigue guardando lo de los demás.

## Cómo se conecta un sistema (5 minutos, cualquier stack)

1. Pide tu `X-System-Key` (se agrega en el `.env` del hub: `HUB_SYSTEMS`).
2. **Subir** cambios:
   `POST http://hub:4000/hub/push` con header `X-System-Key: <tu-clave>`:
   ```json
   { "items": [{ "uuid": "<uuid-v4-único>", "entity": "pedido",
     "action": "upsert", "payload": { "...tu objeto..." }, "ts": "2026-10-13T12:00:00" }] }
   ```
   Respuesta por item: `applied` | `duplicado` | `error`. Reintentar es seguro.
3. **Bajar** cambios:
   `GET http://hub:4000/hub/pull?since=2000-01-01%2000:00:00&limit=200`
   Guarda `max_ts` como tu cursor. El hub **excluye lo que tú mismo subiste**
   (anti-eco); con `?echo=1` lo incluye si lo necesitas.
4. `GET /hub/stats` para monitoreo. `GET /hub/health` público para vigilancia.

Reglas: `uuid` obligatorio y único (el hub lo exige); `entity` libre
(`pedido`, `pago`, `cliente`…); lotes de hasta 200; poda FIFO configurable.

## Correrlo

```
cd sync-hub
cp .env.example .env   # define PORT + HUB_SYSTEMS
npm install
npm start              # :4000
npm test               # 7 pruebas HTTP reales
```

## Escala honesta

Guarda en JSON local (decenas de miles de eventos sin problema). Si un día
mueve millones, el reemplazo es Postgres con el mismo contrato `/hub/*`
(los clientes no cambian). Para 500 nodos con eventos chicos sobra.
