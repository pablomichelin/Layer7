const test = require('node:test');
const assert = require('node:assert/strict');
const {
  getAdminAccessTokenCandidates,
  getAdminAccessTokenFromSources,
} = require('./auth-access');

test('getAdminAccessTokenFromSources prefers a verified bearer token', () => {
  assert.equal(
    getAdminAccessTokenFromSources({
      bearerSessionToken: 'bearer-session-token',
      sessionToken: 'cookie-session-token',
    }),
    'bearer-session-token'
  );
});

test('getAdminAccessTokenFromSources falls back to the cookie token', () => {
  assert.equal(
    getAdminAccessTokenFromSources({
      bearerSessionToken: null,
      sessionToken: 'cookie-session-token',
    }),
    'cookie-session-token'
  );
});

test('getAdminAccessTokenCandidates returns bearer first and cookie second', () => {
  assert.deepEqual(
    getAdminAccessTokenCandidates({
      bearerSessionToken: 'bearer-session-token',
      sessionToken: 'cookie-session-token',
    }),
    [
      { source: 'bearer', token: 'bearer-session-token' },
      { source: 'cookie', token: 'cookie-session-token' },
    ]
  );
});

test('getAdminAccessTokenCandidates deduplicates identical bearer/cookie tokens', () => {
  assert.deepEqual(
    getAdminAccessTokenCandidates({
      bearerSessionToken: 'same-token',
      sessionToken: 'same-token',
    }),
    [
      { source: 'bearer', token: 'same-token' },
    ]
  );
});

test('getAdminAccessTokenCandidates ignores empty sources', () => {
  assert.deepEqual(
    getAdminAccessTokenCandidates({
      bearerSessionToken: null,
      sessionToken: null,
    }),
    []
  );
});
