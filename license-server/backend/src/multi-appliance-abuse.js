/**
 * 30.15 / BG-121 / A-08 — detecção de abuso multi-appliance (fase 1: só alerta).
 * Decisão humana n.º 7: sem max_activations nesta fase.
 *
 * Rebind autorizado (audit license_rebound) explica trocas de hardware_id
 * e não deve gerar falso positivo (GA5.12).
 */

const DEFAULT_LOOKBACK_DAYS = 30;
const DEFAULT_MIN_DISTINCT = 2;

function normalizeHw(raw) {
  if (raw === undefined || raw === null) {
    return null;
  }
  const s = String(raw).trim().toLowerCase();
  if (!s || s.length < 8) {
    return null;
  }
  return s;
}

/**
 * Constrói o conjunto de hardware_ids cobertos por rebinds autorizados
 * + bind actual da licença.
 */
function buildAuthorizedHardwareSet(currentHardwareId, rebindHistory = []) {
  const authorized = new Set();
  const current = normalizeHw(currentHardwareId);
  if (current) {
    authorized.add(current);
  }

  for (const entry of rebindHistory) {
    const prev = normalizeHw(entry?.previous_hardware_id);
    const next = normalizeHw(entry?.new_hardware_id);
    if (prev) {
      authorized.add(prev);
    }
    if (next) {
      authorized.add(next);
    }
  }

  return authorized;
}

/**
 * Avalia se há sinal de abuso (múltiplos appliances) não explicado por rebind.
 *
 * @param {object} input
 * @param {string|null} input.currentHardwareId
 * @param {string[]} input.observedHardwareIds — hw vistos em activate/check-in
 * @param {Array<{previous_hardware_id?: string, new_hardware_id?: string}>} [input.rebindHistory]
 * @param {number} [input.minDistinct=2]
 * @returns {{ alert: boolean, distinct_hardware_ids: string[], unexplained_hardware_ids: string[],
 *   authorized_hardware_ids: string[], reason: string }}
 */
function evaluateMultiApplianceAbuse({
  currentHardwareId = null,
  observedHardwareIds = [],
  rebindHistory = [],
  minDistinct = DEFAULT_MIN_DISTINCT,
} = {}) {
  const distinct = [];
  const seen = new Set();
  for (const raw of observedHardwareIds) {
    const hw = normalizeHw(raw);
    if (!hw || seen.has(hw)) {
      continue;
    }
    seen.add(hw);
    distinct.push(hw);
  }
  distinct.sort();

  const authorized = buildAuthorizedHardwareSet(currentHardwareId, rebindHistory);
  const unexplained = distinct.filter((hw) => !authorized.has(hw));

  if (distinct.length < minDistinct) {
    return {
      alert: false,
      distinct_hardware_ids: distinct,
      unexplained_hardware_ids: unexplained,
      authorized_hardware_ids: [...authorized].sort(),
      reason: 'insufficient_distinct',
    };
  }

  if (unexplained.length === 0) {
    return {
      alert: false,
      distinct_hardware_ids: distinct,
      unexplained_hardware_ids: [],
      authorized_hardware_ids: [...authorized].sort(),
      reason: 'explained_by_rebind_or_current_bind',
    };
  }

  return {
    alert: true,
    distinct_hardware_ids: distinct,
    unexplained_hardware_ids: unexplained,
    authorized_hardware_ids: [...authorized].sort(),
    reason: 'unexplained_multi_hardware',
  };
}

module.exports = {
  DEFAULT_LOOKBACK_DAYS,
  DEFAULT_MIN_DISTINCT,
  buildAuthorizedHardwareSet,
  evaluateMultiApplianceAbuse,
  normalizeHw,
};
