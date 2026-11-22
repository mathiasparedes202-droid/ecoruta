# Documentación del proyecto EcoRuta

Esta carpeta reúne la documentación exigida por la rúbrica, organizada por
sprint y por eje de evaluación. Todo el contenido técnico está tomado del
código del repositorio y del historial real de Git; los datos que sólo puede
aportar el equipo (fechas oficiales de ceremonias, nombres, enlaces a Figma,
capturas) están marcados con **`[COMPLETAR]`** para no inventar evidencia.

## Mapa de la rúbrica

| Rúbrica | Documento | Qué incluye |
|---|---|---|
| I.1 Gestión ágil (Sprint 1–2) | [`01-gestion-agil-sprints-1-2.md`](01-gestion-agil-sprints-1-2.md) | Product Backlog INVEST, prototipado UI/UX, evidencias Scrum |
| I.2 Arquitectura CBSE | [`02-arquitectura-cbse.md`](02-arquitectura-cbse.md) | Componentes, contratos Proporciona/Requiere, composición y middleware |
| I.3 MVP y módulos (Sprint 3) | [`03-mvp-modulos-sprint-3.md`](03-mvp-modulos-sprint-3.md) | Módulo repartidor, trazabilidad QR/firma, métricas ecológicas |
| I.4 Facturación Paraguay (Sprint 4) | [`04-facturacion-sifen-sprint-4.md`](04-facturacion-sifen-sprint-4.md) | Timbrado, IVA, CDC/QR, KuDE/PDF, brecha SIFEN |
| I.5 DevOps y métricas (Sprint 5) | [`05-devops-metricas-sprint-5.md`](05-devops-metricas-sprint-5.md) | Ramas, commits, CI, Velocity/Burndown, DoD |
| II.6–II.10 Demo en vivo | [`06-guia-demo-evaluacion.md`](06-guia-demo-evaluacion.md) | Guion de demo, checklist por eje y preguntas frecuentes |

Planilla de métricas: [`metricas/EcoRuta-Velocity.xlsx`](metricas/EcoRuta-Velocity.xlsx)
(backlog, velocity con `=SUMAR.SI(...)`, burndown y gráfico).

## Leyenda de estado

| Símbolo | Significado |
|---|---|
| ✅ | Implementado en el repositorio y verificable en la demo |
| 🟡 | Parcial: funciona con un alcance acotado o falta una parte |
| 🔴 | Pendiente / fuera del alcance actual (declarado como fase 2) |

## Resumen ejecutivo del sistema

EcoRuta es una plataforma de logística urbana de última milla con tres roles
(Comerciante, Repartidor, Administrador) y seis piezas desplegables:

| Pieza | Tecnología | Ruta |
|---|---|---|
| Panel web | React 18 + Vite + Tailwind | `frontend/` |
| API | PHP 8.2 + PDO/MySQL + JWT | `backend/` |
| App del repartidor | React 18 + TypeScript + Capacitor 6 (Android) | `EcoRuta-app/` |
| Intermediario offline | Node.js (proxy + cola + sincronización) | `sync-agent/` |
| Motor de comprobantes | Node.js + Puppeteer + `qrcode` | `factura-py/` |
| App de escritorio (opcional) | Electron con failover web/local | `desktop-app/` |

Capacidades verificables: autenticación JWT con cierre por vencimiento,
pedidos con tarifa y CO₂ calculados, asignación y seguimiento de estados,
turnos del repartidor, confirmación de entrega por QR o firma, cobro en
efectivo/transferencia/mixto con vuelto, notificaciones internas, panel de
métricas, timbrados con bloqueo automático y factura electrónica pre-fiscal
con IVA 10 %/5 %, KuDE/PDF y QR que reabre el comprobante.

## Alcance fiscal declarado

El módulo de facturación es **pre-fiscal**: controla timbrado, folios, IVA,
numeración y auditoría, y genera un CDC de control interno (HMAC-SHA256) y un
QR verificable. **No** incluye aún firma digital XML ni autorización en línea
del SIFEN; esa brecha se documenta en
[`04-facturacion-sifen-sprint-4.md`](04-facturacion-sifen-sprint-4.md#45-brecha-con-sifen-y-plan-de-fase-2).
