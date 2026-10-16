const facturaService = require("../../services/factura.service");
const facturaPdfService = require("../../services/factura-pdf.service");

/**
 * Obtiene los datos estructurados de una factura a partir del ID de la venta.
 *
 * Esta respuesta será utilizada principalmente por el frontend de React
 * para mostrar la factura dentro de la aplicación.
 *
 * @param {import("express").Request} req - Solicitud HTTP.
 * @param {import("express").Response} res - Respuesta HTTP.
 * @param {import("express").NextFunction} next - Función para continuar
 * con el manejo de errores.
 * @returns {Promise<void>}
 */
const obtenerFactura = async (req, res, next) => {
  try {
    const idVenta = Number(req.params.idVenta);

    if (!Number.isInteger(idVenta) || idVenta <= 0) {
      return res.status(400).json({
        success: false,
        message: "El ID de la venta debe ser un número entero válido."
      });
    }

    const factura = await facturaService.obtenerFactura(idVenta);

    return res.status(200).json({
      success: true,
      message: "Factura obtenida correctamente.",
      data: factura
    });
  } catch (error) {
    return next(error);
  }
};

/**
 * Obtiene el documento HTML de una factura.
 *
 * Este endpoint puede utilizarse para abrir una vista de impresión
 * directamente desde el navegador.
 *
 * @param {import("express").Request} req - Solicitud HTTP.
 * @param {import("express").Response} res - Respuesta HTTP.
 * @param {import("express").NextFunction} next - Función para continuar
 * con el manejo de errores.
 * @returns {Promise<void>}
 */
const obtenerFacturaHtml = async (req, res, next) => {
  try {
    const idVenta = Number(req.params.idVenta);

    if (!Number.isInteger(idVenta) || idVenta <= 0) {
      return res.status(400).json({
        success: false,
        message: "El ID de la venta debe ser un número entero válido."
      });
    }

    const html = await facturaService.generarFacturaHtml(idVenta);

    res.setHeader("Content-Type", "text/html; charset=utf-8");

    return res.status(200).send(html);
  } catch (error) {
    return next(error);
  }
};

/**
 * Genera el archivo PDF de una factura.
 *
 * La factura se genera utilizando los datos históricos de la venta
 * y puede visualizarse o descargarse desde el navegador.
 *
 * @param {import("express").Request} req - Solicitud HTTP.
 * @param {import("express").Response} res - Respuesta HTTP.
 * @param {import("express").NextFunction} next - Función para continuar
 * con el manejo de errores.
 * @returns {Promise<void>}
 */
const generarFacturaPdf = async (req, res, next) => {
  try {
    const idVenta = Number(req.params.idVenta);

    if (!Number.isInteger(idVenta) || idVenta <= 0) {
      return res.status(400).json({
        success: false,
        message: "El ID de la venta debe ser un número entero válido."
      });
    }

    const factura = await facturaService.obtenerFactura(idVenta);

    const pdf = await facturaPdfService.generarPdf(factura);

    const nombreArchivo =
      `factura-${factura.numeroFactura}.pdf`;

    res.setHeader(
      "Content-Type",
      "application/pdf"
    );

    res.setHeader(
      "Content-Disposition",
      `inline; filename="${nombreArchivo}"`
    );

    return res.status(200).send(pdf);
  } catch (error) {
    return next(error);
  }
};

/**
 * Descarga el archivo PDF de una factura.
 *
 * A diferencia del endpoint de visualización, este endpoint indica
 * al navegador que debe descargar el documento.
 *
 * @param {import("express").Request} req - Solicitud HTTP.
 * @param {import("express").Response} res - Respuesta HTTP.
 * @param {import("express").NextFunction} next - Función para continuar
 * con el manejo de errores.
 * @returns {Promise<void>}
 */
const descargarFacturaPdf = async (req, res, next) => {
  try {
    const idVenta = Number(req.params.idVenta);

    if (!Number.isInteger(idVenta) || idVenta <= 0) {
      return res.status(400).json({
        success: false,
        message: "El ID de la venta debe ser un número entero válido."
      });
    }

    const factura = await facturaService.obtenerFactura(idVenta);

    const pdf = await facturaPdfService.generarPdf(factura);

    const nombreArchivo =
      `factura-${factura.numeroFactura}.pdf`;

    res.setHeader(
      "Content-Type",
      "application/pdf"
    );

    res.setHeader(
      "Content-Disposition",
      `attachment; filename="${nombreArchivo}"`
    );

    return res.status(200).send(pdf);
  } catch (error) {
    return next(error);
  }
};

module.exports = {
  obtenerFactura,
  obtenerFacturaHtml,
  generarFacturaPdf,
  descargarFacturaPdf
};