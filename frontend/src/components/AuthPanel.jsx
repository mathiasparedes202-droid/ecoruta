import { useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import { Logo } from '../components/Logo';
import { Button } from '../components/Button';
import { Input, PasswordInput, Checkbox } from '../components/Input';
import { requestRecovery, resetAccount } from '../services/accountService.js';
import { API_BASE } from '../services/env';
import './login.css'

const initialForm = {
  nombre: '',
  apellido: '',
  documento_identidad: '',
  correo: '',
  contraseña: '',
  id_rol: ''
};

export default function AuthPanel({
  onClose,
  onAuthenticated,
  standalone = false,
  allowRegistration = true,
  allowRecovery = true,
  recoveryToken = '',
  registrationRoles = ['1', '2']
}) {
  const [mode, setMode] = useState(
    recoveryToken ? 'reset' : 'login'
  );

  const [form, setForm] = useState({
    ...initialForm,
    id_rol: registrationRoles.length === 1 ? registrationRoles[0] : ''
  });
  const [remember, setRemember] = useState(false);
  const [terms, setTerms] = useState(false);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [sentRecovery, setSentRecovery] = useState(false);

  const [resetForm, setResetForm] = useState({
    token: recoveryToken,
    nueva_contraseña: '',
    confirmar_contraseña: ''
  });

  function changeMode(newMode) {
    setMode(newMode);
    setMessage('');
    setSentRecovery(false);
  }

  function updateField(event) {
    const { name, value } = event.target;

    setForm((current) => ({
      ...current,
      [name]: value
    }));
  }

  async function handleLogin(event) {
    event.preventDefault();

    setLoading(true);
    setMessage('');

    try {
      const response = await fetch(`${API_BASE}/api/auth/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          correo: form.correo,
          contraseña: form.contraseña
        })
      });

      const result = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(
          result.message || 'Correo o contraseña incorrectos.'
        );
      }

      if (!result.user || !result.token) {
        throw new Error(
          'La API no devolvió una sesión válida. Intentá nuevamente.'
        );
      }

      // Guardar usuario
      localStorage.setItem(
        'ecoruta_user',
        JSON.stringify(result.user)
      );

      localStorage.setItem(
        'ecoruta_token',
        result.token
      );

      // Avisar a App.jsx / LoginPage.jsx
      onAuthenticated(result.user);
      onClose();

    } catch (error) {
      setMessage(
        error.message || 'No se pudo iniciar sesión.'
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleRegister(event) {
    event.preventDefault();

    // Validaciones del frontend
    if (!form.id_rol) {
      setMessage('Debes seleccionar un tipo de cuenta.');
      return;
    }

    if (!form.nombre.trim() || !form.apellido.trim()) {
      setMessage('Nombre y apellido son obligatorios.');
      return;
    }

    if (!form.documento_identidad.trim()) {
      setMessage('El documento de identidad es obligatorio.');
      return;
    }

    if (!form.correo.trim()) {
      setMessage('El correo electrónico es obligatorio.');
      return;
    }

    if (form.contraseña.length < 8) {
      setMessage('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (!terms) {
      setMessage('Debes aceptar los términos de servicio.');
      return;
    }

    setLoading(true);
    setMessage('');

    try {
      const response = await fetch(`${API_BASE}/api/auth/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          nombre_completo: `${form.nombre.trim()} ${form.apellido.trim()}`,
          documento_identidad: form.documento_identidad.trim(),
          correo: form.correo.trim().toLowerCase(),
          contraseña: form.contraseña,
          id_rol: Number(form.id_rol)
        })
      });

      const result = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(
          result.message || 'No se pudo crear la cuenta.'
        );
      }

      setMessage(
        result.message ||
        'Cuenta creada correctamente. Ahora puedes iniciar sesión.'
      );

      // Limpiar formulario
      setForm(initialForm);
      setTerms(false);

      // Después de unos segundos volver al login
      setTimeout(() => {
        changeMode('login');
      }, 1500);

    } catch (error) {
      setMessage(
        error.message || 'No se pudo crear la cuenta.'
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleRecovery(event) {
    event.preventDefault();

    if (!form.correo.trim()) {
      setMessage('Escribe tu correo para recuperar la cuenta.');
      return;
    }

    setLoading(true);
    setMessage('');

    try {
      await requestRecovery(form.correo.trim());
      setSentRecovery(true);
    } catch (error) {
      setMessage(
        error.message ||
        'No se pudo procesar la solicitud.'
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleReset(event) {
    event.preventDefault();

    if (
      resetForm.nueva_contraseña !==
      resetForm.confirmar_contraseña
    ) {
      setMessage('Las contraseñas no coinciden.');
      return;
    }

    setLoading(true);
    setMessage('');

    try {
      const result = await resetAccount({
        token: resetForm.token.trim(),
        nueva_contraseña: resetForm.nueva_contraseña
      });

      setMessage(
        result.message ||
        'Contraseña restablecida correctamente.'
      );

      setTimeout(() => {
        changeMode('login');
      }, 1500);

    } catch (error) {
      setMessage(
        error.message ||
        'No se pudo restablecer la contraseña.'
      );
    } finally {
      setLoading(false);
    }
  }

  const roles = [
    {
      id: '1',
      label: 'Comerciante',
      desc: 'Gestiona tus pedidos y entregas',
      icon: (
        <svg
          width="20"
          height="20"
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          aria-hidden="true"
        >
          <path
            d="M4 2h12l2 5H2L4 2z"
            strokeLinejoin="round"
          />
          <path
            d="M2 7v11h16V7"
            strokeLinecap="round"
          />
          <path d="M8 18V13h4v5" />
        </svg>
      )
    },
    {
      id: '2',
      label: 'Repartidor',
      desc: 'Acepta y completa entregas',
      icon: (
        <svg
          width="20"
          height="20"
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          aria-hidden="true"
        >
          <circle cx="5" cy="16" r="2.5" />
          <circle cx="15" cy="16" r="2.5" />
          <path
            d="M1 8l3.5-5H12l3 5"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          <path
            d="M1 8h17M12 3l2 5"
            strokeLinecap="round"
          />
        </svg>
      )
    }
  ];

  const visibleRoles = roles.filter((role) => registrationRoles.includes(role.id));

  return (
    <div
      className={`flex h-full min-h-screen bg-surface ${
        standalone ? '' : 'auth-modal'
      }`}
    >

      {/* ============================================
          PANEL IZQUIERDO - BRANDING
      ============================================ */}

      <div
        className="
          hidden lg:flex
          w-[42%]
          flex-shrink-0
          flex-col
          relative
          overflow-hidden
        "
        style={{
          background:
            'linear-gradient(145deg, #052e16 0%, #14532d 35%, #166534 65%, #15803d 100%)'
        }}
      >

        {/* Decorative circles */}

        <div className="absolute -top-20 -left-20 w-80 h-80 rounded-full bg-white/[0.03]" />

        <div className="absolute top-1/3 -right-16 w-64 h-64 rounded-full bg-white/[0.04]" />

        <div className="absolute -bottom-20 -left-10 w-72 h-72 rounded-full bg-white/[0.03]" />


        {/* Mesh overlay */}

        <div
          className="absolute inset-0 opacity-10"
          style={{
            backgroundImage: `
              radial-gradient(
                circle at 25% 25%,
                #4ade80 0%,
                transparent 50%
              ),
              radial-gradient(
                circle at 75% 75%,
                #22c55e 0%,
                transparent 50%
              )
            `
          }}
        />


        <div className="relative flex flex-col h-full p-10">

          {/* Logo */}

          <Logo theme="light" size="md" />


          {/* Main Content */}

          <div className="flex-1 flex flex-col justify-center gap-8 mt-8">

            <div>

              <span
                className="
                  text-er-400
                  text-xs
                  font-bold
                  uppercase
                  tracking-[0.14em]
                  block
                  mb-4
                "
                style={{
                  fontFamily: 'Inter'
                }}
              >
                Plataforma Logística Verde
              </span>


              <h1
                className="
                  text-4xl
                  font-extrabold
                  text-white
                  leading-tight
                "
                style={{
                  fontFamily: 'Plus Jakarta Sans',
                  letterSpacing: '-0.02em'
                }}
              >
                Entregas
                <br />

                inteligentes.
                <br />

                <span className="text-er-400">
                  Ciudades más
                </span>

                <br />

                <span className="text-er-400">
                  verdes.
                </span>

              </h1>


              <p
                className="
                  text-white/55
                  text-sm
                  leading-relaxed
                  mt-5
                  max-w-xs
                "
                style={{
                  fontFamily: 'Inter'
                }}
              >
                Gestiona tu operación de última milla
                con bicicletas y vehículos eléctricos.
                Menos emisiones, más eficiencia.
              </p>

            </div>


            {/* Stats */}

            <div
              className="
                grid
                grid-cols-3
                gap-4
                pt-6
                border-t
                border-white/10
              "
            >

              {[
                {
                  value: '284 kg',
                  label: 'CO₂ ahorrado'
                },
                {
                  value: '1,480',
                  label: 'Entregas verdes'
                },
                {
                  value: '98.2%',
                  label: 'Tasa de éxito'
                }
              ].map((stat) => (

                <div
                  key={stat.label}
                  className="flex flex-col gap-1"
                >

                  <span
                    className="
                      text-2xl
                      font-bold
                      text-white
                      tabular-nums
                    "
                  >
                    {stat.value}
                  </span>

                  <span
                    className="
                      text-[11px]
                      text-white/40
                      font-medium
                      uppercase
                    "
                  >
                    {stat.label}
                  </span>

                </div>

              ))}

            </div>


            {/* Route Illustration */}

            <RouteIllustration />

          </div>


          {/* Footer */}

          <div className="pt-8 border-t border-white/10">

            <p className="text-white/25 text-xs">
              © 2026 EcoRuta · Todos los derechos reservados
            </p>

          </div>

        </div>

      </div>


      {/* ============================================
          PANEL DERECHO
      ============================================ */}

      <div className="flex-1 flex flex-col overflow-y-auto relative">

        {!standalone && (
          <button
            type="button"
            onClick={onClose}
            className="
              absolute
              top-5
              right-5
              w-9
              h-9
              rounded-lg
              border
              border-border
              hover:bg-surface
              transition-colors
              z-10
            "
            aria-label="Cerrar"
          >
            ×
          </button>
        )}


        {/* Mobile Logo */}

        <div
          className="
            flex
            lg:hidden
            items-center
            justify-center
            pt-8
            pb-4
          "
        >
          <Logo size="md" />
        </div>


        {/* Form Container */}

        <div
          className="
            flex-1
            flex
            items-center
            justify-center
            px-6
            py-8
          "
        >

          <div className="w-full max-w-[440px]">


            {/* ======================================
                LOGIN
            ====================================== */}

            {mode === 'login' && (

              <form
                onSubmit={handleLogin}
                className="flex flex-col gap-5"
              >

                <div className="mb-2">

                  <span className="
                    text-xs
                    font-bold
                    uppercase
                    tracking-[0.12em]
                    text-er-600
                    block
                    mb-2
                  ">
                    Bienvenido de vuelta
                  </span>


                  <h2
                    className="
                      text-2xl
                      font-bold
                      text-text-base
                    "
                  >
                    Iniciar sesión
                  </h2>


                  <p className="text-sm text-text-muted mt-1">
                    Ingresa tus credenciales para acceder
                    al sistema
                  </p>

                </div>


                <Input
                  label="Correo electrónico"
                  type="email"
                  name="correo"
                  placeholder="correo@empresa.com"
                  value={form.correo}
                  onChange={updateField}
                  autoComplete="email"
                  required
                />


                <PasswordInput
                  label="Contraseña"
                  name="contraseña"
                  placeholder="Tu contraseña"
                  value={form.contraseña}
                  onChange={updateField}
                  autoComplete="current-password"
                  required
                />


                <div className="
                  flex
                  items-center
                  justify-between
                ">

                  <Checkbox
                    checked={remember}
                    onChange={setRemember}
                    label="Recordar sesión"
                  />


                  {allowRecovery && (
                    <button
                      type="button"
                      onClick={() =>
                        changeMode('forgot')
                      }
                      className="
                        text-sm
                        font-medium
                        text-er-600
                        hover:text-er-700
                        transition-colors
                      "
                    >
                      ¿Olvidaste tu contraseña?
                    </button>
                  )}

                </div>


                {message && (
                  <p className="text-sm text-red-600">
                    {message}
                  </p>
                )}


                <Button
                  type="submit"
                  fullWidth
                  loading={loading}
                  size="lg"
                >
                  {loading
                    ? 'Iniciando sesión...'
                    : 'Iniciar sesión'}
                </Button>


                {allowRegistration && (
                  <>

                    <div className="
                      relative
                      flex
                      items-center
                      gap-3
                    ">

                      <div className="flex-1 h-px bg-border" />

                      <span className="
                        text-xs
                        text-text-faint
                        font-medium
                      ">
                        ¿Eres nuevo en EcoRuta?
                      </span>

                      <div className="flex-1 h-px bg-border" />

                    </div>


                    <Button
                      type="button"
                      variant="outline"
                      fullWidth
                      onClick={() =>
                        changeMode('register')
                      }
                    >
                      Crear una cuenta
                    </Button>

                  </>
                )}

              </form>

            )}


            {/* ======================================
                REGISTRO
            ====================================== */}

            {mode === 'register' && (

              <form
                onSubmit={handleRegister}
                className="flex flex-col gap-5"
              >

                <div className="mb-2">

                  <span className="
                    text-xs
                    font-bold
                    uppercase
                    tracking-[0.12em]
                    text-er-600
                    block
                    mb-2
                  ">
                    Nueva cuenta
                  </span>


                  <h2 className="
                    text-2xl
                    font-bold
                    text-text-base
                  ">
                    Unirse a EcoRuta
                  </h2>


                  <p className="text-sm text-text-muted mt-1">
                    Crea tu cuenta y empieza a gestionar
                    tus entregas
                  </p>

                </div>


                {/* Role selector */}

                {visibleRoles.length > 1 && (

                <div>

                  <label className="
                    text-sm
                    font-medium
                    text-text-secondary
                    block
                    mb-2
                  ">
                    Tipo de cuenta
                    <span className="text-red-500"> *</span>
                  </label>


                  <div className="grid grid-cols-2 gap-3">

                    {visibleRoles.map((role) => (

                      <button
                        key={role.id}
                        type="button"
                        onClick={() =>
                          setForm((current) => ({
                            ...current,
                            id_rol: role.id
                          }))
                        }
                        className={`
                          p-4
                          rounded-xl
                          border-2
                          text-left
                          flex
                          flex-col
                          gap-2
                          transition-all
                          duration-150
                          ${
                            form.id_rol === role.id
                              ? 'border-er-600 bg-er-50'
                              : 'border-border bg-card hover:border-border-strong'
                          }
                        `}
                      >

                        <span
                          className={
                            form.id_rol === role.id
                              ? 'text-er-700'
                              : 'text-text-muted'
                          }
                        >
                          {role.icon}
                        </span>


                        <div>

                          <p
                            className={`
                              text-sm
                              font-semibold
                              ${
                                form.id_rol === role.id
                                  ? 'text-er-800'
                                  : 'text-text-base'
                              }
                            `}
                          >
                            {role.label}
                          </p>


                          <p className="
                            text-xs
                            text-text-muted
                          ">
                            {role.desc}
                          </p>

                        </div>

                      </button>

                    ))}

                  </div>

                </div>

                )}


                {/* Name */}

                <div className="grid grid-cols-2 gap-3">

                  <Input
                    label="Nombre"
                    name="nombre"
                    placeholder="Tu nombre"
                    value={form.nombre}
                    onChange={updateField}
                    required
                  />


                  <Input
                    label="Apellido"
                    name="apellido"
                    placeholder="Tu apellido"
                    value={form.apellido}
                    onChange={updateField}
                    required
                  />

                </div>


                {/* Document */}

                <Input
                  label="Documento de identidad"
                  name="documento_identidad"
                  placeholder="Ej: 1234567"
                  value={form.documento_identidad}
                  onChange={updateField}
                  required
                />


                {/* Email */}

                <Input
                  label="Correo electrónico"
                  type="email"
                  name="correo"
                  placeholder="correo@empresa.com"
                  value={form.correo}
                  onChange={updateField}
                  required
                />


                {/* Password */}

                <PasswordInput
                  label="Contraseña"
                  name="contraseña"
                  placeholder="Mínimo 8 caracteres"
                  value={form.contraseña}
                  onChange={updateField}
                  required
                />


                {/* Terms */}

                <Checkbox
                  checked={terms}
                  onChange={setTerms}
                  label="Acepto los Términos de servicio y la Política de privacidad"
                />


                {message && (
                  <p className="text-sm text-red-600">
                    {message}
                  </p>
                )}


                <Button
                  type="submit"
                  fullWidth
                  size="lg"
                  loading={loading}
                  disabled={
                    !terms ||
                    !form.id_rol
                  }
                >
                  {loading
                    ? 'Creando cuenta...'
                    : 'Crear cuenta'}
                </Button>


                <p className="
                  text-sm
                  text-center
                  text-text-muted
                ">
                  ¿Ya tienes cuenta?{' '}

                  <button
                    type="button"
                    onClick={() =>
                      changeMode('login')
                    }
                    className="
                      font-semibold
                      text-er-600
                      hover:text-er-700
                      transition-colors
                    "
                  >
                    Iniciar sesión
                  </button>

                </p>

              </form>

            )}


            {/* ======================================
                RECUPERAR CONTRASEÑA
            ====================================== */}

            {mode === 'forgot' && (

              <>
                {sentRecovery ? (

                  <div className="
                    flex
                    flex-col
                    items-center
                    text-center
                    gap-6
                  ">

                    <div className="
                      w-16
                      h-16
                      rounded-2xl
                      bg-er-100
                      flex
                      items-center
                      justify-center
                    ">

                      <svg
                        width="28"
                        height="28"
                        viewBox="0 0 28 28"
                        fill="none"
                        stroke="#16a34a"
                        strokeWidth="1.8"
                      >
                        <rect
                          x="3"
                          y="6"
                          width="22"
                          height="16"
                          rx="3"
                        />

                        <path
                          d="M3 9l11 7.5L25 9"
                          strokeLinecap="round"
                        />

                      </svg>

                    </div>


                    <div>

                      <h2 className="
                        text-2xl
                        font-bold
                        text-text-base
                      ">
                        Revisa tu correo
                      </h2>


                      <p className="
                        text-sm
                        text-text-muted
                        mt-2
                        leading-relaxed
                        max-w-xs
                        mx-auto
                      ">
                        Enviamos las instrucciones de
                        recuperación a tu correo.
                        Revisa también la carpeta de spam.
                      </p>

                    </div>


                    <Button
                      variant="outline"
                      fullWidth
                      onClick={() =>
                        changeMode('login')
                      }
                    >
                      Volver al inicio de sesión
                    </Button>

                  </div>

                ) : (

                  <form
                    onSubmit={handleRecovery}
                    className="flex flex-col gap-5"
                  >

                    <div className="mb-2">

                      <button
                        type="button"
                        onClick={() =>
                          changeMode('login')
                        }
                        className="
                          flex
                          items-center
                          gap-1.5
                          text-sm
                          font-medium
                          text-text-muted
                          hover:text-text-base
                          transition-colors
                          mb-4
                        "
                      >
                        <ArrowLeft size={16} className="inline-block -mt-0.5" /> Volver
                      </button>


                      <h2 className="
                        text-2xl
                        font-bold
                        text-text-base
                      ">
                        Recuperar contraseña
                      </h2>


                      <p className="
                        text-sm
                        text-text-muted
                        mt-1
                      ">
                        Ingresa tu correo y te enviaremos
                        las instrucciones para restablecer
                        tu contraseña.
                      </p>

                    </div>


                    <Input
                      label="Correo electrónico"
                      type="email"
                      name="correo"
                      placeholder="correo@empresa.com"
                      value={form.correo}
                      onChange={updateField}
                      required
                    />


                    {message && (
                      <p className="text-sm text-red-600">
                        {message}
                      </p>
                    )}


                    <Button
                      type="submit"
                      fullWidth
                      size="lg"
                      loading={loading}
                    >
                      {loading
                        ? 'Enviando...'
                        : 'Enviar instrucciones'}
                    </Button>

                  </form>

                )}
              </>

            )}


            {/* ======================================
                RESTABLECER CONTRASEÑA
            ====================================== */}

            {mode === 'reset' && (

              <form
                onSubmit={handleReset}
                className="flex flex-col gap-5"
              >

                <div className="mb-2">

                  <span className="
                    text-xs
                    font-bold
                    uppercase
                    tracking-[0.12em]
                    text-er-600
                    block
                    mb-2
                  ">
                    Recuperación de cuenta
                  </span>


                  <h2 className="
                    text-2xl
                    font-bold
                    text-text-base
                  ">
                    Nueva contraseña
                  </h2>


                  <p className="
                    text-sm
                    text-text-muted
                    mt-1
                  ">
                    Crea una nueva contraseña para volver
                    a acceder a tu cuenta.
                  </p>

                </div>


                {!recoveryToken && (

                  <Input
                    label="Token de recuperación"
                    placeholder="Ingresa tu token"
                    value={resetForm.token}
                    onChange={(event) =>
                      setResetForm((current) => ({
                        ...current,
                        token: event.target.value
                      }))
                    }
                    required
                  />

                )}


                <PasswordInput
                  label="Nueva contraseña"
                  placeholder="Mínimo 8 caracteres"
                  value={resetForm.nueva_contraseña}
                  onChange={(event) =>
                    setResetForm((current) => ({
                      ...current,
                      nueva_contraseña: event.target.value
                    }))
                  }
                  required
                />


                <PasswordInput
                  label="Confirmar contraseña"
                  placeholder="Repite tu contraseña"
                  value={resetForm.confirmar_contraseña}
                  onChange={(event) =>
                    setResetForm((current) => ({
                      ...current,
                      confirmar_contraseña: event.target.value
                    }))
                  }
                  required
                />


                {message && (
                  <p className="text-sm text-red-600">
                    {message}
                  </p>
                )}


                <Button
                  type="submit"
                  fullWidth
                  size="lg"
                  loading={loading}
                >
                  {loading
                    ? 'Restableciendo...'
                    : 'Restablecer contraseña'}
                </Button>


                <button
                  type="button"
                  onClick={() =>
                    changeMode('login')
                  }
                  className="
                    text-sm
                    font-medium
                    text-er-600
                    hover:text-er-700
                    transition-colors
                  "
                >
                  Volver al inicio de sesión
                </button>

              </form>

            )}

          </div>

        </div>


        {/* Navigation dots */}

        <div className="
          flex
          items-center
          justify-center
          gap-2
          pb-8
        ">

          {['login', ...(allowRegistration ? ['register'] : []), 'forgot'].map(
            (view) => (

              <button
                key={view}
                type="button"
                onClick={() => changeMode(view)}
                className={`
                  rounded-full
                  transition-all
                  duration-150
                  ${
                    mode === view
                      ? 'w-5 h-2 bg-er-600'
                      : 'w-2 h-2 bg-border-strong hover:bg-er-400'
                  }
                `}
                aria-label={view}
              />

            )
          )}

        </div>

      </div>

    </div>
  );
}


/* ============================================
    ILUSTRACIÓN DE RUTA
============================================ */

function RouteIllustration() {
  return (
    <svg
      viewBox="0 0 280 100"
      fill="none"
      className="w-full opacity-30"
      aria-hidden="true"
    >

      <path
        d="
          M20 80
          C40 80 50 30 80 30
          C110 30 120 60 150 60
          C180 60 190 20 220 20
          C240 20 255 35 265 30
        "
        stroke="#4ade80"
        strokeWidth="2"
        strokeLinecap="round"
        strokeDasharray="4 5"
      />

      <circle
        cx="20"
        cy="80"
        r="5"
        fill="#22c55e"
      />

      <circle
        cx="20"
        cy="80"
        r="9"
        fill="none"
        stroke="#22c55e"
        strokeWidth="1.5"
        opacity="0.4"
      />

      <circle
        cx="80"
        cy="30"
        r="3"
        fill="#4ade80"
      />

      <circle
        cx="150"
        cy="60"
        r="3"
        fill="#4ade80"
      />

      <circle
        cx="220"
        cy="20"
        r="3"
        fill="#4ade80"
      />

      <path
        d="
          M265 30
          C265 24 270 18 265 14
          C260 18 265 24 265 30Z
        "
        fill="#4ade80"
      />

      <circle
        cx="265"
        cy="18"
        r="4"
        fill="#166534"
      />

    </svg>
  );
}