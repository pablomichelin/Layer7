const fs = require('fs');

function readSecret(name, options = {}) {
  const {
    required = false,
    trim = true,
    emptyMessage = `${name} nao configurada`,
  } = options;

  const directValue = process.env[name];
  const filePath = process.env[`${name}_FILE`];

  if (directValue && filePath) {
    throw new Error(`${name} e ${name}_FILE nao podem ser usados ao mesmo tempo`);
  }

  let value = directValue;

  if (!value && filePath) {
    value = fs.readFileSync(filePath, 'utf8');
  }

  if (typeof value === 'string' && trim) {
    value = value.trim();
  }

  if (required && !value) {
    throw new Error(emptyMessage);
  }

  return value || '';
}

module.exports = {
  readSecret,
};
