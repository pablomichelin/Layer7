import { useEffect, useState } from 'react';
import { get } from '../api';
import { useDebouncedValue } from '../use-debounced-value.js';

/**
 * Select de cliente com busca no servidor (evita truncar em 200).
 */
export default function CustomerSelect({
  name = 'customer_id',
  value,
  onChange,
  required = false,
  disabled = false,
  allowEmpty = true,
  emptyLabel = 'Seleccionar cliente...',
}) {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [options, setOptions] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [selectedLabel, setSelectedLabel] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    const params = new URLSearchParams({ page: '1', limit: '50' });
    if (debouncedSearch) {
      params.set('search', debouncedSearch);
    }

    get(`/customers?${params}`, { signal: controller.signal })
      .then((data) => {
        setOptions(data.customers || []);
        setTotal(data.total || 0);
      })
      .catch((err) => {
        if (err?.name === 'AbortError' || controller.signal.aborted) {
          return;
        }
        console.error(err);
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, [debouncedSearch]);

  useEffect(() => {
    if (!value) {
      setSelectedLabel('');
      return undefined;
    }

    const inOptions = options.find((row) => String(row.id) === String(value));
    if (inOptions) {
      setSelectedLabel(inOptions.name);
      return undefined;
    }

    const controller = new AbortController();
    get(`/customers/${value}`, { signal: controller.signal })
      .then((data) => {
        setSelectedLabel(data.customer?.name || `Cliente #${value}`);
      })
      .catch((err) => {
        if (err?.name === 'AbortError' || controller.signal.aborted) {
          return;
        }
        setSelectedLabel(`Cliente #${value}`);
      });

    return () => controller.abort();
  }, [value, options]);

  const selectOptions = [...options];
  if (value && !selectOptions.some((row) => String(row.id) === String(value))) {
    selectOptions.unshift({ id: value, name: selectedLabel || `Cliente #${value}` });
  }

  return (
    <div className="space-y-2">
      {!disabled && (
        <input
          type="text"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Filtrar clientes por nome, email, CNPJ..."
          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none"
        />
      )}
      <select
        name={name}
        value={value || ''}
        onChange={onChange}
        required={required}
        disabled={disabled}
        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none disabled:bg-gray-100 disabled:text-gray-500"
      >
        {allowEmpty ? <option value="">{emptyLabel}</option> : null}
        {selectOptions.map((customer) => (
          <option key={customer.id} value={customer.id}>{customer.name}</option>
        ))}
      </select>
      <p className="text-xs text-gray-500">
        {loading ? 'A carregar...' : `A mostrar ${selectOptions.length} de ${total} cliente(s).`}
        {total > 50 ? ' Refine a busca se o cliente não aparecer.' : ''}
      </p>
    </div>
  );
}
