import { useEffect, useState } from 'react';
import {
  AlertCircle,
  CheckCircle,
  Loader2,
  UserPlus,
  Users,
  Mail,
  Phone,
  IdCard,
  Lock,
  Unlock,
  RefreshCw
} from 'lucide-react';
import { fetchUsers, createUser, updateUser } from '../services/adminService.js';

const ROLE_LABELS = {
  1: 'Comerciante',
  2: 'Repartidor',
  3: 'Administrador'
};

const emptyForm = {
  nombre_completo: '',
  documento_identidad: '',
  correo: '',
  telefono: '',
  contraseña: '',
  id_rol: '2'
};

function initials(name) {
  return (name || '?').split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}

export default function AdminUsers() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [togglingId, setTogglingId] = useState(null);
  const [message, setMessage] = useState('');
  const [backendError, setBackendError] = useState('');

  async function load() {
    setLoading(true);
    setBackendError('');
    try {
      const data = await fetchUsers();
      const list = Array.isArray(data) ? data : data.users || [];
      setUsers(list);
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No se pudieron cargar los usuarios.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  const set = (key, value) => {
    setForm((f) => ({ ...f, [key]: value }));
    if (errors[key]) setErrors((e) => ({ ...e, [key]: '' }));
  };

  function validate() {
    const e = {};
    if (form.nombre_completo.trim().length < 3) e.nombre_completo = 'Mínimo 3 caracteres';
    if (form.documento_identidad.trim().length < 5) e.documento_identidad = 'Documento inválido';
    if (!/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(form.correo.trim())) e.correo = 'Correo inválido';
    if (form.contraseña.length < 8) e.contraseña = 'Mínimo 8 caracteres';
    if (!ROLE_LABELS[Number(form.id_rol)]) e.id_rol = 'Elegí un rol';
    if (form.telefono.trim() && !/^\+?[0-9\s-]{7,20}$/.test(form.telefono.trim())) e.telefono = 'Teléfono inválido';
    return e;
  }

  async function handleCreate() {
    const e = validate();
    if (Object.keys(e).length > 0) {
      setErrors(e);
      return;
    }
    setSaving(true);
    setBackendError('');
    setMessage('');
    try {
      await createUser({
        nombre_completo: form.nombre_completo.trim(),
        documento_identidad: form.documento_identidad.trim(),
        correo: form.correo.trim(),
        telefono: form.telefono.trim() || undefined,
        contraseña: form.contraseña,
        id_rol: Number(form.id_rol)
      });
      setMessage('¡Usuario creado!');
      setForm(emptyForm);
      setErrors({});
      await load();
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No pudimos crear el usuario. Revisá los datos e intentá de nuevo.');
    } finally {
      setSaving(false);
    }
  }

  async function handleToggleActive(user) {
    setTogglingId(user.id_usuario);
    setBackendError('');
    try {
      await updateUser(user.id_usuario, { activo: !Boolean(Number(user.activo)) });
      setMessage(`Usuario ${user.nombre_completo} ${Number(user.activo) ? 'desactivado' : 'activado'}.`);
      await load();
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No pudimos cambiar el estado del usuario.');
    } finally {
      setTogglingId(null);
    }
  }

  async function handleChangeRole(user, role) {
    if (!ROLE_LABELS[Number(role)]) return;
    setTogglingId(user.id_usuario);
    setBackendError('');
    try {
      await updateUser(user.id_usuario, { id_rol: Number(role) });
      setMessage(`Rol de ${user.nombre_completo} actualizado a ${ROLE_LABELS[Number(role)]}.`);
      await load();
    } catch (err) {
      setBackendError(err instanceof Error ? err.message : 'No pudimos actualizar el rol.');
    } finally {
      setTogglingId(null);
    }
  }

  return (
    <div className="admin-users">
      <div className="panel-title">
        <div>
          <p className="eyebrow">GESTIÓN DE USUARIOS</p>
          <h2>Usuarios del sistema</h2>
          <p>Administra cuentas, roles y permisos de comerciantes, repartidores y administradores.</p>
        </div>
        <button type="button" className="admin-assign__refresh" onClick={load}>
          <RefreshCw size={15} /> Actualizar
        </button>
      </div>

      {backendError && (
        <div className="admin-overview__error">
          <AlertCircle size={18} />
          <span>{backendError}</span>
        </div>
      )}
      {message && (
        <div className="admin-assign__success">
          <CheckCircle size={18} />
          <span>{message}</span>
        </div>
      )}

      <div className="admin-users__layout">
        {/* Formulario de creación */}
        <div className="user-form-wrap">
          <div className="user-form__heading">
            <UserPlus size={18} />
            <h3>Crear usuario</h3>
          </div>
          <form className="user-form" onSubmit={(e) => { e.preventDefault(); handleCreate(); }}>
            <label>
              Nombre completo *
              <input value={form.nombre_completo} onChange={(e) => set('nombre_completo', e.target.value)} placeholder="Nombre y apellido" />
              {errors.nombre_completo && <small>{errors.nombre_completo}</small>}
            </label>
            <label>
              Documento (Cédula / RUC) *
              <input value={form.documento_identidad} onChange={(e) => set('documento_identidad', e.target.value)} placeholder="Ej: 5.123.456" />
              {errors.documento_identidad && <small>{errors.documento_identidad}</small>}
            </label>
            <label>
              Correo electrónico *
              <input value={form.correo} onChange={(e) => set('correo', e.target.value)} placeholder="usuario@ejemplo.com" />
              {errors.correo && <small>{errors.correo}</small>}
            </label>
            <label>
              Teléfono
              <input value={form.telefono} onChange={(e) => set('telefono', e.target.value)} placeholder="+595 981 000000" />
              {errors.telefono && <small>{errors.telefono}</small>}
            </label>
            <label>
              Contraseña inicial *
              <input type="password" value={form.contraseña} onChange={(e) => set('contraseña', e.target.value)} placeholder="Mínimo 8 caracteres" />
              {errors.contraseña && <small>{errors.contraseña}</small>}
              {!errors.contraseña && <small>El usuario deberá cambiarla en su primer inicio de sesión.</small>}
            </label>
            <label>
              Rol *
              <select value={form.id_rol} onChange={(e) => set('id_rol', e.target.value)}>
                {Object.entries(ROLE_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              {errors.id_rol && <small>{errors.id_rol}</small>}
            </label>
            <button type="submit" className="primary-action" disabled={saving}>
              {saving ? <Loader2 size={16} className="new-order-spin" /> : <UserPlus size={16} />}
              {saving ? 'Creando…' : 'Crear usuario'}
            </button>
          </form>
        </div>

        {/* Listado */}
        <div className="users-list">
          <div className="users-list__heading">
            <h3><Users size={16} /> Cuentas del sistema</h3>
            <span>{users.length} usuarios</span>
          </div>

          {loading ? (
            <div className="clientes-list__empty"><Loader2 size={22} className="new-order-spin" /> Cargando usuarios…</div>
          ) : users.length === 0 ? (
            <div className="clientes-list__empty">Sin usuarios registrados.</div>
          ) : (
            <div className="users-list__scroll">
              {users.map((u) => {
                const active = Boolean(Number(u.activo));
                const mustChange = Boolean(Number(u.debe_cambiar_contraseña));
                return (
                  <div className="user-row admin-user-row" key={u.id_usuario}>
                    <span className="user-avatar">{initials(u.nombre_completo)}</span>
                    <div>
                      <strong>{u.nombre_completo}
                        {!active && <em className="user-badge user-badge--inactive">inactivo</em>}
                        {mustChange && <em className="user-badge user-badge--warn">cambiar contraseña</em>}
                      </strong>
                      <span><Mail size={11} /> {u.correo}</span>
                      {u.telefono && <span><Phone size={11} /> {u.telefono}</span>}
                      <span><IdCard size={11} /> {u.documento_identidad}{u.fecha_ultimo_acceso ? ` · último acceso: ${u.fecha_ultimo_acceso}` : ''}</span>
                    </div>
                    <div className="admin-user-row__actions">
                      <select
                        aria-label={`Rol de ${u.nombre_completo}`}
                        value={String(u.id_rol)}
                        disabled={togglingId === u.id_usuario}
                        onChange={(e) => handleChangeRole(u, e.target.value)}
                      >
                        {Object.entries(ROLE_LABELS).map(([value, label]) => (
                          <option key={value} value={value}>{label}</option>
                        ))}
                      </select>
                      <button
                        type="button"
                        className={active ? 'user-toggle user-toggle--off' : 'user-toggle user-toggle--on'}
                        disabled={togglingId === u.id_usuario}
                        onClick={() => handleToggleActive(u)}
                      >
                        {togglingId === u.id_usuario ? (
                          <Loader2 size={13} className="new-order-spin" />
                        ) : active ? (
                          <><Lock size={13} /> Desactivar</>
                        ) : (
                          <><Unlock size={13} /> Activar</>
                        )}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}