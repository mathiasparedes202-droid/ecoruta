import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-control-geocoder/dist/Control.Geocoder.css';
import {
  UserRound,
  Phone,
  MapPin,
  Pencil,
  Trash2,
  Plus,
  CheckCircle,
  AlertCircle,
  Loader2,
  PackageX,
  X,
  Download,
  FileUp,
  Search
} from 'lucide-react';
import {
  createCliente,
  deleteCliente,
  fetchClientes,
  importClientes,
  updateCliente
} from '../services/clientesService.js';
import { downloadClientesCsvTemplate, parseClientesCsv } from '../lib/clientesCsv.js';
import { PARAGUAY_BOUNDS, PARAGUAY_CENTER, inParaguay, boundedNominatim } from '../lib/geografia.js';
import { confirmAction, toastSuccess, toastError } from '../lib/feedback.js';

const MARKER = L.divIcon({
  className: 'cliente-marker',
  html: '<div class="cliente-marker-pin"></div>',
  iconSize: [22, 22],
  iconAnchor: [11, 11]
});

const emptyForm = {
  nombre: '',
  ruc_ci: '',
  telefono: '',
  direccion: '',
  referencia: ''
};

export default function ClientesPage({ user }) {
  const [clientes, setClientes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [coords, setCoords] = useState(null);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState('');
  const [backendError, setBackendError] = useState('');
  const [importOpen, setImportOpen] = useState(false);
  const [importRows, setImportRows] = useState([]);
  const [importErrors, setImportErrors] = useState([]);
  const [importMessage, setImportMessage] = useState('');
  const [importing, setImporting] = useState(false);
  const [search, setSearch] = useState('');
  const importFileRef = useRef(null);

  const mapRef = useRef(null);
  const mapContainerRef = useRef(null);
  const markerRef = useRef(null);
  const pickRef = useRef(null);

  const clientesFiltrados = clientes.filter((cliente) => (
    [cliente.nombre, cliente.ruc_ci, cliente.telefono, cliente.direccion]
      .some((value) => String(value || '').toLocaleLowerCase().includes(search.trim().toLocaleLowerCase()))
  ));

  async function load() {
    setLoading(true);
    try {
      const data = await fetchClientes();
      const list = Array.isArray(data) ? data : data.clientes || [];
      setClientes(list);
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No se pudieron cargar los clientes.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  useEffect(() => {
    const container = mapContainerRef.current;
    if (!container) return;

    const map = L.map(container, { maxBounds: PARAGUAY_BOUNDS, maxBoundsViscosity: 1 }).setView(PARAGUAY_CENTER, 7);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    try {
      map.addControl(L.Control.geocoder({
        geocoder: boundedNominatim(),
        defaultMarkGeocode: false,
        collapsed: false
      }));
    } catch (err) {
      console.warn('No se pudo inicializar el buscador', err);
    }

    const onPick = (lat, lng) => {
      setCoords({ lat, lng });
      if (!markerRef.current) {
        markerRef.current = L.marker([lat, lng], { icon: MARKER }).addTo(map);
      } else {
        markerRef.current.setLatLng([lat, lng]);
      }
      map.setView([lat, lng], 15);
      setErrors((e) => ({ ...e, location: '' }));
      setNotice('');
    };
    pickRef.current = onPick;
    map.on('click', (event) => {
      const { lat, lng } = event.latlng;
      if (inParaguay(lat, lng)) {
        onPick(lat, Math.max(-62.7, Math.min(-54.2, lng)));
      } else {
        setErrors((e) => ({ ...e, location: 'El punto está fuera de Paraguay. EcoRuta solo opera dentro del país.' }));
      }
    });

    mapRef.current = map;

    return () => {
      map.remove();
      mapRef.current = null;
      markerRef.current = null;
    };
  }, []);

  const set = (key, value) => {
    setForm((f) => ({ ...f, [key]: value }));
    if (errors[key]) setErrors((e) => ({ ...e, [key]: '' }));
  };

  function resetForm() {
    setForm(emptyForm);
    setEditingId(null);
    setCoords(null);
    setErrors({});
    setNotice('');
    if (markerRef.current) {
      markerRef.current.remove();
      markerRef.current = null;
    }
    const map = mapRef.current;
    if (map) map.setView(PARAGUAY_CENTER, 7);
  }

  function startEdit(cliente) {
    setForm({
      nombre: cliente.nombre || '',
      ruc_ci: cliente.ruc_ci || '',
      telefono: cliente.telefono || '',
      direccion: cliente.direccion || '',
      referencia: cliente.referencia || ''
    });
    setEditingId(cliente.id_cliente);
    setCoords({ lat: Number(cliente.lat), lng: Number(cliente.lng) });
    setErrors({});
    setNotice('');
    if (markerRef.current) {
      markerRef.current.setLatLng([Number(cliente.lat), Number(cliente.lng)]);
    } else if (mapRef.current) {
      markerRef.current = L.marker([Number(cliente.lat), Number(cliente.lng)], { icon: MARKER }).addTo(mapRef.current);
    }
    mapRef.current?.setView([Number(cliente.lat), Number(cliente.lng)], 14);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function validate() {
    const e = {};
    if (form.nombre.trim().length < 2) e.nombre = 'El nombre debe tener al menos 2 caracteres';
    if (form.ruc_ci.trim() && !/^(?:\d{5,8}|\d{8}-\d)$/.test(form.ruc_ci.trim())) {
      e.ruc_ci = 'Ingresá entre 5 y 8 dígitos o un RUC como 80012345-6';
    }
    if (!form.direccion.trim()) e.direccion = 'La dirección es obligatoria';
    if (form.telefono.trim() && !/^\+?[0-9\s-]{7,20}$/.test(form.telefono.trim())) e.telefono = 'Teléfono inválido';
    if (!coords) e.location = 'Marca la ubicación exacta del cliente en el mapa';
    return e;
  }

  async function handleSave() {
    const e = validate();
    if (Object.keys(e).length > 0) {
      setErrors(e);
      return;
    }
    setSaving(true);
    setBackendError('');
    setNotice('');
    try {
      const payload = {
        nombre: form.nombre.trim(),
        ruc_ci: form.ruc_ci.trim(),
        telefono: form.telefono.trim() || undefined,
        direccion: form.direccion.trim(),
        referencia: form.referencia.trim() || undefined,
        lat: Number(coords.lat),
        lng: Number(coords.lng)
      };
      if (editingId) {
        await updateCliente(editingId, payload);
        setNotice('¡Cliente actualizado!');
      } else {
        await createCliente(payload);
        setNotice('Cliente registrado con su ubicación exacta.');
      }
      await load();
      resetForm();
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No pudimos guardar el cliente. Volvé a intentarlo.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(cliente) {
    const confirmed = await confirmAction(
      '¿Eliminar a ' + (cliente.nombre || 'este cliente') + '?',
      'Los pedidos asignados conservarán la dirección.',
      'Sí, eliminar'
    );
    if (!confirmed) return;
    setBackendError('');
    try {
      await deleteCliente(cliente.id_cliente);
      setNotice('Cliente eliminado.');
      if (editingId === cliente.id_cliente) resetForm();
      await load();
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No pudimos eliminar el cliente. Volvé a intentarlo.');
    }
  }

  async function handleImportFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    setImportRows([]);
    setImportErrors([]);
    setImportMessage('');
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.csv')) {
      setImportMessage('Seleccioná un archivo con formato .csv.');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      setImportMessage('El archivo no puede superar los 2 MB.');
      return;
    }

    try {
      const rows = parseClientesCsv(await file.text());
      setImportRows(rows);
      setImportErrors(rows
        .filter((row) => row.errors.length > 0)
        .map((row) => ({ line: row.line, errors: row.errors })));
    } catch (err) {
      setImportMessage(err instanceof Error ? err.message : 'No se pudo leer el archivo CSV.');
    }
  }

  async function handleImport() {
    const invalidRows = importRows.filter((row) => row.errors.length > 0);
    if (invalidRows.length > 0) {
      setImportErrors(invalidRows.map((row) => ({ line: row.line, errors: row.errors })));
      return;
    }

    setImporting(true);
    setImportMessage('');
    setImportErrors([]);
    try {
      const result = await importClientes(importRows.map(({ errors, ...cliente }) => cliente));
      setNotice(result.message || `Se importaron ${result.importados} clientes.`);
      setImportRows([]);
      setImportOpen(false);
      await load();
    } catch (err) {
      setImportMessage(err instanceof Error ? err.message : 'No se pudieron importar los clientes.');
      setImportErrors(err.errors || []);
    } finally {
      setImporting(false);
    }
  }

  return (
    <div className="clientes-page">
      <div className="clientes-header">
        <div>
          <p className="eyebrow">DIRECTORIO DE CLIENTES</p>
          <h1>Mis clientes</h1>
          <p className="clientes-header__sub">
            Registrá a tus clientes con su ubicación exacta. Los pedidos se envían siempre dentro de Paraguay.
          </p>
        </div>
        {!editingId && (
          <button type="button" className="primary-action clientes-header__btn" onClick={resetForm}>
            <Plus size={16} /> Nuevo cliente
          </button>
        )}
      </div>

      {backendError && (
        <div className="admin-overview__error">
          <AlertCircle size={18} />
          <span>{backendError}</span>
        </div>
      )}
      {notice && (
        <div className="admin-assign__success">
          <CheckCircle size={18} />
          <span>{notice}</span>
        </div>
      )}

      <div className="clientes-layout">
        {/* Formulario + mapa */}
        <section className="clientes-form">
          <div className="clientes-form__title">
            <div className="clientes-form__icon"><UserRound size={17} /></div>
            <div>
              <h2>{editingId ? 'Editar cliente' : 'Registrar cliente'}</h2>
              <span>{editingId ? 'Actualizá los datos y la ubicación exacta.' : 'Completá los datos y marcá el punto en el mapa.'}</span>
            </div>
          </div>

          <label className="new-order-field">
            <span>Nombre completo <b className="new-order-required">*</b></span>
            <input className="new-order-input" value={form.nombre} onChange={(e) => set('nombre', e.target.value)} placeholder="Ej: María Rodríguez" />
            {errors.nombre && <small className="new-order-error">{errors.nombre}</small>}
          </label>

          <label className="new-order-field">
            <span>RUC / C.I.</span>
            <input className="new-order-input" value={form.ruc_ci} onChange={(e) => set('ruc_ci', e.target.value)} placeholder="Ej: 1234567 o 80012345-6" maxLength={20} />
            {errors.ruc_ci && <small className="new-order-error">{errors.ruc_ci}</small>}
          </label>

          <label className="new-order-field">
            <span>Teléfono</span>
            <input className="new-order-input" value={form.telefono} onChange={(e) => set('telefono', e.target.value)} placeholder="Ej: +595 981 123456" />
            {errors.telefono && <small className="new-order-error">{errors.telefono}</small>}
          </label>

          <label className="new-order-field">
            <span>Dirección <b className="new-order-required">*</b></span>
            <input className="new-order-input" value={form.direccion} onChange={(e) => set('direccion', e.target.value)} placeholder="Calle, número, barrio, ciudad" />
            {errors.direccion && <small className="new-order-error">{errors.direccion}</small>}
          </label>

          <div className="new-order-map-box">
            <p className="commerce-map-hint">Buscá la dirección (solo Paraguay) o hacé clic en el mapa para fijar la ubicación exacta.</p>
            <div ref={mapContainerRef} style={{ height: '290px', width: '100%', borderRadius: '8px', zIndex: 0 }}></div>
          </div>

          {errors.location && <small className="new-order-error">{errors.location}</small>}

          {coords ? (
            <div className="new-order-info">
              <MapPin size={14} />
              <span>Ubicación exacta: {coords.lat.toFixed(5)}, {coords.lng.toFixed(5)} {inParaguay(coords.lat, coords.lng) ? '· Paraguay' : '· FUERA DE PARAGUAY'}</span>
            </div>
          ) : (
            <div className="new-order-info">
              <MapPin size={14} />
              <span>Ubicación no definida todavía.</span>
            </div>
          )}

          <label className="new-order-field">
            <span>Referencia del lugar</span>
            <input className="new-order-input" value={form.referencia} onChange={(e) => set('referencia', e.target.value)} placeholder="Ej: casa verde, frente a la estación de servicio" />
          </label>

          <div className="clientes-form__actions">
            {editingId && (
              <button type="button" className="new-order-cancel" onClick={resetForm}>
                <X size={15} /> Cancelar
              </button>
            )}
            <button
              type="button"
              className="primary-action clientes-form__save"
              onClick={handleSave}
              disabled={saving}
            >
              {saving ? <Loader2 size={16} className="new-order-spin" /> : <CheckCircle size={16} />}
              {saving ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Registrar cliente'}
            </button>
          </div>
        </section>

        {/* Listado */}
        <section className="clientes-list">
          <div className="clientes-list__head">
            <div className="clientes-list__toolbar">
              <h2>Clientes registrados <span>({clientes.length})</span></h2>
              <button
                type="button"
                className="clientes-import__toggle"
                onClick={() => {
                  setImportOpen((open) => !open);
                  setImportMessage('');
                  setImportErrors([]);
                }}
              >
                <FileUp size={15} /> Importar CSV
              </button>
            </div>
          </div>

          {importOpen && (
            <div className="clientes-import">
              <div className="clientes-import__heading">
                <div>
                  <strong>Importá varios clientes</strong>
                  <p>Completá la plantilla CSV. RUC / C.I. es opcional; cada fila necesita nombre, dirección y coordenadas dentro de Paraguay.</p>
                </div>
                <button type="button" className="clientes-import__template" onClick={downloadClientesCsvTemplate}>
                  <Download size={15} /> Descargar plantilla
                </button>
              </div>
              <label className="clientes-import__file">
                <span>Archivo CSV (máximo 500 clientes, 2 MB)</span>
                <input ref={importFileRef} type="file" accept=".csv,text/csv" onChange={handleImportFile} disabled={importing} />
              </label>
              {importMessage && <p className="clientes-import__message" role="alert">{importMessage}</p>}
              {importRows.length > 0 && (
                <>
                  <p className="clientes-import__summary">
                    {importRows.length} clientes detectados · {importErrors.length === 0 ? 'listos para importar' : `${importErrors.length} filas con errores`}
                  </p>
                  {importErrors.length > 0 && (
                    <ul className="clientes-import__errors">
                      {importErrors.slice(0, 10).map((error) => (
                        <li key={error.line}>Fila {error.line}: {error.errors.join(', ')}</li>
                      ))}
                      {importErrors.length > 10 && <li>Y {importErrors.length - 10} filas más con errores.</li>}
                    </ul>
                  )}
                  <button
                    type="button"
                    className="primary-action clientes-import__submit"
                    onClick={handleImport}
                    disabled={importing || importErrors.length > 0}
                  >
                    {importing ? <Loader2 size={15} className="new-order-spin" /> : <FileUp size={15} />}
                    {importing ? 'Importando…' : `Importar ${importRows.length} clientes`}
                  </button>
                </>
              )}
            </div>
          )}

          {!loading && clientes.length > 0 && (
            <label className="clientes-search">
              <Search size={16} aria-hidden="true" />
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por nombre, teléfono o dirección"
                aria-label="Buscar clientes"
              />
            </label>
          )}

          {loading ? (
            <div className="clientes-list__empty"><Loader2 size={22} className="new-order-spin" /> Cargando…</div>
          ) : clientes.length === 0 ? (
            <div className="clientes-list__empty">
              <PackageX size={22} />
              <p>Sin clientes todavía. Registrá el primero con su ubicación exacta en el mapa.</p>
            </div>
          ) : clientesFiltrados.length === 0 ? (
            <div className="clientes-list__empty">
              <Search size={22} />
              <p>No hay clientes que coincidan con “{search}”.</p>
            </div>
          ) : (
            <div className="clientes-grid">
              {clientesFiltrados.map((cliente) => (
                <article className="cliente-card" key={cliente.id_cliente}>
                  <div className="cliente-card__head">
                    <div className="cliente-card__avatar"><UserRound size={16} /></div>
                    <div>
                      <strong>{cliente.nombre}</strong>
                      <span className="cliente-card__comercio">{cliente.razon_social || `Cliente #${cliente.id_cliente}`}</span>
                    </div>
                  </div>

                  {cliente.telefono && (
                    <p className="cliente-card__row"><Phone size={13} /> <span>{cliente.telefono}</span></p>
                  )}
                  {cliente.ruc_ci && (
                    <p className="cliente-card__row"><UserRound size={13} /> <span>RUC / C.I.: {cliente.ruc_ci}</span></p>
                  )}
                  <p className="cliente-card__row"><MapPin size={13} /> <span>{cliente.direccion}{cliente.referencia ? ` · ${cliente.referencia}` : ''}</span></p>
                  <p className="cliente-card__row cliente-card__coords">
                    <MapPin size={13} />
                    <span>{Number(cliente.lat).toFixed(5)}, {Number(cliente.lng).toFixed(5)} · Paraguay</span>
                  </p>

                  <div className="cliente-card__stats">
                    <span><b>{cliente.en_curso}</b> en curso</span>
                    <span><b>{cliente.entregas}</b> entregas</span>
                  </div>

                  <div className="cliente-card__actions">
                    <button type="button" onClick={() => startEdit(cliente)}><Pencil size={14} /> Editar</button>
                    <button type="button" className="cliente-card__delete" onClick={() => handleDelete(cliente)}><Trash2 size={14} /> Eliminar</button>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}