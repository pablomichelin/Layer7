function getAdminAccessTokenFromSources({ bearerSessionToken, sessionToken }) {
  return bearerSessionToken || sessionToken || null;
}

function getAdminAccessTokenCandidates({ bearerSessionToken, sessionToken }) {
  const candidates = [];

  if (bearerSessionToken) {
    candidates.push({ source: 'bearer', token: bearerSessionToken });
  }

  if (sessionToken && sessionToken !== bearerSessionToken) {
    candidates.push({ source: 'cookie', token: sessionToken });
  }

  return candidates;
}

module.exports = {
  getAdminAccessTokenCandidates,
  getAdminAccessTokenFromSources,
};
