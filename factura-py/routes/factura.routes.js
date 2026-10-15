const express = require("express");

const facturaController = require(
  "../../controllers/factura.controller"
);

const router = express.Router();

/**
 * Obtiene el documento HTML de una factura.
 *
 * GET /api/facturas/:idVenta/html
 */
router.get(
  "/:idVenta/html",
  facturaController.obtenerFacturaHtml
);

/**
 * Genera el PDF de una factura y lo muestra
 * directamente en el navegador.
 *
 * GET /api/facturas/:idVenta/pdf
 */
router.get(
  "/:idVenta/pdf",
  facturaController.generarFacturaPdf
);

/**
 * Genera el PDF de una factura y solicita
 * su descarga desde el navegador.
 *
 * GET /api/facturas/:idVenta/descargar
 */
router.get(
  "/:idVenta/descargar",
  facturaController.descargarFacturaPdf
);

/**
 * Obtiene los datos estructurados de una factura.
 *
 * Esta ruta será utilizada principalmente por React
 * para mostrar la factura dentro de la aplicación.
 *
 * GET /api/facturas/:idVenta
 */
router.get(
  "/:idVenta",
  facturaController.obtenerFactura
);

module.exports = router;
