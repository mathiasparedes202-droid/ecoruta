#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const template = require('./services/factura.template');
const pdfService = require('./services/factura-pdf.service');

function argsOf(argv) {
  const args = {};
  for (let i = 2; i < argv.length; i++) {
    if (argv[i].startsWith('--')) {
      args[argv[i].slice(2)] = argv[i + 1];
      i++;
    }
  }
  return args;
}

async function main() {
  const args = argsOf(process.argv);
  if (!['html', 'pdf'].includes(args.mode) || !args.input || !args.output) {
    throw new Error('Argumentos inválidos. Se requiere --mode html|pdf --input archivo.json --output archivo.');
  }
  const factura = JSON.parse(fs.readFileSync(args.input, 'utf8'));
  if (!factura || typeof factura !== 'object' || !factura.factura || !Array.isArray(factura.detalles)) {
    throw new Error('El contenido JSON no tiene la estructura de factura esperada.');
  }
  const output = path.resolve(args.output);
  if (args.mode === 'html') {
    fs.writeFileSync(output, template.generarFacturaHtml(factura), 'utf8');
  } else {
    fs.writeFileSync(output, await pdfService.generarPdf(factura));
  }
}
main().catch((error) => {
  console.error(error && error.stack ? error.stack : String(error));
  process.exitCode = 1;
});
