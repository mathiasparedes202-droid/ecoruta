const puppeteer = require("puppeteer");

const facturaTemplate = require(
  "./factura.template"
);

/**
 * Genera un archivo PDF a partir de los datos normalizados
 * de una factura.
 *
 * El servicio reutiliza la plantilla HTML definida en
 * factura.template.js para evitar duplicar el diseño.
 *
 * @param {Object} factura - Datos normalizados de la factura.
 * @returns {Promise<Buffer>} Archivo PDF generado como Buffer.
 */
const generarPdf = async (factura) => {
  let navegador;

  try {
    if (!factura) {
      const error = new Error(
        "No se proporcionaron los datos de la factura."
      );

      error.statusCode = 400;

      throw error;
    }

    const html = facturaTemplate.generarFacturaHtml(
      factura
    );

    const opcionesNavegador = {
      headless: true,
      args: [
        "--no-sandbox",
        "--disable-setuid-sandbox"
      ]
    };

    // Permite usar Chromium/Chrome instalado por el entorno de despliegue.
    // Si no se define la variable, Puppeteer utiliza su navegador administrado.
    if (process.env.PUPPETEER_EXECUTABLE_PATH) {
      opcionesNavegador.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
    }

    navegador = await puppeteer.launch(opcionesNavegador);

    const pagina = await navegador.newPage();

    await pagina.setContent(html, {
      waitUntil: "networkidle0"
    });

    const pdf = await pagina.pdf({
      format: "A4",
      printBackground: true,
      preferCSSPageSize: true,
      margin: {
        top: "8mm",
        right: "8mm",
        bottom: "8mm",
        left: "8mm"
      }
    });

    return Buffer.from(pdf);
  } catch (error) {
    if (!error.statusCode) {
      error.statusCode = 500;
    }

    throw error;
  } finally {
    if (navegador) {
      await navegador.close();
    }
  }
};

module.exports = {
  generarPdf
};
