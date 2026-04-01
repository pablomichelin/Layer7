const nacl = require('tweetnacl');
const { readSecret } = require('./secret-config');

/**
 * Assina uma string com Ed25519 (tweetnacl).
 * A chave privada e a seed de 32 bytes em hexadecimal (64 chars hex).
 * Retorna a assinatura em hexadecimal (128 chars hex = 64 bytes).
 */
function signData(dataString, privateKeyHex) {
  const seed = Buffer.from(privateKeyHex, 'hex');
  if (seed.length !== 32) {
    throw new Error('ED25519_PRIVATE_KEY deve ter 32 bytes (64 hex chars)');
  }
  const keyPair = nacl.sign.keyPair.fromSeed(seed);
  const message = Buffer.from(dataString, 'utf8');
  const signature = nacl.sign.detached(message, keyPair.secretKey);
  return Buffer.from(signature).toString('hex');
}

/**
 * Gera o payload .lic assinado, compativel com o daemon layer7d.
 * Retorna { data: "<json-string>", sig: "<hex-64-bytes>" }
 */
function generateSignedLicense({ hardware_id, expiry, customer, features }) {
  const privateKeyHex = readSecret('ED25519_PRIVATE_KEY', {
    required: true,
    emptyMessage: 'ED25519_PRIVATE_KEY nao configurada no ambiente',
  });

  const payload = JSON.stringify({
    hardware_id,
    expiry,
    customer,
    features,
    issued: new Date().toISOString().slice(0, 10),
  });

  const sig = signData(payload, privateKeyHex);

  return { data: payload, sig };
}

module.exports = { signData, generateSignedLicense };
