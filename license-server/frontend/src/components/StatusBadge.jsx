const styles = {
  active: 'bg-green-100 text-green-800',
  success: 'bg-green-100 text-green-800',
  expired: 'bg-yellow-100 text-yellow-800',
  fail: 'bg-red-100 text-red-800',
  revoked: 'bg-red-100 text-red-800',
};

const labels = {
  active: 'Activa',
  success: 'Sucesso',
  expired: 'Expirada',
  fail: 'Falha',
  revoked: 'Revogada',
};

export default function StatusBadge({ status }) {
  return (
    <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[status] || 'bg-gray-100 text-gray-700'}`}>
      {labels[status] || status}
    </span>
  );
}
