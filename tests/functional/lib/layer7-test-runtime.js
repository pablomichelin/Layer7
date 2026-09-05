/**
 * Resolução portátil de jsdom e PHP para testes locais/CI.
 * Não é dependência do produto. Sem caminho fixo de máquina.
 *
 *   LAYER7_JSDOM  — caminho do módulo jsdom (ficheiro ou directório)
 *   LAYER7_PHP    — executável PHP (ex.: php, php-wasm-cli)
 *
 * Ausência = FAIL explícito, nunca PASS silencioso.
 */
"use strict";

const { execFileSync } = require("child_process");

function resolveJsdom() {
	if (process.env.LAYER7_JSDOM) {
		try {
			return require(process.env.LAYER7_JSDOM);
		} catch (e) {
			console.error("FAIL: LAYER7_JSDOM nao carregou: " + process.env.LAYER7_JSDOM);
			console.error(e.message || e);
			process.exit(1);
		}
	}
	try {
		return require("jsdom");
	} catch (e) {
		console.error("FAIL: jsdom ausente. Defina LAYER7_JSDOM (caminho do modulo) ou torne require(\"jsdom\") resolvivel.");
		process.exit(1);
	}
}

function resolvePhp() {
	if (process.env.LAYER7_PHP) {
		return process.env.LAYER7_PHP;
	}
	try {
		execFileSync("php", ["-v"], { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] });
		return "php";
	} catch (e) {
		console.error("FAIL: PHP ausente. Defina LAYER7_PHP (executavel) ou tenha php no PATH.");
		process.exit(1);
	}
}

function runPhp(scriptPath, args) {
	const php = resolvePhp();
	const argv = [scriptPath].concat(args || []);
	try {
		return execFileSync(php, argv, {
			encoding: "utf8",
			env: process.env,
			maxBuffer: 8 * 1024 * 1024,
		});
	} catch (e) {
		console.error("FAIL: PHP nao executou " + scriptPath);
		if (e.stderr) {
			console.error(e.stderr);
		}
		console.error(e.message || e);
		process.exit(1);
	}
}

module.exports = {
	resolveJsdom: resolveJsdom,
	resolvePhp: resolvePhp,
	runPhp: runPhp,
};
