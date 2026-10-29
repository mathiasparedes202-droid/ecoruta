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

    const html = await facturaTemplate.generarFacturaHtml(
      factura
    );

    const opcionesNavegador = {
      headless: true,
      args: [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        // Chromium hace consultas de fondo (component updates, variation
        // trials, etc.) que pueden colgar la espera de red en servidores con
        // egress lento o restringido: se desactivan para dejarlo mudo.
        "--disable-background-networking",
        "--disable-component-update",
        "--disable-domain-reliability",
        "--disable-sync",
        "--disable-breakpad",
        "--no-first-run",
        "--hide-scrollbars",
        "--mute-audio"
      ]
    };

    // Permite usar Chromium/Chrome instalado por el entorno de despliegue.
    // Si no se define la variable, Puppeteer utiliza su navegador administrado.
    if (process.env.PUPPETEER_EXECUTABLE_PATH) {
      opcionesNavegador.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
    }

    navegador = await puppeteer.launch(opcionesNavegador);

    const pagina = await navegador.newPage();

    // El documento es autocontenido (CSS inline + QR como data URI), así que
    // no se espera por red: networkidle0 se colgaba 30s (TimeoutError) si
    // había algún request pendiente (QR externo de fallback, consultas de
    // fondo de Chromium, red del servidor lenta). domcontentloaded no depende
    // de la red; después se esperan las imágenes con tope propio.
    await pagina.setContent(html, {
      waitUntil: "domcontentloaded",
      timeout: 60000
    });

    // Espera ACOTADA a imágenes (QR) y fuentes: aunque el QR viniera de un
    // servicio externo y la red no responda, el PDF se genera igual en ~4s.
    await pagina.evaluate(async () => {
      const conLimite = (promesa, ms) =>
        Promise.race([
          promesa,
          new Promise((resolver) => setTimeout(resolver, ms))
        ]);

      const imagenes = Array.from(document.images || []);

      await conLimite(
        Promise.all(
          imagenes.map(
            (img) =>
              img.complete
                ? Promise.resolve()
                : new Promise((resolver) => {
                    img.onload = resolver;
                    img.onerror = resolver;
                  })
          )
        ),
        4000
      );

      if (document.fonts && document.fonts.ready) {
        await conLimite(document.fonts.ready, 2000);
      }
    });

    const pdf = await pagina.pdf({
      format: "A4",
      printBackground: true,
      preferCSSPageSize: true,
      timeout: 60000,
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
