# II.6–II.10 — Guía de demo en vivo y checklist de evaluación

> Esta guía sirve para **ensayar y ejecutar** la demostración. La numeración
> II.6–II.10 sigue la rúbrica del curso; los títulos son descriptivos y se
> pueden renombrar si el enunciado usa otro orden. Cada eje tiene su checklist
> con criterio de aceptación verificable.

## 6.1 Mapa de la demo

| Eje | Foco evaluado | Qué se muestra | Tiempo |
|---|---|---|---|
| **II.6** | Flujo funcional del MVP | Pedido → asignación → turno → QR → entrega → cobro | 7 min |
| **II.7** | Facturación y reglas fiscales | Timbrado, IVA, folio, KuDE/PDF, QR, anulación | 5 min |
| **II.8** | Arquitectura e integración | Componentes, contratos, offline/failover, manejo de errores | 3 min |
| **II.9** | Gestión ágil y DevOps | Backlog, velocity/burndown, commits, CI, despliegue | 3 min |
| **II.10** | Sostenibilidad, calidad y defensa | CO₂, pruebas, decisiones y brechas declaradas | 2 min |

**Regla de oro:** cada afirmación se respalda con una pantalla, un comando o un
archivo. Si algo no está implementado, se dice “fase 2” y se muestra el plan
(documento 04, sección 4.5).

## 6.2 Preparación

### T-1 día

- [ ] Confirmar que el push a `main` terminó el deploy de Render
      (`4a222e0` en adelante).
- [ ] Verificar que el timbrado de demo está **vigente** y con folios
      disponibles (`GET /api/timbrados/vigente`).
- [ ] Dejar cargados: 1 comercio, 1 repartidor con turno cerrado, 1 pedido
      pendiente, 1 pedido entregado con factura y 3 clientes.
- [ ] Reiniciar la contraseña de las cuentas de demo si no se recuerda.
- [ ] Descargar el PDF de la factura **30001** y guardarlo fuera de línea.
- [ ] Probar la cámara del teléfono con el QR del pedido de demo.

### T-30 minutos

```powershell
# API local
Invoke-RestMethod http://127.0.0.1:8000/api/health

# Agente (proxy + estado web/local)
Invoke-RestMethod http://localhost:18650/api/sync/health

# Web y KuDE público (producción; /kude entrega el PDF)
Invoke-WebRequest https://ecoruta-jaxf.onrender.com -UseBasicParsing | Select-Object StatusCode
Invoke-WebRequest https://ecoruta-jaxf.onrender.com/api/facturas/30001/kude -UseBasicParsing | Select-Object StatusCode, @{n='tipo';e={$_.Headers['Content-Type']}}
```

- [ ] Si el panel web abre pero las llamadas fallan, revisar en DevTools →
      Network a qué host apunta la API. El build puede estar usando
      `VITE_API_URL` (`http://localhost:18650`) o el fallback de producción;
      **debe coincidir con el backend encendido**.
- [ ] Abrir el panel del agente (`http://localhost:18650`) y confirmar
      `online_web` o `online_local` según el modo de la demo.
- [ ] Cerrar pestañas y notificaciones; zoom del navegador al 100 %.
- [ ] Tener a mano: esta guía, el PDF 30001 y la planilla de métricas.

### T-5 minutos

- [ ] Login de administrador probado (y logout).
- [ ] Login de comerciante probado.
- [ ] App del repartidor abierta y con sesión.
- [ ] Modo avión probado y desactivado (para el demo offline).
- [ ] Cronómetro listo: 20 minutos.

## 6.3 Datos de prueba sugeridos

| Recurso | Valor sugerido | Para qué |
|---|---|---|
| Comercio | “Comercio Demo”, tarifa base 12.000 | Pedido y tarifa |
| Repartidor | “Repartidor Demo”, bicicleta, disponible | Turno y entrega |
| Pedido pendiente | Origen/destino en Asunción, 5 km, 2 kg, 10×20×30 cm | Tarifa 29.000 y CO₂ 0,9 kg |
| Pedido entregado | Con factura emitida | Historial y KuDE |
| Cliente | `María Rodríguez`, RUC/CI `1234567` | Factura con receptor |
| CSV | `nombre;direccion;lat;lng` (3 filas) | Importación masiva |
| Factura histórica | **30001** | Verificación de importes |

> Las cuentas semilla del repositorio son `admin@ecoruta.com` y
> `carlos.mendoza@ecoruta.com` (`database/seed_operadores.sql`). La contraseña
> no está documentada en el repo: `[COMPLETAR]` antes del demo o restablecerla
> con “Recuperar contraseña”.

## 6.4 Guion minuto a minuto

### 0:00–0:01 — Apertura

- Presentar el problema: última milla urbana, repartidores con conectividad
  intermitente y comercios que necesitan trazabilidad y comprobante.
- Aclarar el alcance: módulo fiscal **pre-fiscal**; SIFEN real es fase 2.

### 0:01–0:04 — II.6 Alta de pedido (comerciante)

1. Login como comerciante.
2. Nuevo pedido: origen, destino, peso 2 kg, dimensiones 10×20×30, 5 km.
3. Mostrar la tarifa calculada: **29.000 Gs** (12.000 + 12.500 + 3.000 + 1.500).
4. Mostrar el CO₂ evitado: **0,9 kg** (5 km × 0,180).
5. Confirmar y abrir el QR del pedido.

**Evidencia:** la tarifa no se edita a mano; la calcula el backend.

### 0:04–0:07 — II.6 Asignación, turno y entrega

1. Login como administrador → asignar el pedido al repartidor.
2. En la app: iniciar turno, abrir el pedido, mostrar el mapa.
3. Escanear el QR correcto → confirmar entrega.
4. (Opcional) escanear un QR de otro pedido → mensaje de rechazo.
5. Mostrar el pedido en “Entregado” y la notificación al comercio.

**Evidencia:** el estado queda con historial y fecha de entrega; la factura se
emite automáticamente si hay timbrado.

### 0:07–0:12 — II.7 Facturación

1. Abrir “Facturas” → la factura del pedido entregado.
2. Mostrar folio `EEE-PPP-NNNNNNN`, timbrado, RUC y receptor.
3. Abrir el documento: la fila “KuDE” y “PDF” muestran el mismo comprobante en PDF.
4. Verificar en voz alta: `SUBTOTAL + IVA = TOTAL`, sin decimales.
5. Escanear el QR del PDF → vuelve a abrir el PDF del comprobante.
6. Abrir la factura **30001** y comparar con el PDF guardado:
   `951.095 + 95.109 = 1.046.204` y `TOTAL IVA 95.109`.
7. (Opcional) Anular una factura de prueba con motivo y mostrar la auditoría.

**Evidencia:** el QR es una URL pública, no una imagen decorativa.

### 0:12–0:15 — II.8 Arquitectura y resiliencia

1. Mostrar el diagrama de componentes (documento 02).
2. Explicar la composición: panel/app → agente → API → servicios → MySQL;
   `factura-py` como adaptador de PDF.
3. Demostrar el modo offline: activar modo avión, cambiar un estado, mostrar la
   cola; recuperar señal y ver la sincronización en orden.
4. Mostrar un error controlado: intentar emitir sin timbrado vigente → `422`
   con mensaje claro (en local, no en producción).

**Evidencia:** la app no pierde operaciones y el servidor no duplica pedidos
(`sync_uuid` + `UNIQUE`).

### 0:15–0:18 — II.9 Gestión ágil y DevOps

1. Abrir `docs/metricas/EcoRuta-Velocity.xlsx`:
   - Backlog con 20 HU y 103 puntos.
   - Velocity con `=SUMAR.SI` y gráfico.
   - Burndown S4/S5.
2. Mostrar 2–3 commits reales: `git show 4a222e0 --stat`,
   `git show 07d5d2f --stat`.
3. Mostrar el workflow de CI y aclarar que es **advisory** (no bloquea).
4. Mostrar el `Dockerfile`: PHP 8.2 + Apache + Node 22 + Chromium.

### 0:18–0:20 — II.10 Sostenibilidad, calidad y cierre

1. Panel de métricas: CO₂ acumulado, km, ingresos, tiempos.
2. Pruebas: `cd EcoRuta-app; npm test` (8 pruebas) y `npm run build`.
3. Declarar brechas con honestidad:
   - SIFEN real (XML firmado, CDC oficial) = fase 2.
   - “Firma” de entrega = registro nominal, no trazo biométrico.
   - Un solo autor ⇒ sin revisión cruzada; se compensa con CI y checklist.
4. Cierre: “el sistema funciona hoy, y sabemos exactamente qué falta para
   producción fiscal”.

## 6.5 Checklist por eje

### II.6 — Flujo funcional del MVP

- [ ] El comerciante crea un pedido y ve la tarifa antes de confirmar.
- [ ] La tarifa coincide con la fórmula documentada.
- [ ] El administrador asigna el pedido.
- [ ] El repartidor inicia turno y ve sólo sus pedidos.
- [ ] El QR correcto confirma; uno incorrecto se rechaza.
- [ ] La entrega deja historial, fecha y notificación.
- [ ] El cobro en efectivo calcula el vuelto.

**Aprueba si:** el ciclo completo se ejecuta sin tocar la base de datos y sin
errores en consola.

### II.7 — Facturación y reglas fiscales

- [ ] Sin timbrado vigente la emisión se bloquea (422) y no consume folio.
- [ ] El folio es correlativo y único.
- [ ] `gravada + IVA = total` en el resumen y en la liquidación.
- [ ] Los importes se muestran en guaraníes enteros.
- [ ] El PDF incluye emisor, receptor, timbrado, folio, CDC y QR.
- [ ] El QR reabre el PDF público del comprobante.
- [ ] La anulación exige motivo y queda auditada.
- [ ] La factura 30001 coincide con el PDF real.

**Aprueba si:** no hay decimales y ninguna suma falla.

### II.8 — Arquitectura e integración

- [ ] Se distinguen componentes y sus contratos (proporciona/requiere).
- [ ] La API valida rol y propiedad del recurso.
- [ ] El modo offline encola y reintenta en orden.
- [ ] Un `422` va a conflicto visible; un `401` corta la cola.
- [ ] El failover web/local no pierde ni duplica pedidos.
- [ ] Los errores se devuelven en JSON con código HTTP correcto.

**Aprueba si:** se puede explicar qué pasa cuando se cae la red o la web.

### II.9 — Gestión ágil y DevOps

- [ ] Backlog con 20 HU, prioridad, puntos y estado.
- [ ] Velocity y burndown con fórmulas y gráficos.
- [ ] Los números se pueden reconstruir desde Git.
- [ ] Hay commits atómicos con causa raíz.
- [ ] El pipeline de CI existe y su alcance está declarado.
- [ ] El despliegue está documentado (Dockerfile, variables, Render).
- [ ] DoR/DoD escritos y aplicados.

**Aprueba si:** cualquier número de la planilla se puede explicar y rastrear.

### II.10 — Sostenibilidad, calidad y defensa

- [ ] El CO₂ se calcula con una fórmula única y visible.
- [ ] El panel muestra CO₂, km, ingresos y tiempos.
- [ ] Las pruebas de QR y offline pasan.
- [ ] El build del panel pasa.
- [ ] Las brechas se declaran sin maquillaje.
- [ ] Cada integrante sabe explicar su parte.

**Aprueba si:** las limitaciones se explican como decisiones, no como olvidos.

## 6.6 Demos especiales

### 6.6.1 Cierre de sesión por token vencido (HU-16)

**Opción A — temporizador (local):**

1. En `backend/.env`, poner `JWT_EXPIRE=120` y reiniciar el backend.
2. Iniciar sesión en el panel.
3. Esperar 2 minutos sin interactuar.
4. La app vuelve sola al login y borra `ecoruta_token` / `ecoruta_user`.

**Opción B — 401 (sirve en producción):**

1. Iniciar sesión y abrir DevTools → Console.
2. Invalidar el token:
   ```js
   const t = localStorage.getItem('ecoruta_token');
   localStorage.setItem('ecoruta_token', t.slice(0, -2) + 'xx');
   ```
3. Provocar una llamada (recargar el listado).
4. El backend responde `401` y la sesión se cierra.

**Evidencia:** `frontend/src/services/authSession.js` y el temporizador de
`App.jsx` (sección “Vencimiento de sesión” del documento 02).

### 6.6.2 Bloqueo por timbrado (sólo local)

1. Crear un timbrado con `fecha_fin` anterior a hoy (o desactivar el vigente).
2. Intentar facturar un pedido entregado.
3. Mostrar `422` y confirmar que la secuencia no cambió.
4. Reactivar el timbrado correcto al terminar.

### 6.6.3 Importación CSV de clientes

1. Abrir “Clientes” → Importar.
2. Usar `nombre;direccion;lat;lng` (opcionales: `ruc_ci;telefono;referencia`).
3. Mostrar filas válidas, errores por fila y el resultado final.

### 6.6.4 Offline real

1. Abrir la app, cargar pedidos (quedan cacheados).
2. Modo avión.
3. Cambiar estado y registrar cobro.
4. Mostrar la cola (`ecoruta_outbox_v1`) y el estado “pendiente”.
5. Recuperar señal y mostrar la subida en orden.
6. Verificar en el panel que no hay duplicados.

## 6.7 Preguntas frecuentes (con respuestas honestas)

| Pregunta | Respuesta sugerida |
|---|---|
| ¿Esto ya es válido ante la SET? | No. Es **pre-fiscal**: timbrado, folio, IVA y QR propios. La firma XML y la autorización SIFEN son fase 2 (documento 04, 4.5). |
| ¿El CDC es el oficial? | No: es un HMAC-SHA256 de control interno de 64 hex. El CDC oficial de 44 dígitos requiere el algoritmo SET. |
| ¿La firma del receptor es biométrica? | No. Se registra el nombre y una nota; el QR sí se valida contra el pedido. Está declarado como limitación. |
| ¿Cómo evitan pedidos duplicados? | `sync_uuid` en origen, `UNIQUE` en base y búsqueda previa; un `pagado=1` no se revierte. |
| ¿Qué pasa si Render está frío? | El agente reintenta antes de degradar a local; el escritorio muestra web/local y permite forzar. |
| ¿Por qué un solo autor? | Es el historial real. Se compensa con CI, checklist DoD y pruebas; no se inventaron revisiones. |
| ¿De dónde sale 0,180 kg CO₂/km? | Es el factor centralizado en `orders_service.php`. `[COMPLETAR]` la fuente en la defensa si se exige cita. |
| ¿Cuánta cobertura hay? | Hay 8 pruebas de QR y offline en la app móvil; no se mide cobertura global todavía. |
| ¿Cómo se protege el dinero? | Cálculo en backend, IVA extraído, importes enteros, validación de pago y vuelto, y pruebas contra el PDF real. |
| ¿Qué falta para producción? | SIFEN real, monitoreo/alertas, respaldo de base, revisión de seguridad y pruebas de carga. |

## 6.8 Plan de contingencia

| Si falla… | Hacer… |
|---|---|
| Render no responde (cold start) | Esperar 60 s, reintentar; si sigue, usar el entorno local y mostrarlo como failover. |
| La cámara no lee el QR | Usar la opción de cargar una imagen del QR o mostrar el payload y validarlo. |
| No hay internet | Ejecutar el guion en local con XAMPP + agente; el demo offline ya es parte de la historia. |
| La base está vacía | Cargar el seed y crear un pedido en vivo (2 minutos). |
| El PDF no genera | Mostrar el HTML (fallback) y explicar la detección de Chromium ausente. |
| Una suma no cuadra | Abrir la factura 30001 verificada y explicar la normalización de datos históricos. |
| Se cae la demo | Saltar al checklist del eje afectado y mostrar el archivo/código que lo respalda. |

## 6.9 Hoja de evaluación (para el evaluador)

| Eje | Peso | 1 | 2 | 3 | 4 | 5 | Observaciones |
|---|---|---|---|---|---|---|---|
| II.6 Flujo MVP | | | | | | | |
| II.7 Facturación | | | | | | | |
| II.8 Arquitectura | | | | | | | |
| II.9 Ágil/DevOps | | | | | | | |
| II.10 Sostenibilidad/defensa | | | | | | | |

**Criterios transversales:** claridad de la explicación, coherencia entre lo
dicho y lo mostrado, manejo de preguntas y honestidad sobre las brechas.

## 6.10 Ensayo rápido (checklist de 10 puntos)

- [ ] Los tres logins funcionan.
- [ ] Un pedido nuevo muestra tarifa y CO₂ correctos.
- [ ] La asignación llega al repartidor.
- [ ] El QR válido confirma y el inválido se rechaza.
- [ ] La factura se emite con folio y sin decimales.
- [ ] El QR del PDF abre el mismo PDF público.
- [ ] La factura 30001 cuadra.
- [ ] El modo offline encola y sincroniza.
- [ ] La planilla abre con fórmulas y gráficos.
- [ ] El cierre de sesión por vencimiento se explica y se muestra.
