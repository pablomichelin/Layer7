const test = require('node:test');
const assert = require('node:assert/strict');
const {
  buildAuthErrorResponse,
  buildLogoutSuccessResponse,
} = require('./auth-route-response');

test('buildAuthErrorResponse centralizes auth error payloads', () => {
  assert.deepEqual(
    buildAuthErrorResponse('Autenticacao necessaria.'),
    { error: 'Autenticacao necessaria.' }
  );
});

test('buildLogoutSuccessResponse centralizes logout success payloads', () => {
  assert.deepEqual(
    buildLogoutSuccessResponse(),
    { message: 'Sessao encerrada' }
  );
});
