import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './auth';
import { getAuthGateState } from './auth-gate.js';
import { AUTH_SESSION_VALIDATING_MESSAGE } from './auth-messages.js';
import {
  ADMIN_DASHBOARD_ROUTE,
  ADMIN_LOGIN_ROUTE,
} from './panel-routes.js';
import Layout from './components/Layout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Licenses from './pages/Licenses';
import LicenseDetail from './pages/LicenseDetail';
import LicenseForm from './pages/LicenseForm';
import Customers from './pages/Customers';
import CustomerForm from './pages/CustomerForm';
import CustomerDetail from './pages/CustomerDetail';

function PrivateRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();
  const gateState = getAuthGateState({ loading, isAuthenticated });

  if (gateState === 'loading') {
    return <div className="min-h-screen flex items-center justify-center text-sm text-gray-500">{AUTH_SESSION_VALIDATING_MESSAGE}</div>;
  }

  return gateState === 'authenticated' ? children : <Navigate to={ADMIN_LOGIN_ROUTE} replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path={ADMIN_LOGIN_ROUTE} element={<Login />} />
      <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
        <Route index element={<Navigate to={ADMIN_DASHBOARD_ROUTE} replace />} />
        <Route path="dashboard" element={<Dashboard />} />
        <Route path="licenses" element={<Licenses />} />
        <Route path="licenses/new" element={<LicenseForm />} />
        <Route path="licenses/:id" element={<LicenseDetail />} />
        <Route path="licenses/:id/edit" element={<LicenseForm />} />
        <Route path="customers" element={<Customers />} />
        <Route path="customers/new" element={<CustomerForm />} />
        <Route path="customers/:id" element={<CustomerDetail />} />
        <Route path="customers/:id/edit" element={<CustomerForm />} />
      </Route>
      <Route path="*" element={<Navigate to={ADMIN_DASHBOARD_ROUTE} replace />} />
    </Routes>
  );
}
