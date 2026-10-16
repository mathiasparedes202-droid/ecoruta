/**
 * Escapa caracteres HTML para evitar que los datos almacenados
 * en la base de datos alteren la estructura del documento.
 *
 * @param {unknown} valor - Valor que será mostrado en el HTML.
 * @returns {string} Valor seguro para insertar en HTML.
 */
const escaparHtml = (valor) => {
  if (valor === null || valor === undefined) {
    return "";
  }

  return String(valor)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
};

/**
 * Formatea un monto en guaraníes.
 *
 * @param {number|string|null|undefined} monto - Monto a formatear.
 * @returns {string} Monto con separador de miles.
 */
const formatearMoneda = (monto) => {
  const valor = Number(monto) || 0;

  return new Intl.NumberFormat("es-PY", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(valor);
};

/**
 * Formatea una fecha para mostrarla en la factura.
 *
 * @param {string|Date|null|undefined} fecha - Fecha a formatear.
 * @returns {string} Fecha en formato local.
 */
const formatearFecha = (fecha) => {
  if (!fecha) {
    return "";
  }

  const fechaConvertida = new Date(fecha);

  if (Number.isNaN(fechaConvertida.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("es-PY", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  }).format(fechaConvertida);
};

/**
 * Convierte un entero (0..999.999.999) a letras en español mayúsculas.
 */
const numeroALetras = (n) => {
  n = Math.round(Number(n) || 0);
  if (n < 0 || n > 999999999) return String(n);
  if (n === 0) return "CERO";
  const U = ["", "UN", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE", "DIEZ", "ONCE", "DOCE", "TRECE", "CATORCE", "QUINCE", "DIECISÉIS", "DIECISIETE", "DIECIOCHO", "DIECINUEVE", "VEINTE", "VEINTIÚN", "VEINTIDÓS", "VEINTITRÉS", "VEINTICUATRO", "VEINTICINCO", "VEINTISÉIS", "VEINTISIETE", "VEINTIOCHO", "VEINTINUEVE"];
  const D = ["", "", "", "TREINTA", "CUARENTA", "CINCUENTA", "SESENTA", "SETENTA", "OCHENTA", "NOVENTA"];
  const C = ["", "CIENTO", "DOSCIENTOS", "TRESCIENTOS", "CUATROCIENTOS", "QUINIENTOS", "SEISCIENTOS", "SETECIENTOS", "OCHOCIENTOS", "NOVECIENTOS"];
  const t = (x) => {
    if (x < 30) return U[x];
    if (x < 100) { const d = Math.floor(x / 10), r = x % 10; return r === 0 ? D[d] : `${D[d]} Y ${U[r]}`; }
    if (x === 100) return "CIEN";
    const c = Math.floor(x / 100), r = x % 100;
    return r === 0 ? C[c] : `${C[c]} ${t(r)}`;
  };
  const m = (x) => {
    if (x < 1000) return t(x);
    const miles = Math.floor(x / 1000), r = x % 1000;
    const head = miles === 1 ? "MIL" : `${t(miles)} MIL`;
    return r === 0 ? head : `${head} ${t(r)}`;
  };
  if (n < 1000000) return m(n);
  const mill = Math.floor(n / 1000000), r = n % 1000000;
  const head = mill === 1 ? "UN MILLÓN" : `${m(mill)} MILLONES`;
  return r === 0 ? head : `${head} ${m(r)}`;
};

/**
 * Monto en letras estilo comprobante paraguayo: ".... GUARANÍES NN/100".
 */
const montoEnLetras = (total) => {
  const v = Number(total) || 0;
  const entero = Math.floor(v);
  const dec = Math.round((v - entero) * 100);
  return `${numeroALetras(entero)} GUARANÍES ${String(dec).padStart(2, "0")}/100`;
};

/**
 * Fecha y hora de emisión.
 */
const formatearFechaHora = (fecha) => {
  if (!fecha) return "";
  const f = new Date(fecha);
  if (Number.isNaN(f.getTime())) return "";
  const d = new Intl.DateTimeFormat("es-PY", { day: "2-digit", month: "2-digit", year: "numeric" }).format(f);
  const h = new Intl.DateTimeFormat("es-PY", { hour: "2-digit", minute: "2-digit", hour12: false }).format(f);
  return `${d} ${h}`;
};

/**
 * Genera las filas de la tabla de detalles.
 *
 * @param {Array<Object>} detalles - Detalles normalizados.
 * @returns {string} Filas HTML.
 */
const generarFilasDetalles = (detalles = []) => {
  if (!Array.isArray(detalles) || detalles.length === 0) {
    return `
      <tr>
        <td colspan="7" class="sin-detalles">
          No existen productos registrados.
        </td>
      </tr>
    `;
  }

  return detalles
    .map((detalle) => {
      return `
        <tr>
          <td class="centrado">
            ${escaparHtml(detalle.cantidad)}
          </td>

          <td class="centrado">
            ${escaparHtml(
              detalle.unidadMedida || "Servicio"
            )}
          </td>

          <td class="descripcion">
            ${escaparHtml(detalle.descripcion)}
          </td>

          <td class="numero">
            ${formatearMoneda(
              detalle.precioUnitario
            )}
          </td>

          <td class="numero">
            ${
              detalle.exenta > 0
                ? formatearMoneda(detalle.exenta)
                : ""
            }
          </td>

          <td class="numero">
            ${
              detalle.iva5 > 0
                ? formatearMoneda(detalle.iva5)
                : ""
            }
          </td>

          <td class="numero">
            ${
              detalle.iva10 > 0
                ? formatearMoneda(detalle.iva10)
                : ""
            }
          </td>
        </tr>
      `;
    })
    .join("");
};

/**
 * Genera el documento HTML completo de una factura.
 *
 * La función recibe un objeto normalizado generado por
 * factura.mapper.js y no depende directamente de Sequelize.
 *
 * @param {Object} factura - Datos normalizados de la factura.
 * @returns {string} Documento HTML completo.
 */
const generarFacturaHtml = (factura) => {
  const {
    emisor = {},
    factura: datosFactura = {},
    cliente = {},
    detalles = [],
    resumen = {}
  } = factura;

  const esContado =
    datosFactura.condicionVenta === "Contado";

  const esCredito =
    datosFactura.condicionVenta === "Crédito";

  const filasDetalles = generarFilasDetalles(
    detalles
  );

  return `
    <!DOCTYPE html>

    <html lang="es">
      <head>
        <meta charset="UTF-8">

        <meta
          name="viewport"
          content="width=device-width, initial-scale=1.0"
        >

        <title>
          Factura ${escaparHtml(
            datosFactura.numeroFactura
          )}
        </title>

        <style>
          * {
            box-sizing: border-box;
          }

          @page {
            size: A4 portrait;
            margin: 8mm;
          }

          body {
            margin: 0;
            padding: 0;
            color: #000;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
          }

          .factura {
            width: 100%;
            min-height: 280mm;
            border: 1.5px solid #000;
            padding: 8px;
          }

          .cinta-titulo {
            background: #000;
            color: #fff;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 2px;
            padding: 5px;
            margin-bottom: 8px;
          }

          .monto-letras {
            border: 1px solid #000;
            border-top: 0;
            padding: 6px 8px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
          }

          .verificacion {
            margin-top: 10px;
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 8px;
            line-height: 1.5;
            word-break: break-all;
          }

          .verificacion strong {
            text-transform: uppercase;
          }

          .encabezado {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            border-bottom: 1px solid #000;
          }

          .emisor {
            min-height: 115px;
            padding: 8px;
            border-right: 1px solid #000;
          }

          .emisor-superior {
            display: flex;
            align-items: flex-start;
            gap: 10px;
          }

          .logo {
            width: 58px;
            height: 58px;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            text-align: center;
          }

          .datos-emisor {
            flex: 1;
          }

          .razon-social {
            margin: 0 0 5px;
            font-size: 17px;
            font-weight: 700;
            text-transform: uppercase;
          }

          .texto-emisor {
            margin: 2px 0;
            line-height: 1.3;
          }

          .datos-factura {
            min-height: 115px;
            padding: 8px;
          }

          .tipo-documento {
            margin: 0;
            text-align: center;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 1px;
          }

          .numero-factura {
            margin: 8px 0;
            padding: 5px;
            border: 1px solid #000;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
          }

          .timbrado {
            margin: 3px 0;
            text-align: center;
            font-weight: 700;
          }

          .vigencia {
            margin: 3px 0;
            text-align: center;
          }

          .informacion {
            border-bottom: 1px solid #000;
          }

          .fila-informacion {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 29px;
            border-bottom: 1px solid #000;
          }

          .fila-informacion:last-child {
            border-bottom: 0;
          }

          .campo {
            padding: 6px;
          }

          .campo:first-child {
            border-right: 1px solid #000;
          }

          .etiqueta {
            font-weight: 700;
            text-transform: uppercase;
          }

          .casilla {
            display: inline-block;
            width: 11px;
            height: 11px;
            margin: 0 4px;
            border: 1px solid #000;
            vertical-align: -1px;
            text-align: center;
            line-height: 9px;
            font-size: 9px;
          }

          .tabla-detalles {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
          }

          .tabla-detalles th,
          .tabla-detalles td {
            border: 1px solid #000;
          }

          .tabla-detalles thead th {
            padding: 5px 3px;
            text-align: center;
            font-size: 9px;
          }

          .tabla-detalles tbody td {
            height: 25px;
            padding: 4px;
            vertical-align: top;
          }

          .tabla-detalles .cantidad {
            width: 8%;
          }

          .tabla-detalles .unidad {
            width: 10%;
          }

          .tabla-detalles .descripcion-columna {
            width: 40%;
          }

          .tabla-detalles .precio {
            width: 14%;
          }

          .tabla-detalles .impuesto {
            width: 12.66%;
          }

          .centrado {
            text-align: center;
          }

          .numero {
            text-align: right;
            white-space: nowrap;
          }

          .descripcion {
            text-transform: uppercase;
          }

          .sin-detalles {
            height: 180px !important;
            text-align: center;
            vertical-align: middle !important;
          }

          .fila-subtotal td {
            height: 27px !important;
            font-weight: 700;
            vertical-align: middle !important;
          }

          .etiqueta-subtotal {
            text-align: right;
            padding-right: 8px !important;
          }

          .total-pagar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #000;
            border-top: 0;
          }

          .total-titulo {
            padding: 8px;
            border-right: 1px solid #000;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
          }

          .total-monto {
            padding: 8px;
            text-align: right;
            font-size: 14px;
            font-weight: 700;
          }

          .liquidacion {
            display: grid;
            grid-template-columns:
              1.5fr
              1fr
              1fr
              1fr;
            border: 1px solid #000;
            border-top: 0;
          }

          .liquidacion > div {
            min-height: 31px;
            padding: 7px 4px;
            border-right: 1px solid #000;
            text-align: center;
          }

          .liquidacion > div:last-child {
            border-right: 0;
          }

          .liquidacion-titulo {
            font-weight: 700;
            text-transform: uppercase;
          }

          .pie {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 18px;
            gap: 25px;
          }

          .observaciones {
            min-height: 55px;
            padding: 6px;
            border: 1px solid #000;
          }

          .firma {
            display: flex;
            min-height: 55px;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 6px;
            border: 1px solid #000;
            text-align: center;
          }

          .estado-anulada {
            margin-bottom: 8px;
            padding: 5px;
            border: 2px solid #000;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
          }

          @media print {
            body {
              -webkit-print-color-adjust: exact;
              print-color-adjust: exact;
            }

            .factura {
              min-height: auto;
            }
          }
        </style>
      </head>

      <body>
        <main class="factura">

          <div class="cinta-titulo">
            FACTURA ELECTRÓNICA · KuDE — REPRESENTACIÓN IMPRESA
          </div>

          ${
            datosFactura.estado === "Anulada"
              ? `
                <div class="estado-anulada">
                  FACTURA ANULADA
                </div>
              `
              : ""
          }

          <header class="encabezado">

            <section class="emisor">
              <div class="emisor-superior">

                <div class="logo">
                  LOGO
                </div>

                <div class="datos-emisor">

                  <h1 class="razon-social">
                    ${escaparHtml(
                      emisor.razonSocial
                    )}
                  </h1>

                  <p class="texto-emisor">
                    <strong>RUC:</strong>
                    ${escaparHtml(emisor.ruc)}
                  </p>

                  <p class="texto-emisor">
                    ${escaparHtml(
                      emisor.direccion
                    )}
                  </p>

                  <p class="texto-emisor">
                    ${escaparHtml(
                      emisor.telefono
                    )}
                  </p>

                  <p class="texto-emisor">
                    ${escaparHtml(
                      emisor.email
                    )}
                  </p>

                </div>

              </div>
            </section>

            <section class="datos-factura">

              <h2 class="tipo-documento">
                ${escaparHtml(
                  datosFactura.tipoComprobante ||
                  "FACTURA"
                )}
              </h2>

              <div class="numero-factura">
                ${escaparHtml(
                  datosFactura.numeroFactura
                )}
              </div>

              <p class="timbrado">
                TIMBRADO N.º
                ${escaparHtml(
                  datosFactura.timbrado
                )}
              </p>

              <p class="vigencia">
                Válido hasta:
                ${escaparHtml(
                  emisor.vigenciaTimbrado
                )}
              </p>

              <p class="timbrado">
                CDC CONTROL
                ${escaparHtml(
                  (factura.cdc || "").slice(0, 16)
                )}
              </p>

            </section>

          </header>

          <section class="informacion">

            <div class="fila-informacion">

              <div class="campo">
                <span class="etiqueta">
                  Fecha de emisión:
                </span>

                ${formatearFechaHora(
                  datosFactura.fechaEmision
                )}
              </div>

              <div class="campo">
                <span class="etiqueta">
                  Condición de venta:
                </span>

                CONTADO

                <span class="casilla">
                  ${esContado ? "X" : ""}
                </span>

                CRÉDITO

                <span class="casilla">
                  ${esCredito ? "X" : ""}
                </span>
              </div>

            </div>

            <div class="fila-informacion">

              <div class="campo">
                <span class="etiqueta">
                  Nombre o razón social:
                </span>

                ${escaparHtml(
                  cliente.razonSocial
                )}
              </div>

              <div class="campo">
                <span class="etiqueta">
                  RUC / C.I.:
                </span>

                ${escaparHtml(
                  cliente.rucCi
                )}
              </div>

            </div>

            <div class="fila-informacion">

              <div class="campo">
                <span class="etiqueta">
                  Teléfono:
                </span>

                ${escaparHtml(
                  cliente.celular
                )}
              </div>

              <div class="campo">
                <span class="etiqueta">
                  Plazo:
                </span>

                ${
                  datosFactura.plazo
                    ? `${escaparHtml(
                      datosFactura.plazo
                    )} días`
                    : ""
                }
              </div>

            </div>

            <div class="fila-informacion">

              <div class="campo">
                <span class="etiqueta">
                  Dirección:
                </span>

                ${escaparHtml(
                  cliente.direccion || ""
                )}
              </div>

              <div class="campo">
                <span class="etiqueta">
                  Método de pago:
                </span>

                ${escaparHtml(
                  datosFactura.metodoPago || ""
                )}
              </div>

            </div>

          </section>

          <table class="tabla-detalles">

            <thead>
              <tr>

                <th
                  rowspan="2"
                  class="cantidad"
                >
                  CANTIDAD
                </th>

                <th
                  rowspan="2"
                  class="unidad"
                >
                  UNIDAD
                </th>

                <th
                  rowspan="2"
                  class="descripcion-columna"
                >
                  DESCRIPCIÓN
                </th>

                <th
                  rowspan="2"
                  class="precio"
                >
                  PRECIO UNITARIO
                </th>

                <th
                  colspan="3"
                >
                  VALOR DE VENTA
                </th>

              </tr>

              <tr>

                <th class="impuesto">
                  EXENTAS
                </th>

                <th class="impuesto">
                  5 %
                </th>

                <th class="impuesto">
                  10 %
                </th>

              </tr>
            </thead>

            <tbody>

              ${filasDetalles}

              <tr class="fila-subtotal">

                <td
                  colspan="4"
                  class="etiqueta-subtotal"
                >
                  SUBTOTALES
                </td>

                <td class="numero">
                  ${formatearMoneda(
                    resumen.subtotalExenta
                  )}
                </td>

                <td class="numero">
                  ${formatearMoneda(
                    resumen.subtotalIva5
                  )}
                </td>

                <td class="numero">
                  ${formatearMoneda(
                    resumen.subtotalIva10
                  )}
                </td>

              </tr>

            </tbody>

          </table>

          <section class="total-pagar">

            <div class="total-titulo">
              SUBTOTAL
            </div>

            <div class="total-monto">
              Gs.
              ${formatearMoneda(
                resumen.totalFactura
              )}
            </div>

          </section>

          <section class="total-pagar">

            <div class="total-titulo">
              DESCUENTO
            </div>

            <div class="total-monto">
              Gs.
              ${formatearMoneda(
                resumen.descuento || 0
              )}
            </div>

          </section>

          <section class="total-pagar">

            <div class="total-titulo">
              IVA 10 %
            </div>

            <div class="total-monto">
              Gs.
              ${formatearMoneda(
                resumen.iva10
              )}
            </div>

          </section>

          <section class="total-pagar">

            <div class="total-titulo">
              TOTAL A PAGAR
            </div>

            <div class="total-monto">
              Gs.
              ${formatearMoneda(
                resumen.totalFactura
              )}
            </div>

          </section>

          <div class="monto-letras">
            SON: ${escaparHtml(
              montoEnLetras(resumen.totalFactura)
            )}
            ${datosFactura.metodoPago ? ` · MÉTODO DE PAGO: ${escaparHtml(datosFactura.metodoPago).toUpperCase()}` : ""}
          </div>

          <section class="liquidacion">

            <div class="liquidacion-titulo">
              LIQUIDACIÓN DEL IVA
            </div>

            <div>
              IVA 5 %<br>

              ${formatearMoneda(
                resumen.liquidacionIva5
              )}
            </div>

            <div>
              IVA 10 %<br>

              ${formatearMoneda(
                resumen.liquidacionIva10
              )}
            </div>

            <div>
              TOTAL IVA<br>

              ${formatearMoneda(
                resumen.totalLiquidacion
              )}
            </div>

          </section>

          <footer class="pie">

            <div class="observaciones">

              <strong>
                OBSERVACIONES:
              </strong>

              <br>

              ${escaparHtml(
                datosFactura.observaciones
              )}

            </div>

            <div class="firma">

              FIRMA DEL CLIENTE

            </div>

          </footer>

          <div class="verificacion">
            <strong>Sello digital (control interno):</strong>
            ${escaparHtml(factura.cdc || "—")}
            <br>
            <strong>Código de verificación:</strong>
            ${escaparHtml(factura.qrTexto || "—")}
            <br>
            <strong>Timbrado vigente hasta:</strong>
            ${escaparHtml(emisor.vigenciaTimbrado || "—")}
            — Esta es una representación impresa del documento tributario electrónico.
          </div>

        </main>
      </body>
    </html>
  `;
};

module.exports = {
  generarFacturaHtml
};
