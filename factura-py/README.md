# Generación de facturas (backend)

Este módulo genera la representación HTML y PDF de las ventas registradas por el backend PHP.

## Endpoints autenticados

- `GET /api/ventas/{id}/factura/html`: devuelve HTML listo para visualizar/imprimir.
- `GET /api/ventas/{id}/factura/pdf`: muestra el PDF en el navegador.
- `GET /api/ventas/{id}/factura/descargar`: descarga el PDF.

Los tres endpoints utilizan la venta persistida y sus detalles; no aceptan importes ni datos fiscales enviados por el cliente para construir el comprobante.

## Requisitos

- Node.js compatible con la versión de Puppeteer del `package.json` (Node 22.12 o posterior).
- Dependencias instaladas dentro de `factura-py`.
- Chrome/Chromium disponible. En una instalación nueva, ejecutar `npm install` dentro de `factura-py` y `npx puppeteer browsers install chrome`. En servidores que ya tengan Chromium, configurar `PUPPETEER_EXECUTABLE_PATH` con la ruta del ejecutable.
- PHP debe poder ejecutar `node` y escribir en el directorio temporal del sistema.

## Prueba manual del generador

El backend PHP invoca `generate-factura.js` con un JSON temporal y un archivo de salida temporal. Para probarlo manualmente, preparar un JSON con el modelo de factura normalizado y ejecutar:

```bash
node generate-factura.js --mode html --input /ruta/factura.json --output /tmp/factura.html
node generate-factura.js --mode pdf --input /ruta/factura.json --output /tmp/factura.pdf
```

Los archivos temporales creados por la API se eliminan después de entregar la respuesta.
