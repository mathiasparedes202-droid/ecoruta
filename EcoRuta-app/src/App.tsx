import { useEffect, useState } from 'react'
import './Login.css'
import './Repartidor.css'

import Login from './Login'
import RepartidorDashboard from './RepartidorDashboard'
import { RepartidorUser } from './types/repartidor'

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
    return <Login onLogin={setUser} />;
  }

  return <RepartidorDashboard user={user} onLogout={handleLogout} />;
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
