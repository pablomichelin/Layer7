const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const NGINX_CONF = path.join(__dirname, '../../nginx/nginx.conf');

function loadNginxConf() {
  return fs.readFileSync(NGINX_CONF, 'utf8');
}

test('origin nginx replaces X-Forwarded-For with $remote_addr and never appends client XFF', () => {
  const conf = loadNginxConf();
  const xffLines = conf
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.startsWith('proxy_set_header X-Forwarded-For'));

  assert.ok(xffLines.length >= 3, 'expected X-Forwarded-For on all proxied locations');
  for (const line of xffLines) {
    assert.equal(line, 'proxy_set_header X-Forwarded-For $remote_addr;');
    assert.equal(line.includes('$proxy_add_x_forwarded_for'), false);
  }
  const activeDirectives = conf
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'));
  assert.equal(
    activeDirectives.some((line) => line.includes('$proxy_add_x_forwarded_for')),
    false
  );
});

test('P2-3 X-Forwarded-Proto map stays unchanged in this block', () => {
  const conf = loadNginxConf();
  assert.match(conf, /map \$http_x_forwarded_proto \$layer7_forwarded_proto/);
  assert.match(conf, /proxy_set_header X-Forwarded-Proto \$layer7_forwarded_proto;/);
  const protoLines = conf
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.startsWith('proxy_set_header X-Forwarded-Proto'));
  assert.ok(protoLines.length >= 3);
  for (const line of protoLines) {
    assert.equal(line, 'proxy_set_header X-Forwarded-Proto $layer7_forwarded_proto;');
  }
});

test('origin nginx still publishes X-Real-IP from the TCP peer', () => {
  const conf = loadNginxConf();
  const realIpLines = conf
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.startsWith('proxy_set_header X-Real-IP'));
  assert.ok(realIpLines.length >= 3);
  for (const line of realIpLines) {
    assert.equal(line, 'proxy_set_header X-Real-IP $remote_addr;');
  }
});
