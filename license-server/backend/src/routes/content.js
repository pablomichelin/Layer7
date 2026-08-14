const path = require('path');
const fs = require('fs');
const express = require('express');
const { contentAuthMiddleware } = require('../content-auth');

const router = express.Router();

/** Ficheiros permitidos sob /layer7/blacklists/ut1/current/ */
const ALLOWED_FILES = new Set([
  'layer7-blacklists-manifest.v1.txt',
  'layer7-blacklists-manifest.v1.txt.sig',
  'layer7-blacklists-ut1.tar.gz',
  'blacklists-signing-public-key.pem',
]);

function contentRootDir() {
  const fromEnv = process.env.CONTENT_BLACKLISTS_DIR;
  if (fromEnv && fromEnv.trim() !== '') {
    return path.resolve(fromEnv.trim());
  }
  return path.resolve(__dirname, '../../content/blacklists/ut1/current');
}

function safeJoinContent(fileName) {
  if (!ALLOWED_FILES.has(fileName)) {
    return null;
  }
  const root = contentRootDir();
  const full = path.resolve(root, fileName);
  if (!full.startsWith(root + path.sep) && full !== root) {
    return null;
  }
  return full;
}

router.get('/layer7/blacklists/ut1/current/:fileName', contentAuthMiddleware, (req, res) => {
  const fileName = path.basename(String(req.params.fileName || ''));
  const full = safeJoinContent(fileName);
  if (!full) {
    res.set('Cache-Control', 'no-store');
    return res.status(404).json({ error: 'not_found' });
  }
  if (!fs.existsSync(full) || !fs.statSync(full).isFile()) {
    res.set('Cache-Control', 'no-store');
    return res.status(503).json({ error: 'content_unavailable' });
  }

  res.set('Cache-Control', 'private, no-store');
  res.set('X-Content-Type-Options', 'nosniff');
  return res.sendFile(full);
});

module.exports = router;
module.exports.contentRootDir = contentRootDir;
module.exports.ALLOWED_FILES = ALLOWED_FILES;
