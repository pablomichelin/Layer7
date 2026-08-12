/**
 * Consulta DB + avaliação 30.15 (alerta multi-appliance).
 * Separado da lógica pura para manter testes unitários sem Postgres.
 */

const {
  DEFAULT_LOOKBACK_DAYS,
  evaluateMultiApplianceAbuse,
} = require('./multi-appliance-abuse');

/**
 * @param {import('pg').Pool|import('pg').PoolClient} queryable
 * @param {{ lookbackDays?: number, sampleLimit?: number }} [opts]
 */
async function collectMultiApplianceAlerts(queryable, {
  lookbackDays = DEFAULT_LOOKBACK_DAYS,
  sampleLimit = 8,
} = {}) {
  const candidates = await queryable.query(
    `
    WITH observed AS (
      SELECT license_id, lower(btrim(hardware_id)) AS hw
        FROM activations_log
       WHERE hardware_id IS NOT NULL
         AND btrim(hardware_id) <> ''
         AND created_at > NOW() - ($1::int * INTERVAL '1 day')
      UNION ALL
      SELECT license_id, lower(btrim(hardware_id)) AS hw
        FROM check_ins_log
       WHERE hardware_id IS NOT NULL
         AND btrim(hardware_id) <> ''
         AND created_at > NOW() - ($1::int * INTERVAL '1 day')
    ),
    distinct_hw AS (
      SELECT license_id, array_agg(DISTINCT hw ORDER BY hw) AS hws
        FROM observed
       WHERE length(hw) >= 8
       GROUP BY license_id
      HAVING COUNT(DISTINCT hw) >= 2
    )
    SELECT l.id,
           l.license_key,
           l.hardware_id,
           c.name AS customer_name,
           d.hws AS observed_hardware_ids
      FROM distinct_hw d
      JOIN licenses l ON l.id = d.license_id
      LEFT JOIN customers c ON c.id = l.customer_id
     WHERE l.archived_at IS NULL
       AND (c.id IS NULL OR c.archived_at IS NULL)
     ORDER BY l.id ASC
    `,
    [lookbackDays]
  );

  if (candidates.rows.length === 0) {
    return { total: 0, sample: [], lookback_days: lookbackDays };
  }

  const licenseIds = candidates.rows.map((row) => String(row.id));
  const rebinds = await queryable.query(
    `
    SELECT metadata->>'license_id' AS license_id,
           metadata->>'previous_hardware_id' AS previous_hardware_id,
           metadata->>'new_hardware_id' AS new_hardware_id,
           created_at
      FROM admin_audit_log
     WHERE event_type = 'license_rebound'
       AND result = 'success'
       AND metadata->>'license_id' = ANY($1::text[])
     ORDER BY created_at ASC
    `,
    [licenseIds]
  );

  const rebindByLicense = new Map();
  for (const row of rebinds.rows) {
    const key = String(row.license_id);
    if (!rebindByLicense.has(key)) {
      rebindByLicense.set(key, []);
    }
    rebindByLicense.get(key).push({
      previous_hardware_id: row.previous_hardware_id,
      new_hardware_id: row.new_hardware_id,
    });
  }

  const alerts = [];
  for (const row of candidates.rows) {
    const evaluation = evaluateMultiApplianceAbuse({
      currentHardwareId: row.hardware_id,
      observedHardwareIds: row.observed_hardware_ids || [],
      rebindHistory: rebindByLicense.get(String(row.id)) || [],
    });

    if (!evaluation.alert) {
      continue;
    }

    alerts.push({
      id: row.id,
      license_key: row.license_key,
      customer_name: row.customer_name,
      hardware_id: row.hardware_id,
      distinct_count: evaluation.distinct_hardware_ids.length,
      unexplained_count: evaluation.unexplained_hardware_ids.length,
      unexplained_hardware_ids: evaluation.unexplained_hardware_ids,
      reason: evaluation.reason,
      lookback_days: lookbackDays,
    });
  }

  return {
    total: alerts.length,
    sample: alerts.slice(0, sampleLimit),
    lookback_days: lookbackDays,
  };
}

module.exports = {
  collectMultiApplianceAlerts,
};
