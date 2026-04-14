const test = require('node:test');
const assert = require('node:assert/strict');
const {
  createBearerSessionToken,
  extractBearerTokenFromAuthorizationHeader,
  verifyBearerSessionToken,
} = require('./bearer-session-token');

function createSessionFixture() {
  return {
    admin: {
      id: 7,
      email: 'pablo@systemup.inf.br',
      name: 'Pablo',
    },
    session: {
      id: 11,
      expires_at: new Date('2026-04-13T12:30:00.000Z'),
    },
    sessionToken: 'plain-session-token-123',
  };
}

test('extractBearerTokenFromAuthorizationHeader returns the bearer token', () => {
  assert.equal(
    extractBearerTokenFromAuthorizationHeader('Bearer abc.def.ghi'),
    'abc.def.ghi'
  );
  assert.equal(
    extractBearerTokenFromAuthorizationHeader('bearer plain-session-token'),
    'plain-session-token'
  );
  assert.equal(extractBearerTokenFromAuthorizationHeader('Basic abc123'), null);
  assert.equal(extractBearerTokenFromAuthorizationHeader(null), null);
});

test('createBearerSessionToken is disabled when no secret exists', () => {
  const fixture = createSessionFixture();

  const token = createBearerSessionToken({
    secret: null,
    sessionToken: fixture.sessionToken,
    admin: fixture.admin,
    session: fixture.session,
  });

  assert.equal(token, null);
});

test('verifyBearerSessionToken rejects opaque bearer tokens', () => {
  assert.equal(
    verifyBearerSessionToken('plain-session-token-123', {
      secret: 'super-secret',
    }),
    null
  );
});

test('signed bearer token round-trips to the original session token', () => {
  const fixture = createSessionFixture();
  const token = createBearerSessionToken({
    secret: 'super-secret',
    sessionToken: fixture.sessionToken,
    admin: fixture.admin,
    session: fixture.session,
    issuedAtMs: new Date('2026-04-13T12:00:00.000Z').getTime(),
  });

  const verifiedToken = verifyBearerSessionToken(token, {
    secret: 'super-secret',
    nowMs: new Date('2026-04-13T12:10:00.000Z').getTime(),
  });

  assert.equal(verifiedToken, fixture.sessionToken);
});

test('signed bearer token accepts serialized session expiry for response compatibility', () => {
  const fixture = createSessionFixture();
  const token = createBearerSessionToken({
    secret: 'super-secret',
    sessionToken: fixture.sessionToken,
    admin: fixture.admin,
    session: {
      ...fixture.session,
      expires_at: '2026-04-13T12:30:00.000Z',
    },
    issuedAtMs: new Date('2026-04-13T12:00:00.000Z').getTime(),
  });

  const verifiedToken = verifyBearerSessionToken(token, {
    secret: 'super-secret',
    nowMs: new Date('2026-04-13T12:10:00.000Z').getTime(),
  });

  assert.equal(verifiedToken, fixture.sessionToken);
});

test('verifyBearerSessionToken rejects expired signed tokens', () => {
  const fixture = createSessionFixture();
  const token = createBearerSessionToken({
    secret: 'super-secret',
    sessionToken: fixture.sessionToken,
    admin: fixture.admin,
    session: fixture.session,
    issuedAtMs: new Date('2026-04-13T12:00:00.000Z').getTime(),
  });

  const verifiedToken = verifyBearerSessionToken(token, {
    secret: 'super-secret',
    nowMs: new Date('2026-04-13T12:31:00.000Z').getTime(),
  });

  assert.equal(verifiedToken, null);
});

test('verifyBearerSessionToken rejects tampered signatures', () => {
  const fixture = createSessionFixture();
  const token = createBearerSessionToken({
    secret: 'super-secret',
    sessionToken: fixture.sessionToken,
    admin: fixture.admin,
    session: fixture.session,
    issuedAtMs: new Date('2026-04-13T12:00:00.000Z').getTime(),
  });

  const tamperedToken = `${token.slice(0, -1)}x`;
  const verifiedToken = verifyBearerSessionToken(tamperedToken, {
    secret: 'super-secret',
    nowMs: new Date('2026-04-13T12:10:00.000Z').getTime(),
  });

  assert.equal(verifiedToken, null);
});
