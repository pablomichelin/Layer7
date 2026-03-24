import { NavLink, useNavigate } from 'react-router-dom';
import { clearToken } from '../api';

const links = [
  { to: '/dashboard', label: 'Dashboard', icon: '📊' },
  { to: '/licenses', label: 'Licenças', icon: '🔑' },
  { to: '/customers', label: 'Clientes', icon: '👥' },
];

export default function Sidebar() {
  const navigate = useNavigate();

  function handleLogout() {
    clearToken();
    navigate('/login');
  }

  return (
    <aside className="w-64 bg-brand-700 text-white flex flex-col min-h-screen">
      <div className="p-6 border-b border-brand-600">
        <h1 className="text-lg font-bold tracking-tight">Layer7 License Manager</h1>
        <p className="text-brand-200 text-xs mt-1">por Systemup</p>
      </div>

      <nav className="flex-1 py-4">
        {links.map(({ to, label, icon }) => (
          <NavLink
            key={to}
            to={to}
            className={({ isActive }) =>
              `flex items-center gap-3 px-6 py-3 text-sm transition-colors ${
                isActive
                  ? 'bg-brand-600 text-white font-medium'
                  : 'text-brand-100 hover:bg-brand-600/50'
              }`
            }
          >
            <span>{icon}</span>
            {label}
          </NavLink>
        ))}
      </nav>

      <button
        onClick={handleLogout}
        className="m-4 px-4 py-2 text-sm bg-brand-800 hover:bg-brand-900 rounded transition-colors"
      >
        Sair
      </button>
    </aside>
  );
}
