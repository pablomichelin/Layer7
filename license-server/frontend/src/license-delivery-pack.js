import { formatCalendarDate } from './format-date.js';
import { formatLicenseEquipmentLabel, formatSkuLabel } from './license-display.js';

/**
 * Texto pronto para colar no WhatsApp/email ao entregar uma licença.
 */
export function buildLicenseDeliveryPack(license = {}) {
  const key = license.license_key || '';
  const customer = license.customer_name || (license.customer_id ? `Cliente #${license.customer_id}` : '—');
  const sku = formatSkuLabel(license.features);
  const expiry = formatCalendarDate(license.expiry);
  const equipment = formatLicenseEquipmentLabel(license);

  return [
    'Layer7 — pacote de activação',
    `Cliente: ${customer}`,
    `SKU: ${sku}`,
    `Expira: ${expiry}`,
    `Equipamento: ${equipment}`,
    `Chave: ${key}`,
    `Comando: layer7d --activate ${key}`,
  ].join('\n');
}

/**
 * Resumo multi-linha para confirmações destrutivas / sensíveis.
 */
export function buildLicenseActionConfirmMessage(actionLabel, license = {}) {
  const customer = license.customer_name || (license.customer_id ? `Cliente #${license.customer_id}` : '—');
  const lines = [
    actionLabel,
    '',
    `Cliente: ${customer}`,
    `SKU: ${formatSkuLabel(license.features)}`,
    `Expira: ${formatCalendarDate(license.expiry)}`,
    `Equipamento: ${formatLicenseEquipmentLabel(license)}`,
    `Chave: ${license.license_key || '—'}`,
    '',
    'Confirma?',
  ];
  return lines.join('\n');
}
