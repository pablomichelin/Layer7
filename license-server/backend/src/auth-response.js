function buildAdminAuthResponse(session, createBearerSessionToken) {
  const responsePayload = {
    admin: session.metadata.admin,
    session: session.metadata.session,
  };

  const bearerToken = createBearerSessionToken(session);
  if (bearerToken) {
    responsePayload.token = bearerToken;
    responsePayload.token_type = 'Bearer';
  }

  return responsePayload;
}

module.exports = {
  buildAdminAuthResponse,
};
