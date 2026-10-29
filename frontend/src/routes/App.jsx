import { useEffect, useState } from 'react';
import AccountPanel from '../components/AccountPanel.jsx';
import AuthPanel from '../components/AuthPanel.jsx';
import DashboardPage from '../pages/DashboardPage.jsx';
import LoginPage from '../pages/LoginPage.jsx';
import Recupercion_contrasena from '../pages/Recupercion_contrasena.jsx';
import { clearAuthSession, endAuthSession, getTokenExpiration } from '../services/authSession.js';

function getStoredUser() {
  const token = localStorage.getItem('ecoruta_token');
  const expiresAt = token ? getTokenExpiration(token) : null;

  if (!token || !expiresAt || expiresAt <= Date.now()) {
    clearAuthSession();
    return null;
  }

  try {
    return JSON.parse(localStorage.getItem('ecoruta_user') || 'null');
  } catch {
    clearAuthSession();
    return null;
  }
}

export default function App() {
  const [showAuth, setShowAuth] = useState(false);
  const [user, setUser] = useState(getStoredUser);
  const [accountOpen, setAccountOpen] = useState(false);

  useEffect(() => {
    const handleAuthRequired = () => {
      clearAuthSession();
      setUser(null);
    };
    const handleStorage = (event) => {
      if (event.key === 'ecoruta_token' && !event.newValue) {
        setUser(null);
      }
    };
    window.addEventListener('ecoruta-auth-required', handleAuthRequired);
    window.addEventListener('storage', handleStorage);
    return () => {
      window.removeEventListener('ecoruta-auth-required', handleAuthRequired);
      window.removeEventListener('storage', handleStorage);
    };
  }, []);

  useEffect(() => {
    if (!user) return undefined;

    const token = localStorage.getItem('ecoruta_token');
    const expiresAt = token ? getTokenExpiration(token) : null;
    if (!expiresAt || expiresAt <= Date.now()) {
      endAuthSession();
      return undefined;
    }

    let timeoutId;
    const closeExpiredSession = () => {
      const remaining = expiresAt - Date.now();
      if (remaining <= 0) {
        endAuthSession();
        return;
      }
      timeoutId = window.setTimeout(closeExpiredSession, Math.min(remaining, 2_147_483_647));
    };

    closeExpiredSession();
    return () => window.clearTimeout(timeoutId);
  }, [user]);

  if (window.location.pathname === '/recuperar-contrasena') {
    return <Recupercion_contrasena />;
  }

  if (!user) {
    return <LoginPage onAuthenticated={setUser} />;
  }

  if (Number(user.debe_cambiar_contraseña) === 1) {
    return <main className="login-shell"><img className="login-logo" src="/EcoRutaLogo.png" alt="EcoRuta, plataforma de gestión logística verde" /><AccountPanel forced user={user} onUpdated={(updatedUser) => { const nextUser = { ...user, ...updatedUser, debe_cambiar_contraseña: 0 }; localStorage.setItem('ecoruta_user', JSON.stringify(nextUser)); setUser(nextUser); }} /></main>;
  }

  return (
    <>
      <header className="site-header">
        <nav aria-label="Navegacion principal">
          {/* {user ? <a href="#panel">Panel</a> : <a href="#rutas">Rutas</a>}
            <a href="#nosotros">Nosotros</a> */}
        </nav>
        <button type="button" className="header-button header-button--account" onClick={() => setAccountOpen(true)} aria-label="Abrir mi cuenta" title="Mi cuenta">
          <span aria-hidden="true">&#128100;</span>
        </button>
      </header>
      <DashboardPage user={user} onLogout={() => { clearAuthSession(); setUser(null); }} onUserUpdate={(updatedUser) => { localStorage.setItem('ecoruta_user', JSON.stringify(updatedUser)); setUser(updatedUser); }} />
      <footer id="nosotros">EcoRuta</footer>
      {showAuth && <AuthPanel onClose={() => setShowAuth(false)} onAuthenticated={setUser} />}
      {accountOpen && <div className="auth-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setAccountOpen(false)}><div className="account-modal"><button className="auth-close" type="button" onClick={() => setAccountOpen(false)}>x</button><AccountPanel user={user} onUpdated={(updatedUser) => { localStorage.setItem('ecoruta_user', JSON.stringify(updatedUser)); setUser(updatedUser); }} /></div></div>}
    </>
  );
}
