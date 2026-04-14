function buildAuthErrorResponse(error) {
  return { error };
}

function buildLogoutSuccessResponse() {
  return { message: 'Sessao encerrada' };
}

module.exports = {
  buildAuthErrorResponse,
  buildLogoutSuccessResponse,
};
