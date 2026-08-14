const pool = require('./db');
const {
  createTotpChallengeToken,
  isTotpChallengeJti,
  parseTotpChallengeToken,
} = require('./totp');

function isExpired(expiresAt) {
  const millis = expiresAt instanceof Date
    ? expiresAt.getTime()
    : new Date(expiresAt).getTime();
  return !Number.isFinite(millis) || millis <= Date.now();
}

async function issueTotpChallenge(adminId, secret, executor = pool) {
  const token = createTotpChallengeToken(adminId, secret);
  const parsed = parseTotpChallengeToken(token, secret);
  if (!parsed) {
    throw new Error('TOTP challenge issue failed.');
  }

  const client = await executor.connect();
  try {
    await client.query('BEGIN');
    await client.query(
      `UPDATE admin_totp_challenges
          SET used_at = COALESCE(used_at, NOW())
        WHERE admin_id = $1
          AND used_at IS NULL`,
      [adminId]
    );
    await client.query(
      `INSERT INTO admin_totp_challenges (jti, admin_id, expires_at)
       VALUES ($1, $2, $3)`,
      [parsed.jti, adminId, new Date(parsed.exp)]
    );
    await client.query('COMMIT');
    return token;
  } catch (err) {
    try {
      await client.query('ROLLBACK');
    } catch {
      // keep the original error
    }
    throw err;
  } finally {
    client.release();
  }
}

async function consumeTotpChallenge({ jti, adminId, executor = pool }) {
  if (!isTotpChallengeJti(jti) || adminId == null) {
    return false;
  }

  const client = await executor.connect();
  try {
    await client.query('BEGIN');
    const locked = await client.query(
      `SELECT jti, admin_id, expires_at, used_at
         FROM admin_totp_challenges
        WHERE jti = $1
        FOR UPDATE`,
      [jti]
    );
    const row = locked.rows[0];
    if (
      !row
      || Number(row.admin_id) !== Number(adminId)
      || row.used_at
      || isExpired(row.expires_at)
    ) {
      await client.query('ROLLBACK');
      return false;
    }

    await client.query(
      `UPDATE admin_totp_challenges
          SET used_at = NOW()
        WHERE jti = $1
          AND used_at IS NULL`,
      [jti]
    );
    await client.query('COMMIT');
    return true;
  } catch (err) {
    try {
      await client.query('ROLLBACK');
    } catch {
      // keep the original error
    }
    throw err;
  } finally {
    client.release();
  }
}

module.exports = {
  consumeTotpChallenge,
  issueTotpChallenge,
};
