import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';

export default function Layout() {
  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <main className="flex-1 p-6 lg:p-8 bg-gray-50 overflow-auto">
        <Outlet />
        <footer className="mt-12 pt-4 border-t border-gray-200 text-center text-xs text-gray-400">
          Systemup Solucao em Tecnologia — www.systemup.inf.br
        </footer>
      </main>
    </div>
  );
}
