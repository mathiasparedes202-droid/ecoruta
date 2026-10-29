const REQUIRED_COLUMNS = ['nombre', 'direccion', 'lat', 'lng'];
const OPTIONAL_COLUMNS = ['telefono', 'referencia'];
const MAX_CLIENTES_IMPORT = 500;

function normalizeHeader(value) {
  return value
    .replace(/^\uFEFF/, '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function parseRows(text, delimiter) {
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;

  for (let index = 0; index < text.length; index += 1) {
    const character = text[index];

    if (quoted) {
      if (character === '"' && text[index + 1] === '"') {
        field += '"';
        index += 1;
      } else if (character === '"') {
        quoted = false;
      } else {
        field += character;
      }
      continue;
    }

    if (character === '"' && field === '') {
      quoted = true;
    } else if (character === delimiter) {
      row.push(field);
      field = '';
    } else if (character === '\n' || character === '\r') {
      if (character === '\r' && text[index + 1] === '\n') index += 1;
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
    } else {
      field += character;
    }
  }

  if (quoted) {
    throw new Error('El CSV tiene comillas sin cerrar.');
  }

  if (field !== '' || row.length > 0) {
    row.push(field);
    rows.push(row);
  }

  return rows;
}

function detectDelimiter(headerLine) {
  let commas = 0;
  let semicolons = 0;
  let quoted = false;

  for (let index = 0; index < headerLine.length; index += 1) {
    const character = headerLine[index];
    if (character === '"') {
      if (quoted && headerLine[index + 1] === '"') index += 1;
      else quoted = !quoted;
    } else if (!quoted && character === ',') {
      commas += 1;
    } else if (!quoted && character === ';') {
      semicolons += 1;
    }
  }

  return semicolons > commas ? ';' : ',';
}

export function parseClientesCsv(text) {
  const firstLine = text.split(/\r?\n/, 1)[0] || '';
  const rows = parseRows(text, detectDelimiter(firstLine));
  const headers = (rows.shift() || []).map(normalizeHeader);
  const missingColumns = REQUIRED_COLUMNS.filter((column) => !headers.includes(column));

  if (missingColumns.length > 0) {
    throw new Error(`Faltan columnas obligatorias: ${missingColumns.join(', ')}.`);
  }

  const columnIndex = Object.fromEntries(
    [...REQUIRED_COLUMNS, ...OPTIONAL_COLUMNS]
      .map((column) => [column, headers.indexOf(column)])
  );
  const clientes = [];

  rows.forEach((values, index) => {
    if (values.every((value) => !value.trim())) return;

    const line = index + 2;
    const cliente = Object.fromEntries(
      Object.entries(columnIndex).map(([column, position]) => [
        column,
        position < 0 ? '' : values[position]?.trim() || ''
      ])
    );
    const errors = [];
    if (cliente.nombre.length < 2) errors.push('nombre: mínimo 2 caracteres');
    if (!cliente.direccion) errors.push('dirección obligatoria');
    if (cliente.telefono && !/^\+?[0-9\s-]{7,20}$/.test(cliente.telefono)) {
      errors.push('teléfono inválido');
    }
    if (
      !cliente.lat || !cliente.lng ||
      !Number.isFinite(Number(cliente.lat)) || !Number.isFinite(Number(cliente.lng))
    ) {
      errors.push('latitud y longitud deben ser números');
    } else if (
      Number(cliente.lat) < -27.6 || Number(cliente.lat) > -19.2 ||
      Number(cliente.lng) < -62.7 || Number(cliente.lng) > -54.2
    ) {
      errors.push('coordenadas fuera de Paraguay');
    }

    clientes.push({ ...cliente, line, errors });
  });

  if (clientes.length === 0) {
    throw new Error('El archivo no contiene clientes para importar.');
  }
  if (clientes.length > MAX_CLIENTES_IMPORT) {
    throw new Error(`Podés importar hasta ${MAX_CLIENTES_IMPORT} clientes por archivo.`);
  }

  return clientes;
}

export function downloadClientesCsvTemplate() {
  const content = '\uFEFFnombre;telefono;direccion;referencia;lat;lng\r\n';
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'plantilla-clientes-ecoruta.csv';
  link.click();
  URL.revokeObjectURL(url);
}
