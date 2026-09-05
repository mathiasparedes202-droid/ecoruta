import { useEffect, useState } from 'react';
import AccountPanel from '../components/AccountPanel.jsx';
import AuthPanel from '../components/AuthPanel.jsx';
import DashboardPage from '../pages/DashboardPage.jsx';
import LoginPage from '../pages/LoginPage.jsx';
import Recupercion_contrasena from '../pages/Recupercion_contrasena.jsx';

export default function App() {
  const [showAuth, setShowAuth] = useState(false);
  const [user, setUser] = useState(() => {
    const token = localStorage.getItem('ecoruta_token');
    return token ? JSON.parse(localStorage.getItem('ecoruta_user') || 'null') : null;
  });
  const [accountOpen, setAccountOpen] = useState(false);

  useEffect(() => {
    const handleAuthRequired = () => setUser(null);
    window.addEventListener('ecoruta-auth-required', handleAuthRequired);
    return () => window.removeEventListener('ecoruta-auth-required', handleAuthRequired);
  }, []);

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
      <DashboardPage user={user} onLogout={() => { localStorage.removeItem('ecoruta_user'); localStorage.removeItem('ecoruta_token'); setUser(null); }} onUserUpdate={(updatedUser) => { localStorage.setItem('ecoruta_user', JSON.stringify(updatedUser)); setUser(updatedUser); }} />
      <footer id="nosotros">EcoRuta</footer>
      {showAuth && <AuthPanel onClose={() => setShowAuth(false)} onAuthenticated={setUser} />}
      {accountOpen && <div className="auth-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setAccountOpen(false)}><div className="account-modal"><button className="auth-close" type="button" onClick={() => setAccountOpen(false)}>x</button><AccountPanel user={user} onUpdated={(updatedUser) => { localStorage.setItem('ecoruta_user', JSON.stringify(updatedUser)); setUser(updatedUser); }} /></div></div>}
    </>
  );
}
