const {
  Venta,
  Cliente,
  DetalleVenta,
  Producto
} = require("../../models");

const facturaMapper = require("./factura.mapper");
const facturaTemplate = require("./factura.template");

/**
 * Busca una venta junto con toda la información necesaria
 * para construir una factura.
 *
 * @param {number} idVenta - ID de la venta.
 * @returns {Promise<Object>} Venta con cliente y detalles.
 * @throws {Error} Si la venta no existe.
 */
const obtenerVentaCompleta = async (idVenta) => {
  const venta = await Venta.findByPk(idVenta, {
    include: [
      {
        model: Cliente,
        as: "cliente",
        required: true
      },
      {
        model: DetalleVenta,
        as: "detalles",
        required: false,
        include: [
          {
            model: Producto,
            as: "producto",
            required: true
          }
        ]
      }
    ]
  });

  if (!venta) {
    const error = new Error(
      "No se encontró una venta con el ID proporcionado."
    );

    error.statusCode = 404;

    throw error;
  }

  return venta;
};

/**
 * Valida que una venta pueda utilizarse para emitir
 * o visualizar una factura.
 *
 * @param {Object} venta - Venta obtenida desde la base de datos.
 * @throws {Error} Si la factura está anulada o no tiene detalles.
 */
const validarVentaParaFactura = (venta) => {
  if (venta.estado_factura === "Anulada") {
    const error = new Error(
      "No se puede generar una factura que se encuentra anulada."
    );

    error.statusCode = 409;

    throw error;
  }

  if (
    !Array.isArray(venta.detalles) ||
    venta.detalles.length === 0
  ) {
    const error = new Error(
      "La venta no contiene detalles para generar la factura."
    );

    error.statusCode = 422;

    throw error;
  }

  if (!venta.numero_factura) {
    const error = new Error(
      "La venta no tiene un número de factura asignado."
    );

    error.statusCode = 422;

    throw error;
  }

  if (!venta.timbrado) {
    const error = new Error(
      "La venta no tiene un número de timbrado asignado."
    );

    error.statusCode = 422;

    throw error;
  }
};

/**
 * Obtiene los datos normalizados de una factura.
 *
 * Este método es reutilizable y puede ser utilizado por:
 *
 * - El frontend de React.
 * - La vista de impresión.
 * - El generador de PDF.
 * - La reimpresión de una factura.
 *
 * @param {number} idVenta - ID de la venta.
 * @returns {Promise<Object>} Datos normalizados de la factura.
 */
const obtenerFactura = async (idVenta) => {
  const venta = await obtenerVentaCompleta(idVenta);

  validarVentaParaFactura(venta);

  const factura = facturaMapper.mapearVentaAFactura(
    venta
  );

  return factura;
};

/**
 * Genera el documento HTML completo de una factura.
 *
 * La plantilla recibe un objeto normalizado, por lo que no
 * depende directamente de los modelos de Sequelize.
 *
 * @param {number} idVenta - ID de la venta.
 * @returns {Promise<string>} Documento HTML de la factura.
 */
const generarFacturaHtml = async (idVenta) => {
  const factura = await obtenerFactura(idVenta);

  return await facturaTemplate.generarFacturaHtml(
    factura
  );
};

module.exports = {
  obtenerFactura,
  generarFacturaHtml
};