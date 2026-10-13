# Guía de conexión EcoRuta — web ↔ local, paso a paso

La **app de escritorio** (`desktop-app/`) es la pieza universal: muestra la web,
cambia sola al sistema local si la web cae, y al volver sincroniza y regresa.
Vale para cualquier sistema cambiando URLs en **Conexiones**. Esta guía explica
cada conexión, en orden, sin asumir nada.

```
┌─────────────┐   internet   ┌──────────────────┐   sync-key   ┌───────────┐
│  FRONTEND   │─────────────▶│  API WEB (Render)│◀─────────────│  AGENTE   │
│ Vercel/:5173│              │   + TiDB Cloud   │   push/pull  │ PC oficina│
└─────────────┘              └──────────────────┘              └─────┬─────┘
      │                                                             │ proxy
      │ caida de internet                                           ▼
      └──────────────────────────────────────────────▶┌──────────────────┐
                                                      │ API LOCAL (XAMPP)│
                                                      │  + MySQL local   │
                                                      └──────────────────┘
```

## Pieza 1 — La web (Render + TiDB), una sola vez

1. **Deploy**: el servicio compila el `Dockerfile` de la raíz con `main`. Verifica
   en Render que el deploy esté *Live* en el commit actual.
2. **Environment** del servicio (con **Save Changes** + esperar *Live*):
   | KEY | VALUE |
   |---|---|
   | `DB_HOST` | host TiDB (ej. `gateway01...tidbcloud.com`) |
   | `DB_PORT` | `4000` |
   | `DB_NAME` | nombre exacto de la base con `pedidos` |
   | `DB_USER` | usuario exacto (con sufijo, ej. `xxx.root`) |
   | `DB_PASSWORD` | clave vigente, **escrita a mano** (el portapapeles mete espacios) |
   | `DB_SSL` | `true` |
   | `SYNC_API_KEY` | 64 hex, **igual** que en `backend/.env` y `sync-agent/.env` |
   | `SYNC_DB_NAME` | igual que `DB_NAME` |
3. **TiDB Cloud → IP Access List**: permitir las salidas de Render (`0.0.0.0/0`
   para probar; luego restringir si quieres).
4. **Migraciones en la web** (Render → **Shell**):
   ```
   php database/migrations/45_add_sync_pedidos.php
   php database/migrations/46_create_sync_devices.php
   ```
   Deben decir `OK`. (La 44 es solo módulo tienda; en base delivery se salta sola.)
5. **Verificar** (PowerShell, con tu clave):
   ```
   Invoke-RestMethod "https://TU-SERVICIO.onrender.com/api/sync/health"
   Invoke-RestMethod "https://TU-SERVICIO.onrender.com/api/sync/pull?since=2000-01-01%2000:00:00&limit=1" -Headers @{"x-sync-key"="TU_CLAVE"}
   ```
   El pull debe dar `success:true` y filas **con** `sync_uuid` y `updated_at`.
   Sin header da 401 a propósito (así se prueba que el guard funciona).

## Pieza 2 — La PC local/oficina, una sola vez

1. **MySQL (XAMPP)** corriendo, base `ecoruta_db` existente.
2. **`backend/.env`**: `DB_*` hacia tu MySQL + misma `SYNC_API_KEY` + `SYNC_DB_NAME`
   con el nombre de tu base local.
3. **Migraciones locales**:
   ```
   php backend\database\migrations\45_add_sync_pedidos.php ecoruta_db
   php backend\database\migrations\46_create_sync_devices.php ecoruta_db
   ```
4. **`sync-agent/.env`** (copiar de `.env.example`): `LOCAL_*` a tu MySQL,
   `WEB_BASE_URL` a tu Render, misma `SYNC_API_KEY` en ambas claves.
   Luego `cd sync-agent && npm install`.
5. **Frontends**: `frontend/.env` → `VITE_API_URL=http://localhost:18650`,
   `EcoRuta-app/.env` → `VITE_API_URL=http://localhost:18650/api`,
   y `npm run build` en cada uno.
6. **Levantar todo**: doble clic a `iniciar-ecoruta.bat` (MySQL → API `:8000` →
   agente `:18650` → web `:5173`). Verificar:
   `http://127.0.0.1:8000/api/health`, `http://localhost:18650/agent/status`.

## Pieza 3 — App de escritorio (la universal)

1. Instala `desktop-app/dist-installer/EcoRuta Setup X.Y.Z.exe`
   (aviso "editor desconocido" → *Más información → Ejecutar de todas formas*).
2. Al abrir, levanta sola el stack local si falta (ventana **Conexiones** muestra
   el resultado por servicio; fallos en notificación + `%APPDATA%/ecoruta-desktop/logs/autostart.log`).
3. **Conexiones, campo por campo**:
   - *App web*: lo que ven los usuarios (ej. tu Vercel). Se muestra con internet.
   - *App local*: tu frontend en la PC (`http://localhost:5173`). Se muestra sin internet.
   - *API web*: backend Render. **No se ve: se vigila** (cada N segundos decide el cambio).
   - *API local*: tu backend (`http://127.0.0.1:8000`). Atiende sin internet.
   - *Agente sync*: intermediario (`http://localhost:18650`) que sincroniza.
   - *Clave sync*: la misma `SYNC_API_KEY`. Queda en tu PC, nunca en el repo.
   - *Carpeta del sistema local*: dónde están `backend/`, `sync-agent/`, `frontend/`
     (autodetecta `C:\xampp\htdocs\ecoruta`; si tu repo está en otro lado, ponlo o usa *Detectar carpeta*).
   - Botones *Probar* verifican cada API; *Guardar* aplica; *Restablecer* vuelve a fábrica.
4. Bandeja: estado WEB/LOCAL, modo auto/forzar, Sincronizar ahora, salir.
5. Nota: web y local son dominios distintos → **inicia sesión una vez en cada lado**.

## Primera sincronización (convergencia)

1. Reinicia el agente (`detener` + `iniciar`) para que tome el código actual.
2. Panel del agente (`http://localhost:18650`) → debe decir `online_web`.
3. **Subir faltantes** (encola locales ausentes en web, sin duplicar) → **Sincronizar ahora**.
4. Verifica: `SELECT COUNT(*) FROM pedidos` en ambas bases + cola en 0.
5. Conflictos: botón **Reintentar** (re-enqueue) o **Revisado** (archivar).

## Lugares sin internet (nodo remoto + pendrive)

PC remota con todo instalado (`iniciar-ecoruta.bat` funciona sin internet):
1. Remota → panel → **Exportar cola** → pendrive.
2. Conectada → **Importar cola** → se sube sola a la web.
3. Conectada → **Exportar novedades web** → pendrive.
4. Remota → **Importar novedades** → aplica (`duplicado` lo ya visto).
Importar dos veces no duplica (idempotencia por `sync_uuid`).

## App del repartidor (nodo mula)

`EcoRuta-app/.env` → API web. Sin señal: ve su ruta guardada, entrega/cobra/cancela
en el teléfono (banner amarillo, contador por sincronizar); al volver la señal
sube solo en orden. Regenerar APK (`android:apk`) para distribuir.

## Fallas comunes (todas vividas)

| Síntoma | Causa | Fix |
|---|---|---|
| `X-Sync-Key inválido` (401) | clave distinta/ausente en un lado | igualar `SYNC_API_KEY` en los 3 `.env` + Render |
| `Connection refused` (web) | TiDB no deja entrar a Render | IP Access List `0.0.0.0/0` + `DB_SSL=true` |
| `1045 Access denied` | `DB_PASSWORD` vieja o con espacio | re-escribir a mano, Save, esperar *Live* |
| Pull sin `sync_uuid` | falta migración 45 en esa base | correrla ahí |
| Ventana blanca LOCAL | nada corre en `:5173` | `iniciar-ecoruta.bat` o app 1.0.2+ (levanta sola) |
| `win-unpacked.tmp` bloqueado al compilar | watchers (VS Code/AI) agarran archivos | compilar fuera del repo (`C:\Temp\ecoruta-build`) |
| Login web 500 | `DB_PASSWORD` vieja en ese servicio | actualizarla ahí |
| `/api/auth/login` 404 en navegador | es solo POST | probar desde la app, no del navegador |
