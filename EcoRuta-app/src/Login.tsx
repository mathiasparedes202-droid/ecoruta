import React, { useState, useEffect } from 'react';
import { IoEye, IoEyeOff, IoMailOutline, IoLockClosedOutline, IoPersonOutline, IoDocumentTextOutline } from 'react-icons/io5';
import { FaShieldAlt, FaMotorcycle, FaBicycle } from 'react-icons/fa';
import './Login.css';
import { API } from './config';
import { RepartidorUser } from './types/repartidor';

type Props = {
  onLogin?: (user: RepartidorUser) => void;
};

const STORAGE_KEY_USER = "ecoruta_user:v1";
const STORAGE_KEY_EMAIL = "ecoruta_saved_email:v1";

type Vehicle = 'Bicicleta' | 'Vehículo Eléctrico';

const Login: React.FC<Props> = ({ onLogin }) => {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [correo, setCorreo] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const [nombre, setNombre] = useState('');
  const [documento, setDocumento] = useState('');
  const [correoReg, setCorreoReg] = useState('');
  const [passReg, setPassReg] = useState('');
  const [passReg2, setPassReg2] = useState('');
  const [tipoVehiculo, setTipoVehiculo] = useState<Vehicle>('Bicicleta');
  const [matricula, setMatricula] = useState('');
  const [registrando, setRegistrando] = useState(false);
  const [success, setSuccess] = useState('');
  const [showPassReg, setShowPassReg] = useState(false);

  useEffect(() => {
    const savedEmail = localStorage.getItem(STORAGE_KEY_EMAIL);
    if (savedEmail) {
      setCorreo(savedEmail);
    }
  }, []);

  const handleSubmit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setError('');

    if (!correo.trim() || !password) {
      setError('Por favor, completa todos los campos.');
      return;
    }

    setLoading(true);

    try {
      const res = await fetch(`${API}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          correo: correo.trim(),
          contraseña: password
        })
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        throw new Error(data?.message || 'Credenciales inválidas.');
      }
      const userData: RepartidorUser = {
        id: data.user?.id_usuario,
        id_usuario: data.user?.id_usuario,
        id_repartidor: data.user?.id_repartidor ?? null,
        email: data.user?.correo || correo.trim(),
        displayName: data.user?.nombre_completo,
        rol: data.user?.rol || 'Repartidor',
        tipo_vehiculo: data.user?.tipo_vehiculo,
        matricula: data.user?.matricula,
        disponible: Boolean(data.user?.disponible)
      };

      localStorage.setItem(STORAGE_KEY_USER, JSON.stringify(userData));
      if (data.token) {
        localStorage.setItem('ecoruta_token', data.token);
      } else {
        localStorage.removeItem('ecoruta_token');
      }

      if (remember) {
        localStorage.setItem(STORAGE_KEY_EMAIL, correo.trim());
      } else {
        localStorage.removeItem(STORAGE_KEY_EMAIL);
      }

      if (onLogin) {
        onLogin(userData);
      }

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error de conexión con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setError('');
    setSuccess('');

    if (!nombre.trim() || !documento.trim() || !correoReg.trim() || !passReg) {
      setError('Por favor, completa todos los campos.');
      return;
    }
    if (passReg.length < 8) {
      setError('La contraseña debe tener al menos 8 caracteres.');
      return;
    }
    if (passReg !== passReg2) {
      setError('Las contraseñas no coinciden.');
      return;
    }

    setRegistrando(true);

    try {
      const res = await fetch(`${API}/auth/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          id_rol: 2,
          nombre_completo: nombre.trim(),
          documento_identidad: documento.trim(),
          correo: correoReg.trim().toLowerCase(),
          contraseña: passReg,
          tipo_vehiculo: tipoVehiculo,
          matricula: matricula.trim() || null
        })
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        throw new Error(data?.message || 'No se pudo crear la cuenta.');
      }

      setSuccess(data?.message || '¡Cuenta creada! Ya podés iniciar sesión.');
      setCorreo(correoReg.trim().toLowerCase());
      setNombre('');
      setDocumento('');
      setCorreoReg('');
      setPassReg('');
      setPassReg2('');
      setMatricula('');
      setMode('login');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error de conexión con el servidor.');
    } finally {
      setRegistrando(false);
    }
  };

  const switchToRegister = () => {
    setMode('register');
    setError('');
    setSuccess('');
  };

  const switchToLogin = () => {
    setMode('login');
    setError('');
    setSuccess('');
  };

  return (
    <div className="eco-login-viewport">
      <div className="eco-login-card-pro">
        <div className="eco-login-form-panel">
          {mode === 'login' ? (
            <>
              <div className="form-header-area">
                <h2>Acceso Repartidores</h2>
                <p>Ingresá con tu cuenta para gestionar tus entregas.</p>
              </div>

              {success && (
                <div className="alert-banner-success">
                  <span>{success}</span>
                </div>
              )}

              {error && (
                <div className="alert-banner-error">
                  <span>{error}</span>
                </div>
              )}

              <form onSubmit={handleSubmit}>
                <div className="pro-input-group">
                  <label>Correo Electrónico</label>
                  <div className="pro-input-wrapper">
                    <IoMailOutline className="input-icon-lead" />
                    <input
                      type="email"
                      className="pro-input"
                      placeholder="Ingresa tu correo."
                      value={correo}
                      onChange={(e) => setCorreo(e.target.value)}
                      required
                      autoComplete="email"
                    />
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Contraseña</label>
                  <div className="pro-input-wrapper">
                    <IoLockClosedOutline className="input-icon-lead" />
                    <input
                      type={showPassword ? 'text' : 'password'}
                      className="pro-input"
                      placeholder="Ingresa tu contraseña."
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      autoComplete="current-password"
                    />
                    <button
                      type="button"
                      className="btn-toggle-eye"
                      onClick={() => setShowPassword(!showPassword)}
                      aria-label={showPassword ? 'Ocultar contraseña' : 'Ver contraseña'}
                    >
                      {showPassword ? <IoEyeOff /> : <IoEye />}
                    </button>
                  </div>
                </div>

                <label className="remember-label">
                  <input
                    type="checkbox"
                    checked={remember}
                    onChange={(e) => setRemember(e.target.checked)}
                  />
                  <span>Recordar credenciales</span>
                </label>

                <button type="submit" className="btn-submit-pro" disabled={loading}>
                  {loading ? "Verificando..." : "Iniciar Sesión"}
                </button>
              </form>

              <div className="eco-auth-switch">
                ¿Aún no tenés cuenta?{' '}
                <button type="button" onClick={switchToRegister}>Registrate</button>
              </div>
            </>
          ) : (
            <>
              <div className="form-header-area">
                <h2>Únete al equipo</h2>
                <p>Creá tu cuenta de repartidor para empezar a entregar.</p>
              </div>

              {success && (
                <div className="alert-banner-success">
                  <span>{success}</span>
                </div>
              )}

              {error && (
                <div className="alert-banner-error">
                  <span>{error}</span>
                </div>
              )}

              <form onSubmit={handleRegister}>
                <div className="pro-input-group">
                  <label>Nombre completo</label>
                  <div className="pro-input-wrapper">
                    <IoPersonOutline className="input-icon-lead" />
                    <input
                      type="text"
                      className="pro-input"
                      placeholder="Tu nombre y apellido."
                      value={nombre}
                      onChange={(e) => setNombre(e.target.value)}
                      required
                      autoComplete="name"
                    />
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Documento de identidad</label>
                  <div className="pro-input-wrapper">
                    <IoDocumentTextOutline className="input-icon-lead" />
                    <input
                      type="text"
                      className="pro-input"
                      placeholder="Ej: 1234567"
                      value={documento}
                      onChange={(e) => setDocumento(e.target.value)}
                      required
                      autoComplete="off"
                    />
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Correo Electrónico</label>
                  <div className="pro-input-wrapper">
                    <IoMailOutline className="input-icon-lead" />
                    <input
                      type="email"
                      className="pro-input"
                      placeholder="Ingresa tu correo."
                      value={correoReg}
                      onChange={(e) => setCorreoReg(e.target.value)}
                      required
                      autoComplete="email"
                    />
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Tipo de vehículo</label>
                  <div className="pro-vehicle-row">
                    <button
                      type="button"
                      className={`pro-vehicle-pill ${tipoVehiculo === 'Bicicleta' ? 'active' : ''}`}
                      onClick={() => setTipoVehiculo('Bicicleta')}
                    >
                      <FaBicycle />
                      <span>Bicicleta</span>
                    </button>
                    <button
                      type="button"
                      className={`pro-vehicle-pill ${tipoVehiculo === 'Vehículo Eléctrico' ? 'active' : ''}`}
                      onClick={() => setTipoVehiculo('Vehículo Eléctrico')}
                    >
                      <FaMotorcycle />
                      <span>Vehículo Eléctrico</span>
                    </button>
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Matrícula (opcional)</label>
                  <div className="pro-input-wrapper">
                    <IoDocumentTextOutline className="input-icon-lead" />
                    <input
                      type="text"
                      className="pro-input"
                      placeholder="Ej: ABC-123"
                      value={matricula}
                      onChange={(e) => setMatricula(e.target.value)}
                      autoComplete="off"
                    />
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Contraseña</label>
                  <div className="pro-input-wrapper">
                    <IoLockClosedOutline className="input-icon-lead" />
                    <input
                      type={showPassReg ? 'text' : 'password'}
                      className="pro-input"
                      placeholder="Mínimo 8 caracteres."
                      value={passReg}
                      onChange={(e) => setPassReg(e.target.value)}
                      required
                      autoComplete="new-password"
                    />
                    <button
                      type="button"
                      className="btn-toggle-eye"
                      onClick={() => setShowPassReg(!showPassReg)}
                      aria-label={showPassReg ? 'Ocultar contraseña' : 'Ver contraseña'}
                    >
                      {showPassReg ? <IoEyeOff /> : <IoEye />}
                    </button>
                  </div>
                </div>

                <div className="pro-input-group">
                  <label>Confirmar contraseña</label>
                  <div className="pro-input-wrapper">
                    <IoLockClosedOutline className="input-icon-lead" />
                    <input
                      type={showPassReg ? 'text' : 'password'}
                      className="pro-input"
                      placeholder="Repetí tu contraseña."
                      value={passReg2}
                      onChange={(e) => setPassReg2(e.target.value)}
                      required
                      autoComplete="new-password"
                    />
                  </div>
                </div>

                <button type="submit" className="btn-submit-pro" disabled={registrando}>
                  {registrando ? "Creando cuenta..." : "Crear cuenta"}
                </button>
              </form>

              <div className="eco-auth-switch">
                ¿Ya tenés una cuenta?{' '}
                <button type="button" onClick={switchToLogin}>Iniciar sesión</button>
              </div>
            </>
          )}

          <div className="security-seal-footer">
            <FaShieldAlt />
            <span>Tu seguridad es nuestra prioridad</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;