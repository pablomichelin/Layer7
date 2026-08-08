const STORAGE_KEY = 'layer7_recent_customers';
const MAX_RECENT = 5;

export function rememberRecentCustomer(customer) {
  if (!customer?.id || typeof window === 'undefined') {
    return;
  }

  const entry = {
    id: customer.id,
    name: customer.name || `Cliente #${customer.id}`,
  };

  const current = listRecentCustomers().filter((row) => String(row.id) !== String(entry.id));
  const next = [entry, ...current].slice(0, MAX_RECENT);
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
}

export function listRecentCustomers() {
  if (typeof window === 'undefined') {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed = JSON.parse(raw || '[]');
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}
