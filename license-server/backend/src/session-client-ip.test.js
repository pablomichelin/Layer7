const http = require('node:http');
const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { getClientIp } = require('./session');

function limiterKey(req) {
  return getClientIp(req) || 'unknown';
}

function probeExpressIp(headers) {
  return new Promise((resolve, reject) => {
    const app = express();
    app.set('trust proxy', 1);
    app.get('/ip', (req, res) => {
      res.json({ ip: getClientIp(req) });
    });

    const server = app.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      const req = http.request({
        host: '127.0.0.1',
        port,
        path: '/ip',
        headers,
      }, (res) => {
        let body = '';
        res.on('data', (chunk) => {
          body += chunk;
        });
        res.on('end', () => {
          server.close();
          resolve(JSON.parse(body).ip);
        });
      });
      req.on('error', (error) => {
        server.close();
        reject(error);
      });
      req.end();
    });
  });
}

test('getClientIp ignores client X-Forwarded-For when req.ip is the trusted hop', () => {
  const trusted = {
    headers: { 'x-forwarded-for': '198.51.100.1, 203.0.113.10' },
    ip: '203.0.113.10',
    socket: { remoteAddress: '172.18.0.4' },
  };
  const spoofed = {
    headers: { 'x-forwarded-for': '8.8.8.8' },
    ip: '203.0.113.10',
    socket: { remoteAddress: '172.18.0.4' },
  };

  assert.equal(getClientIp(trusted), '203.0.113.10');
  assert.equal(getClientIp(spoofed), '203.0.113.10');
  assert.equal(limiterKey(trusted), limiterKey(spoofed));
});

test('getClientIp never takes the leftmost X-Forwarded-For hop', () => {
  assert.equal(getClientIp({
    headers: { 'x-forwarded-for': '9.9.9.9' },
  }), null);
  assert.equal(getClientIp({
    headers: { 'x-forwarded-for': '9.9.9.9, 203.0.113.10' },
    socket: { remoteAddress: '172.18.0.4' },
  }), '172.18.0.4');
});

test('getClientIp falls back to the socket when Express did not set req.ip', () => {
  assert.equal(getClientIp({
    headers: {},
    socket: { remoteAddress: '10.0.0.20' },
  }), '10.0.0.20');
});

test('getClientIp with trust proxy 1 uses the nginx-replaced hop, not a client spoof', async () => {
  const replaced = await probeExpressIp({
    'X-Forwarded-For': '172.18.0.1',
  });
  const appendedSpoof = await probeExpressIp({
    'X-Forwarded-For': '9.9.9.9, 172.18.0.1',
  });

  assert.equal(replaced, '172.18.0.1');
  assert.equal(appendedSpoof, '172.18.0.1');
  assert.equal(replaced, appendedSpoof);
});
