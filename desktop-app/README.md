# EcoRuta Desktop (app de escritorio conmutadora)

Una ventana que muestra el sistema **web** y, si se cae, pasa **sola al local**;
al volver, dispara la sincronización del agente y regresa a la web.
Incluye gestor de conexiones (vale para cualquier sistema: solo cambia URLs)
y levanta el stack local (PHP + Sync Agent + web) como `iniciar-ecoruta`.

## Usar

```
cd desktop-app
npm install
npm start
```

- Bandeja del sistema: estado WEB/LOCAL, modo (automático / forzar web / forzar local),
  Sincronizar ahora, Verificar conexiones, Conexiones…, Salir.
- Ventana principal = el sistema en uso. Ventana Conexiones = ajustes + pruebas.
- La configuración vive en `%APPDATA%/ecoruta-desktop/` (fuera del repo);
  la clave sync nunca se commitea.

## Smoke test (sin ventanas)

```
npm run smoke
```

## Empaquetar a .exe (cuando lo pidas)

`npm install --save-dev electron-builder` + config `build.win`. Requiere
internet para descargar las herramientas la primera vez.

## Notas honestas

- Web y local son orígenes distintos: la sesión se inicia **una vez en cada
  lado** (los tokens no se comparten entre dominios). Quedan guardados por separado.
- El failover vigila la **API web** (`/api/sync/health` con caída a `/api/health`),
  no la página pública: si la API responde, se considera en línea.
- El stack local necesita `php` y `node` en el PATH, `sync-agent/.env` y su
  `npm install`, y `frontend/dist` compilado.
