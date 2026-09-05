import { useEffect, useState } from 'react'
import './Login.css'
import './Repartidor.css'

import Login from './Login'
import RepartidorDashboard from './RepartidorDashboard'
import { RepartidorUser } from './types/repartidor'

type InstallPromptEvent = Event & {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

export type User = {
  id: string | number
  email: string
  displayName?: string
  avatar?: string
}

export type Item = {
  id: string
  title: string
  price: number
  qty: number
  image?: string
}

const STORAGE_KEY_USER = "ecoruta_user:v1";
const STORAGE_KEY_TOKEN = "ecoruta_token";

const getStoredUser = (): RepartidorUser | null => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY_USER);
    return raw ? (JSON.parse(raw) as RepartidorUser) : null;
  } catch {
    return null;
  }
};

function App() {
  const [user, setUser] = useState<RepartidorUser | null>(() => getStoredUser());
  const [installPrompt, setInstallPrompt] = useState<InstallPromptEvent | null>(null);
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    const standalone = window.matchMedia('(display-mode: standalone)').matches
      || Boolean((window.navigator as Navigator & { standalone?: boolean }).standalone);
    setIsInstalled(standalone);

    const handleInstallPrompt = (event: Event) => {
      event.preventDefault();
      setInstallPrompt(event as InstallPromptEvent);
    };
    const handleInstalled = () => {
      setIsInstalled(true);
      setInstallPrompt(null);
    };

    window.addEventListener('beforeinstallprompt', handleInstallPrompt);
    window.addEventListener('appinstalled', handleInstalled);
    return () => {
      window.removeEventListener('beforeinstallprompt', handleInstallPrompt);
      window.removeEventListener('appinstalled', handleInstalled);
    };
  }, []);

  const handleInstall = async () => {
    if (installPrompt) {
      await installPrompt.prompt();
      await installPrompt.userChoice;
      setInstallPrompt(null);
      return;
    }

    window.alert('Para instalarla, abrí el menú del navegador y elegí "Instalar EcoRuta Repartidor" o "Agregar a pantalla de inicio".');
  };

  useEffect(() => {
    if (user) {
      if (!localStorage.getItem(STORAGE_KEY_TOKEN)) {
        setUser(null);
        return;
      }
      localStorage.setItem(STORAGE_KEY_USER, JSON.stringify(user));
    } else {
      localStorage.removeItem(STORAGE_KEY_USER);
    }
  }, [user]);

  const handleLogout = () => {
    setUser(null);
    localStorage.removeItem(STORAGE_KEY_USER);
    localStorage.removeItem(STORAGE_KEY_TOKEN);
    localStorage.removeItem("ecoruta_token:v1");
    localStorage.removeItem("ecoruta_saved_email:v1");
  };

  if (!user) {
    return <><InstallButton onInstall={handleInstall} isInstalled={isInstalled} /><Login onLogin={setUser} /></>;
  }

  return <><InstallButton onInstall={handleInstall} isInstalled={isInstalled} /><RepartidorDashboard user={user} onLogout={handleLogout} /></>;
}

function InstallButton({ onInstall, isInstalled }: { onInstall: () => void; isInstalled: boolean }) {
  if (isInstalled) return null;
  return <button className="install-app-button" type="button" onClick={onInstall}>Descargar aplicación</button>;
}

// function App() {
//   const [user, setUser] = useState<RepartidorUser | null>(() => getStoredUser())

//   useEffect(() => {
//     if (user) {
//       localStorage.setItem('ecoruta_user', JSON.stringify(user))
//     } else {
//       localStorage.removeItem('ecoruta_user')
//     }
//   }, [user])

//   const handleLogout = () => {
//     setUser(null)
//     localStorage.removeItem('ecoruta_token')
//     localStorage.removeItem('ecoruta_saved_email')
//   }

//   if (!user) {
//     return <Login onLogin={setUser} />
//   }

//   return (
//     <>
//       <RepartidorDashboard user={user} onLogout={handleLogout} />
//     </>
//   )
// }

export default App
