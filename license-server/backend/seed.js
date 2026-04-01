process.argv = [process.argv[0], process.argv[1], 'init'];
console.warn('[SEED] Aviso: seed.js permanece apenas por compatibilidade. Use node bootstrap-admin.js init.');
require('./bootstrap-admin');
