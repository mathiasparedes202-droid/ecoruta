import { useState } from 'react';
import {
  ClipboardPlus,
  LayoutDashboard,
  LogOut,
  PackageSearch,
  Store,
  UserCog,
  UserRound,
} from 'lucide-react';

// Pages del Comerciante
import MerchantDashboard from './MerchantDashboard.jsx';
import NewOrderPage from './PedidoPage.jsx';
import MyOrdersPage from './MisPedidos.jsx';
import CommercePage from './MiComercio.jsx';
import ClientesPage from './ClientesPage.jsx';
import AdminOverview from './AdminOverview.jsx';
import AdminOrders from './AdminOrders.jsx';
import AdminUsers from './AdminUsers.jsx';
import NotificationBell from '../components/NotificationBell.jsx';

const modules = {
  1: [
    {
      key: 'merchant-dashboard',
      title: 'Dashboard',
      text: 'Resumen de la actividad de tu comercio.',
      tone: 'teal'
    },
    {
      key: 'new-order',
      title: 'Nueva solicitud',
      text: 'Crea una nueva solicitud de entrega.',
      tone: 'orange'
    },
    {
      key: 'my-orders',
      title: 'Mis pedidos',
      text: 'Consulta y realiza seguimiento de tus pedidos.',
      tone: 'blue'
    },
    {
      key: 'clientes',
      title: 'Mis clientes',
      text: 'Registrá clientes con su ubicación exacta.',
      tone: 'teal'
    },
    {
      key: 'commerce',
      title: 'Mi comercio',
      text: 'Administra la información de tu comercio.',
      tone: 'teal'
    }
  ],

  2: [
    {
      key: 'available',
      title: 'Entregas disponibles',
      text: 'Encuentra pedidos listos para asignar.',
      tone: 'orange'
    },
    {
      key: 'active',
      title: 'Mi recorrido',
      text: 'Gestiona tus entregas en curso.',
      tone: 'teal'
    },
    {
      key: 'profile',
      title: 'Mi vehículo',
      text: 'Actualiza disponibilidad y vehículo.',
      tone: 'blue'
    }
  ],

  3: [
    {
      key: 'users',
      title: 'Usuarios',
      text: 'Administra cuentas y permisos.',
      tone: 'teal'
    },
    {
      key: 'orders',
      title: 'Pedidos',
      text: 'Supervisa todos los pedidos.',
      tone: 'orange'
    },
    {
      key: 'metrics',
      title: 'Métricas',
      text: 'Revisa impacto y actividad diaria.',
      tone: 'blue'
    }
  ]
};

const roleNames = {
  1: 'Comerciante',
  2: 'Repartidor',
  3: 'Administrador'
};

const moduleIcons = {
  'merchant-dashboard': LayoutDashboard,
  'new-order': ClipboardPlus,
  'my-orders': PackageSearch,
  clientes: UserRound,
  commerce: Store,
  users: UserCog,
  overview: LayoutDashboard
};

export default function DashboardPage({ user, onLogout, onUserUpdate }) {
  const userModules = modules[user.id_rol] || modules[1];
  // const companyName = user.nombre_comercio || user.nombre_empresa || user.comercio || 'Mi comercio';

  // Para comerciante iniciamos directamente en su dashboard
  const initialModule =
    Number(user.id_rol) === 1
      ? 'merchant-dashboard'
      : 'overview';

  const [activeModule, setActiveModule] = useState(initialModule);

  function renderContent() {
    /*
      =====================================
      COMERCIANTE - ROL 1
      =====================================
    */

    if (Number(user.id_rol) === 1) {
      switch (activeModule) {
        case 'merchant-dashboard':
          return (
            <MerchantDashboard
              user={user}
              onNavigate={setActiveModule}
            />
          );

        case 'new-order':
          return (
            <NewOrderPage
              user={user}
              onBack={() =>
                setActiveModule('merchant-dashboard')
              }
              onNavigate={setActiveModule}
            />
          );

        case 'my-orders':
          return (
            <MyOrdersPage
              user={user}
              onNavigate={setActiveModule}
            />
          );

        case 'commerce':
          return (
            <CommercePage
              user={user}
              onUserUpdate={onUserUpdate}
            />
          );

        case 'clientes':
          return (
            <ClientesPage
              user={user}
            />
          );

        default:
          return (
            <MerchantDashboard
              user={user}
              onNavigate={setActiveModule}
            />
          );
      }
    }

    /*
      =====================================
      OTROS ROLES
      =====================================
    */

    if (Number(user.id_rol) === 3 && activeModule === 'overview') {
      return <AdminOverview />;
    }

    if (Number(user.id_rol) === 3 && activeModule === 'metrics') {
      return <AdminOverview />;
    }

    if (Number(user.id_rol) === 3 && activeModule === 'users') {
      return <AdminUsers />;
    }

    if (Number(user.id_rol) === 3 && activeModule === 'orders') {
      return <AdminOrders user={user} />;
    }

    return (
      <section className="module-detail">
        <p className="eyebrow">
          Módulo seleccionado
        </p>

        <h2>
          {userModules.find(
            (module) => module.key === activeModule
          )?.title}
        </h2>

        <p>
          {userModules.find(
            (module) => module.key === activeModule
          )?.text}
        </p>

        <button
          className="primary-action"
          type="button"
        >
          Configurar módulo
          <span aria-hidden="true">-&gt;</span>
        </button>
      </section>
    );
  }

  return (
    <main className={`dashboard ${Number(user.id_rol) === 3 ? 'dashboard--admin' : ''}`}>

      {/* ================= SIDEBAR ================= */}

      <aside className="dashboard-sidebar">

        <a className="dashboard-brand" href="/" aria-label="EcoRuta, página principal">
          <img src="/EcoRutaLogo.png" alt="EcoRuta" />
        </a>

        <p className="eyebrow">
          Panel EcoRuta
        </p>

        <nav
          className="dashboard-nav"
          aria-label="Módulos del panel"
        >

          {/* RESUMEN SOLO PARA OTROS ROLES */}
          {Number(user.id_rol) !== 1 && (
            <button
              className={
                activeModule === 'overview'
                  ? 'dashboard-nav__active'
                  : ''
              }
              type="button"
              onClick={() =>
                setActiveModule('overview')
              }
            >
              <span className="dashboard-nav__icon" aria-hidden="true">
                <LayoutDashboard size={18} strokeWidth={1.8} />
              </span>
              Resumen
            </button>
          )}

          {userModules.map((module) => (
            <button
              className={
                activeModule === module.key
                  ? 'dashboard-nav__active'
                  : ''
              }
              key={module.key}
              type="button"
              onClick={() =>
                setActiveModule(module.key)
              }
            >
              <span className="dashboard-nav__icon" aria-hidden="true">
                {(() => {
                  const Icon = moduleIcons[module.key] || LayoutDashboard;
                  return <Icon size={18} strokeWidth={1.8} />;
                })()}
              </span>
              {module.title}
            </button>
          ))}

        </nav>

        <button
          className="logout-button"
          type="button"
          onClick={onLogout}
        >
          <span className="logout-button__icon" aria-hidden="true">
            <LogOut size={17} strokeWidth={1.8} />
          </span>
          <span className="logout-user-name">
            {user.nombre_completo || 'Usuario'}
          </span>
          <span className="logout-user-role">
            {roleNames[user.id_rol] || user.rol || 'Usuario'}
          </span>
          Cerrar sesión
        </button>

      </aside>


      {/* ================= CONTENIDO ================= */}

      <header
        className="dashboard-content"
        id="panel"
      >
        {(Number(user.id_rol) === 1 || Number(user.id_rol) === 3) && (
          <NotificationBell
            roleId={Number(user.id_rol)}
            onNavigateToOrders={() =>
              setActiveModule(
                Number(user.id_rol) === 1 ? 'my-orders' : 'orders'
              )
            }
          />
        )}

        {/* ================= PAGE ACTIVA ================= */}

        {renderContent()}

      </header>

    </main>
  );
}