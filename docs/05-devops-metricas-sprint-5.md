# I.5 — DevOps, métricas y cierre del Sprint 5

## 5.1 Estrategia de ramas y commits

### Estado real del repositorio

| Aspecto | Situación verificada |
|---|---|
| Remoto | `github.com/mathiasparedes202-droid/ecoruta` |
| Rama de trabajo | `main` (única rama local y remota) |
| Ramas de funcionalidad | Ninguna: el trabajo se integró directo en `main` |
| Pull Requests | 0 |
| Tags / releases | Ninguno |
| Autores | 1 (`mathiasparedes202-droid`) |
| Commits | 96 de desarrollo hasta `4a222e0` (más el commit de esta documentación) |

El flujo aplicado fue **trunk-based**: commits pequeños y temáticos directo sobre
`main`, con mensajes que describen el problema resuelto más que el archivo
tocado. Ejemplos reales: `Fix: PDF de factura fallaba con 'falta Chromium'`,
`IVA: extraer con /11 (10%) y /21 (5%)`, `sync-agent: no degradar a local si la
web solo tardo`.

> **Limitación declarada:** al ser un proyecto de un solo autor no hubo revisión
> cruzada ni PRs. La rúbrica valora el historial; por eso se documentan los
> commits reales en la sección 5.6 y no se inventan revisiones. Si el equipo
> suma integrantes, el flujo recomendado es: rama `feat/<hu>`, PR con checklist
> de la sección 5.3 y merge squash a `main`.

### Convención de mensajes

```
<área>: <qué se corrigió o agregó>
Fix: <síntoma observable>            ← para bugs
```

Se prioriza que el mensaje permita reconstruir el *porqué* sin abrir el diff.
No se usa Conventional Commits estricto (`feat:`, `fix:`) de forma consistente;
es una mejora propuesta para el próximo ciclo.

## 5.2 Integración continua

### Workflow activo

`.github/workflows/react-doctor.yml` se ejecuta en:

- `pull_request` (opened, synchronize, reopened, ready_for_review);
- `push` a `main`.

Pasos: `actions/checkout@v5` con `fetch-depth: 0` y
`millionco/react-doctor@v2`. El análisis reporta hallazgos de seguridad,
rendimiento, correctitud, accesibilidad, tamaño de bundle y arquitectura
**como comentario/estado, sin bloquear** (modo advisory). La configuración
`doctor.config.json` excluye `sandbox/**` y `backend/public/js/**`.

> Existe una copia en `frontend/.github/workflows/react-doctor.yml`. GitHub sólo
> lee `.github/` en la raíz del repositorio, por lo que esa copia **no se
> ejecuta**; se conserva como referencia y no debe confundirse con un segundo
> pipeline.

### Verificaciones locales usadas antes de cada push

| Verificación | Comando | Alcance |
|---|---|---|
| Sintaxis PHP | `php -l backend/src/services/facturacion_service.php` | Archivos modificados |
| Sintaxis Node | `node --check factura-py/services/factura.template.js` | Motor de facturas |
| Build del panel | `cd frontend && npm run build` | React + Vite |
| Pruebas de la app móvil | `cd EcoRuta-app && npm test` | Vitest (QR + offline) |
| Copias vendorizadas | `Get-FileHash` de ambos `factura-py` | Deben ser idénticas |

`EcoRuta-app` tiene 8 pruebas en `src/lib/qr.test.ts` y
`src/lib/offlineSync.test.ts`: payload estable, generación/lectura de QR,
validación contra el pedido esperado, caché sin red, subida FIFO, conflicto
`422`, corte por `401` y no borrar datos sin red.

## 5.3 Definition of Ready y Definition of Done

### DoR — una historia entra al sprint si…

- [ ] Está escrita como *Como \<rol\> quiero \<acción\> para \<beneficio\>*.
- [ ] Tiene criterios de aceptación en Gherkin (al menos el camino feliz y un error).
- [ ] Está estimada en Fibonacci y priorizada (MoSCoW).
- [ ] Tiene dependencias identificadas (tablas, endpoints, permisos).
- [ ] Existe un boceto de UI o contrato de API acordado.
- [ ] Se conocen los datos de prueba necesarios.

### DoD — una historia se considera terminada si…

- [ ] El código está en `main` con un commit atómico y mensaje claro.
- [ ] `php -l` / `node --check` / `npm run build` pasan sin errores.
- [ ] Las pruebas del módulo afectado están en verde (o se documenta por qué no aplica).
- [ ] El flujo completo se probó de punta a punta con los tres roles.
- [ ] La migración (si hubo) es idempotente y se aplicó en local **y** en la web.
- [ ] Los permisos por rol están verificados (comerciante / repartidor / admin).
- [ ] La documentación o el contrato de API quedó actualizado.
- [ ] No se agregaron secretos, `node_modules`, `vendor` ni artefactos pesados.
- [ ] Si toca dinero o fiscalidad, los importes cuadran a guaraníes enteros.

## 5.4 Velocity

Planilla: [`metricas/EcoRuta-Velocity.xlsx`](metricas/EcoRuta-Velocity.xlsx),
hoja **Velocity** (los valores se calculan con `=SUMIF(...)`, que Excel en
español muestra como `=SUMAR.SI(...)`).

| Sprint | Ventana | Comprometidos | Completados | Velocity | % cumplimiento |
|---|---|---|---|---|---|
| S1 | 01-09 → 07-09-2026 | 11 | 11 | 11 | 100 % |
| S2 | 08-10 → 13-10-2026 | 18 | 18 | 18 | 100 % |
| S3 | 14-10 → 20-10-2026 | 28 | 26 | 26 | 92,9 % |
| S4 | 21-10 → 29-10-2026 | 27 | 27 | 27 | 100 % |
| S5 | 29-10 → 20-11-2026 | 19 | 19 | 19 | 100 % |
| **Total** | | **103** | **101** | **20,2 (prom.)** | **98,1 %** |

La única diferencia entre comprometido y completado es **HU-08** (confirmación
de entrega): el QR está implementado y probado, pero la “firma” es nominal (se
registra el nombre de quien recibe, no un trazo), por lo que se acreditaron 6 de
8 puntos.

### Lectura honesta de los números

- Las ventanas tienen duraciones muy distintas (S1: 7 días; S5: 23 días), así
  que **la velocity no es comparable entre sprints** sin normalizar. En
  puntos/día: S1 ≈ 1,6 · S2 ≈ 3,0 · S3 ≈ 3,7 · S4 ≈ 3,0 · S5 ≈ 0,8.
- El S5 incluyó documentación, hardening y correcciones que no se estimaron en
  puntos; por eso su velocity es baja aunque el esfuerzo fue alto.
- El promedio de 20,2 puntos es una referencia para planificar, no una promesa.

### Cómo regenerar la planilla

```powershell
# openpyxl sólo se necesita para generar el archivo, no es dependencia del proyecto
python -m pip install --target "$env:TEMP\opencode\xls" openpyxl
$env:PYTHONPATH="$env:TEMP\opencode\xls"
python tools/generar_planilla_metricas.py
```

## 5.5 Burndown

Hojas **Burndown S4** y **Burndown S5** del libro. La serie ideal es lineal
desde los puntos comprometidos hasta 0; la serie real se **reconstruyó con la
fecha de cierre de cada historia** (fecha del commit que la entregó). No se
inventaron valores diarios: los días sin commits mantienen el saldo anterior.

| Sprint | Día 0 | Punto de control | Cierre |
|---|---|---|---|
| S4 | 27 | día 7 (28-10): 6 restantes | día 8 (29-10): 0 |
| S5 | 19 | día 1 (30-10): 0 restantes | se mantiene en 0 |

> **Sobre el S5:** el trabajo funcional se concentró el 29-10 y el resto del
> ciclo se dedicó a documentación, sincronización de copias y hardening
> (commit del 20-11). El burndown lo refleja como una caída temprana; es un
> artefacto de la granularidad de los commits, no un error de cálculo.

## 5.6 Evidencia de commits del cierre

| Commit | Fecha | Aporte | Archivos |
|---|---|---|---|
| `34b90ac` | 28-10 | Validación fiscal completa, montos que cuadran y QR que reabre la factura | Backend + frontend |
| `d058ada` | 28-10 | Secreto JWT único: la descarga de facturas deja de fallar | `src/config/jwt.php` |
| `f4ad41a` | 29-10 | El agente no degrada a local si la web sólo tardó (cold start de Render) | `sync-agent/` |
| `9ab94bb` | 29-10 | PDF resiliente cuando falta Chromium | `factura-py/` |
| `1baef3c` | 29-10 | Fin del timeout de 30 s en Puppeteer | `factura-py/` |
| `253fda0` | 29-10 | IVA extraído con `/11` y `/21` en facturas, ventas y compras | Backend |
| `895e0ed` | 29-10 | IVA en guaraníes enteros: 7.000 → 6.364 + 636 | Backend |
| `07d5d2f` | 29-10 | Totales de factura y cierre de sesión vencida | Backend + frontend |
| `7bbf5d6` | 29-10 | Importación masiva de clientes por CSV | Frontend + backend |
| `366ca29` | 29-10 | RUC/CI en el alta de clientes | Backend + frontend |
| `4a222e0` | 20-11 | PDF de la factura 30001 con montos enteros y totales conciliados | `factura-py/` (2 copias) + backend |

Cada uno es verificable con `git show <hash>`. El commit `4a222e0` se validó
contra el PDF realmente desplegado:

```
SUBTOTAL 951.095 · IVA 95.109 · TOTAL A PAGAR 1.046.204
TOTAL IVA 95.109 · SON: … GUARANÍES 00/100
QR → https://ecoruta-jaxf.onrender.com/api/facturas/30001/kude
```

## 5.7 Despliegue

### Imagen del backend (`Dockerfile` en la raíz)

| Etapa | Qué hace |
|---|---|
| Base | `php:8.2-apache` |
| Sistema | Instala `chromium`, librerías de soporte, Node 22 y extensiones `pdo`, `pdo_mysql`, `zip` |
| Composer | `composer install --no-dev --optimize-autoloader` |
| Código | Copia `backend/` al docroot y `factura-py/` a `/var/www/factura-py` |
| Motor PDF | `npm install` de `factura-py` + `PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium` |
| Arranque | Script que adapta Apache al `$PORT` dinámico de Render |

### Entornos

| Entorno | API | Base de datos | Notas |
|---|---|---|---|
| Local (XAMPP) | `http://127.0.0.1:8000` | MySQL local | `iniciar-ecoruta.bat` levanta API, agente y web |
| Web (Render) | `https://ecoruta-backend-app.onrender.com` | MySQL gestionada | Auto-deploy al hacer push a `main` |
| Panel web | Build de Vite | — | `VITE_API_URL` define la API; en producción cae al host de Render |
| Agente | `http://localhost:18650` | — | Proxy con failover web/local |

### Variables críticas

`DB_*`, `APP_URL`, `FRONTEND_URL`, `JWT_SECRET`, `JWT_EXPIRE`,
`SYNC_API_KEY`, `SYNC_DB_NAME`, `FACTURA_PY_PATH`, `MAIL_*` (SMTP 465/SSL en
Render). `backend/.env.example` documenta el contrato completo.

### Riesgos operativos y mitigación

| Riesgo | Mitigación aplicada |
|---|---|
| Cold start de Render (30–60 s) | Reintento antes de degradar a local; app de escritorio con failover |
| Chromium ausente | Fallback HTML en PHP + mensaje claro en PDF; Chromium del sistema en Docker |
| SMTP bloqueado en el puerto 587 | Se usa 465 con SSL |
| Token vencido con la pestaña abierta | Temporizador sobre `exp` + cierre ante cualquier `401` |
| Copias divergentes de `factura-py` | Se sincronizan y se comparan por hash antes de cada push |

## 5.8 Retrospectiva del ciclo

### Qué funcionó

- Commits atómicos con causa raíz en el mensaje: permitieron reconstruir la
  historia fiscal y de sesión sin ambigüedad.
- Corregir en capas (cálculo → payload → plantilla) evitó parches frágiles.
- Pruebas de QR y offline en la app móvil dieron confianza para el demo.
- El fallback web/local mantiene el sistema usable durante una caída.

### Qué mejorar

| Acción | Responsable | Cuándo |
|---|---|---|
| Usar ramas `feat/*` + PR con el checklist DoD | `[COMPLETAR]` | Próximo sprint |
| Volver la CI bloqueante (`blocking: error`) | `[COMPLETAR]` | Cuando el score esté estable |
| Medir cobertura (Vitest + PHPUnit) | `[COMPLETAR]` | Próximo sprint |
| Normalizar la velocity por duración del sprint | `[COMPLETAR]` | Próxima planificación |
| Agregar pruebas de integración del flujo de facturación | `[COMPLETAR]` | Fase 2 (SIFEN) |

### Acuerdos

1. Ningún push a `main` sin build y pruebas del módulo en verde.
2. Los cambios de dinero/fiscalidad se validan contra el PDF real, no sólo en tests.
3. La documentación se actualiza en el mismo sprint que el código.
4. Los artefactos pesados y secretos quedan fuera del repositorio (`.gitignore`).
