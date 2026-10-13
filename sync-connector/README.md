# Conector universal (vive junto a cada sistema)

Puente entre **un** sistema y el **Hub**. Cero dependencias (`node` pelado).

## Cómo conecta un sistema (cualquiera)

1. Copia `.env.example` a `.env`: `HUB_URL`, `SYSTEM_NAME`, `SYSTEM_KEY`.
2. **Para subir**: tu sistema deja archivos `{items:[{uuid, entity, action, payload}]}` en `data/drop/`.
   El conector los sube al hub y los archiva en `data/sent/`. Si algo falla,
   el archivo queda y se reintenta (el hub deduplica: reenviar es seguro).
3. **Para bajar**: el conector trae lo nuevo con cursor persistido, lo guarda en
   `data/inbox/` y, si defines `CALLBACK_URL`, se lo POSTea a tu sistema para
   auto-aplicar. Si tu sistema no responde, el cursor **no avanza** (backpressure:
   nada se pierde ni se salta).
4. `npm start` (cada `POLL_SECONDS`). Logs a consola.

## Preset EcoRuta

`src/translate.js`: fila EcoRuta → envelope hub (`toHubEnvelope`, el uuid ES el
`sync_uuid`) y vuelta (`toEcorutaPush` → `{pedidos, ventas, replays}` listo para
`POST /api/sync/push`). Ejemplo de callback para auto-aplicar en tu API:

```js
// tu-callback.js: recibe {items} del hub y los aplica en tu base/API
import { toEcorutaPush } from './translate.js';
const body = toEcorutaPush(items);
await fetch('https://tu-api/api/sync/push', {
  method: 'POST',
  headers: { 'content-type': 'application/json', 'x-sync-key': process.env.SYNC_API_KEY },
  body: JSON.stringify(body),
});
```

## Tests

`npm test` — 9 pruebas contra un hub falso: traducción con uuid, drop→push→sent,
inbox+cursor, y freno de cursor con callback caído.
