import test from 'node:test';
import assert from 'node:assert/strict';
import {
  AUTH_LOGIN_ERROR_MESSAGE,
  AUTH_SESSION_EXPIRED_MESSAGE,
  AUTH_SESSION_INVALID_MESSAGE,
  AUTH_SESSION_VALIDATING_MESSAGE,
} from './auth-messages.js';

test('auth messages expose the canonical session strings', () => {
  assert.equal(AUTH_SESSION_EXPIRED_MESSAGE, 'Sessao expirada');
  assert.equal(AUTH_SESSION_INVALID_MESSAGE, 'Sessao administrativa inconsistente.');
  assert.equal(AUTH_SESSION_VALIDATING_MESSAGE, 'Validando sessão...');
  assert.equal(AUTH_LOGIN_ERROR_MESSAGE, 'Erro ao fazer login');
});
