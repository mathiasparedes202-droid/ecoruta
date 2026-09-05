import AuthPanel from '../components/AuthPanel.jsx';

export default function LoginPage({ onAuthenticated }) {
  return (
    <AuthPanel
      standalone
      allowRegistration
      registrationRoles={['1']}
      onClose={() => {}}
      onAuthenticated={onAuthenticated}
    />
  );
}
