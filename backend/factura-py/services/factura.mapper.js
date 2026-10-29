/**
 * Datos temporales del emisor.
 *
 * Más adelante estos datos deberían obtenerse desde una tabla
 * de configuración de empresa o desde un servicio específico.
 */
const DATOS_EMISOR = {
  razonSocial: "Floracia",
  ruc: "",
  direccion: "",
  telefono: "",
  email: "",
  timbrado: "",
  vigenciaTimbrado: "",
  establecimiento: "001",
  puntoExpedicion: "001"
};

/**
 * Convierte un valor a número.
 *
 * Sequelize puede devolver los campos DECIMAL como cadenas,
 * por lo que se normalizan antes de enviarlos al frontend
 * o utilizarlos en la plantilla.
 *
 * @param {number|string|null|undefined} valor - Valor a convertir.
 * @returns {number} Valor numérico.
 */
const convertirANumero = (valor) => {
  const numero = Number(valor);

  return Number.isFinite(numero)
    ? numero
    : 0;
};

/**
 * Convierte una fecha a una cadena con formato ISO.
 *
 * @param {Date|string|null|undefined} fecha - Fecha a formatear.
 * @returns {string|null} Fecha en formato ISO o null.
 */
const formatearFecha = (fecha) => {
  if (!fecha) {
    return null;
  }

  const fechaConvertida = new Date(fecha);

  if (Number.isNaN(fechaConvertida.getTime())) {
    return null;
  }

  return fechaConvertida.toISOString();
};

/**
 * Obtiene el valor de venta correspondiente al tipo de IVA.
 *
 * La factura utiliza tres columnas:
 *
 * - Exentas.
 * - IVA 5 %.
 * - IVA 10 %.
 *
 * @param {Object} detalle - Detalle de venta.
 * @returns {Object} Valores separados por tipo de IVA.
 */
const obtenerValoresPorIva = (detalle) => {
  const subtotal = convertirANumero(
    detalle.subtotal
  );

  const tipoIva = convertirANumero(
    detalle.iva_tipo
  );

  // El precio YA trae el IVA adentro: la columna gravada de la factura es
  // el bruto menos su IVA (extracción con / 11 para 10% y / 21 para 5%),
  // para que gravado + IVA = total de la factura.
  const iva = tipoIva === 10
    ? subtotal / 11
    : tipoIva === 5
      ? subtotal / 21
      : 0;

  return {
    exenta: tipoIva === 0
      ? subtotal
      : 0,

    iva5: tipoIva === 5
      ? subtotal - iva
      : 0,

    iva10: tipoIva === 10
      ? subtotal - iva
      : 0
  };
};

/**
 * Convierte un detalle de venta al formato utilizado
 * por la factura.
 *
 * @param {Object} detalle - Detalle de venta de Sequelize.
 * @returns {Object} Detalle normalizado.
 */
const mapearDetalle = (detalle) => {
  const valoresIva = obtenerValoresPorIva(
    detalle
  );

  return {
    idDetalleVenta: detalle.id_detalle_venta,

    idProducto: detalle.id_producto,

    cantidad: convertirANumero(
      detalle.cantidad
    ),

    descripcion:
      detalle.descripcion_producto ||
      detalle.producto?.nombre_producto ||
      "Producto sin descripción",

    precioUnitario: convertirANumero(
      detalle.precio_unitario
    ),

    tipoIva: convertirANumero(
      detalle.iva_tipo
    ),

    exenta: valoresIva.exenta,

    iva5: valoresIva.iva5,

    iva10: valoresIva.iva10,

    subtotal: convertirANumero(
      detalle.subtotal
    )
  };
};

/**
 * Convierte una venta completa en un modelo estándar
 * de factura.
 *
 * El resultado no depende de Sequelize y puede utilizarse
 * desde React, una plantilla HTML o un generador de PDF.
 *
 * @param {Object} venta - Venta obtenida con sus relaciones.
 * @returns {Object} Modelo normalizado de factura.
 */
const mapearVentaAFactura = (venta) => {
  const detalles = Array.isArray(
    venta.detalles
  )
    ? venta.detalles.map(mapearDetalle)
    : [];

  return {
    idVenta: venta.id_venta,

    emisor: {
      ...DATOS_EMISOR,

      // El timbrado de la venta es el dato histórico
      // que debe aparecer en la factura.
      timbrado: venta.timbrado
    },

    factura: {
      numeroFactura: venta.numero_factura,

      numeroComprobante:
        venta.numero_comprobante || null,

      tipoComprobante:
        venta.tipo_comprobante,

      timbrado: venta.timbrado,

      fechaEmision: formatearFecha(
        venta.fecha_emision
      ),

      estado:
        venta.estado_factura,

      condicionVenta:
        venta.condicion_venta,

      plazo:
        venta.plazo || null,

      observaciones:
        venta.observaciones || null
    },

    cliente: {
      idCliente:
        venta.cliente?.id_cliente || null,

      razonSocial:
        venta.cliente?.razon_social ||
        "Consumidor final",

      rucCi:
        venta.cliente?.ruc_ci ||
        "Sin documento",

      celular:
        venta.cliente?.celular || null
    },

    detalles,

    resumen: {
      subtotalExenta: convertirANumero(
        venta.subtotal_iva_exenta
      ),

      subtotalIva5: convertirANumero(
        venta.subtotal_iva_5
      ),

      subtotalIva10: convertirANumero(
        venta.subtotal_iva_10
      ),

      iva5: convertirANumero(
        venta.iva_5
      ),

      iva10: convertirANumero(
        venta.iva_10
      ),

      totalIva: convertirANumero(
        venta.total_iva
      ),

      liquidacionIva5: convertirANumero(
        venta.liquidacion_iva_5
      ),

      liquidacionIva10: convertirANumero(
        venta.liquidacion_iva_10
      ),

      totalLiquidacion: convertirANumero(
        venta.total_liquidacion
      ),

      totalFactura: convertirANumero(
        venta.total_factura
      )
    }
  };
};

module.exports = {
  mapearVentaAFactura
};