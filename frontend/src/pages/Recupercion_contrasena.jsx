import { useState } from 'react';
import { ArrowUpRight } from 'lucide-react';
import { requestRecovery, resetAccount } from '../services/accountService.js';

export default function Recupercion_contrasena() {
	const recoveryToken = new URLSearchParams(window.location.search).get('reset_token') || '';
	const [correo, setCorreo] = useState('');
	const [token, setToken] = useState(recoveryToken);
	const [nuevaContrasena, setNuevaContrasena] = useState('');
	const [message, setMessage] = useState('');
	const [loading, setLoading] = useState(false);

	async function submitRecovery(event) {
		event.preventDefault();
		setLoading(true);
		setMessage('');
		try {
			await requestRecovery(correo);
			setMessage('Si la cuenta existe, recibirás un enlace en tu correo. Revisa también spam.');
		} catch (error) {
			setMessage(error.message);
		} finally {
			setLoading(false);
		}
	}

	async function submitReset(event) {
		event.preventDefault();
		setLoading(true);
		setMessage('');
		try {
			const result = await resetAccount({ token, nueva_contraseña: nuevaContrasena });
			setMessage(result.message || '¡Listo! Tu contraseña fue actualizada.');
			setToken('');
			setNuevaContrasena('');
		} catch (error) {
			setMessage(error.message);
		} finally {
			setLoading(false);
		}
	}

	return (
		<main className="login-shell">
			<section className="auth-panel auth-panel--recovery" aria-labelledby="recovery-title">
				<div className="recovery-heading">
					<div className="recovery-icon" aria-hidden="true"><ArrowUpRight size={18} /></div>
					<div><p className="login-kicker">Seguridad de tu cuenta</p>
						<p className="recovery-step">Recuperación de acceso</p></div>
				</div>
				<h2 id="recovery-title">Recuperar cuenta</h2>
				<p className="auth-description">Solicita un enlace para recuperar tu acceso o establece una nueva contraseña con tu token.</p>
				<form onSubmit={submitRecovery}>
					<label>Correo electrónico<input type="email" value={correo} onChange={(event) => setCorreo(event.target.value)} required /></label>
					<button className="auth-submit" type="submit" disabled={loading}>{loading ? 'Procesando...' : 'Enviar enlace'}</button>
				</form>
				<div className="recovery-divider"><span>Ya tienes un token</span></div>
				<form onSubmit={submitReset}>
					<label>Token de recuperación<input value={token} onChange={(event) => setToken(event.target.value)} required /></label>
					<label>Nueva contraseña<input type="password" value={nuevaContrasena} onChange={(event) => setNuevaContrasena(event.target.value)} required minLength="8" /></label>
					<button className="auth-submit" type="submit" disabled={loading}>{loading ? 'Procesando...' : 'Cambiar contraseña'}</button>
				</form>
				{message && <p className="auth-message">{message}</p>}
				<a className="auth-switch" href="/">Volver al inicio de sesión</a>
			</section>
			<p className="login-note">Acceso exclusivo para el equipo EcoRuta</p>
		</main>
	);
}
