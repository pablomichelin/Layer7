function getSessionLifecycleDecision({
  now,
  expiresAt,
  absoluteExpiresAt,
  renewWindowMs,
  idleTimeoutMs,
}) {
  if (now >= expiresAt || now >= absoluteExpiresAt) {
    return { action: 'expired' };
  }

  const timeUntilIdleExpiry = expiresAt.getTime() - now.getTime();
  if (timeUntilIdleExpiry > renewWindowMs) {
    return {
      action: 'keep_alive',
      nextExpiresAt: expiresAt,
    };
  }

  return {
    action: 'renew',
    nextExpiresAt: new Date(Math.min(
      now.getTime() + idleTimeoutMs,
      absoluteExpiresAt.getTime()
    )),
  };
}

module.exports = {
  getSessionLifecycleDecision,
};
