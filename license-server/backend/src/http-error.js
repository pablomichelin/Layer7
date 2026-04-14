class HttpError extends Error {
  constructor(status, message) {
    super(message);
    this.name = 'HttpError';
    this.status = status;
  }
}

function createHttpError(status, message) {
  return new HttpError(status, message);
}

function isHttpError(error) {
  return error instanceof HttpError;
}

module.exports = {
  HttpError,
  createHttpError,
  isHttpError,
};
