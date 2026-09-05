#!/usr/bin/env node
/**
 * V6b2b — FormData modos bulk/import baseline V6b2b vs candidato.
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_vip_bulk_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");

const { JSDOM } = resolveJsdom();
const renderPhp = path.join(__dirname, "harness-exceptions-view/render-vip-bulk-parity.php");

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function jsdomRoot() {
	if (process.env.LAYER7_JSDOM) {
		const p = process.env.LAYER7_JSDOM;
		if (p.endsWith(".js")) {
			return path.join(p, "..", "..", "..");
		}
		return p;
	}
	return path.dirname(require.resolve("jsdom/package.json"));
}

function setControlledFileList(win, input, files) {
	const root = jsdomRoot();
	const FileListMod = require(path.join(root, "lib/jsdom/living/generated/FileList.js"));
	const utils = require(path.join(root, "lib/jsdom/living/generated/utils.js"));
	const impl = FileListMod.createImpl(win, []);
	files.forEach(function (file) {
		impl.push(utils.implForWrapper(file));
	});
	input.files = utils.wrapperForImpl(impl);
}

function renderPair(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	return JSON.parse(raw);
}

function openDom(html, url) {
	return new JSDOM(html, {
		url: url || "http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_bulk=1",
		runScripts: "outside-only",
	});
}

function entriesEqual(a, b) {
	if (a.length !== b.length) return false;
	for (let i = 0; i < a.length; i++) {
		if (a[i][0] !== b[i][0]) return false;
		const va = a[i][1];
		const vb = b[i][1];
		if (typeof va === "object" && va && va.file) {
			if (!vb || !vb.file) return false;
			if (va.name !== vb.name || va.type !== vb.type || va.size !== vb.size || va.text !== vb.text) {
				return false;
			}
		} else if (va !== vb) {
			return false;
		}
	}
	return true;
}

function entriesPreview(entries) {
	return JSON.stringify(entries.map(function (p) {
		const v = p[1];
		if (v && typeof v === "object" && v.file) {
			return [p[0], { file: v.name, type: v.type, size: v.size, text: v.text.slice(0, 32) }];
		}
		return [p[0], typeof v === "string" && v.length > 48 ? v.slice(0, 48) + "…" : v];
	}));
}

function findForm(doc, submitName) {
	const forms = Array.from(doc.querySelectorAll('form[action*="layer7_exceptions.php#l7-vip-list"]'));
	return forms.find(function (f) {
		return f.querySelector('[name="' + submitName + '"]');
	}) || null;
}

function fileText(win, file) {
	const root = jsdomRoot();
	const utils = require(path.join(root, "lib/jsdom/living/generated/utils.js"));
	const impl = utils.implForWrapper(file);
	if (impl && impl._buffer) {
		return impl._buffer.toString("utf8");
	}
	return "";
}

async function serializeFormPayload(html, submitName, fill, pageUrl) {
	const dom = openDom(html, pageUrl);
	const doc = dom.window.document;
	const win = dom.window;
	const form = findForm(doc, submitName);
	if (!form) {
		return { error: "form ausente: " + submitName };
	}
	if (typeof fill === "function") {
		await fill(form, doc, win);
	}
	const submit = form.querySelector('[name="' + submitName + '"]');
	let fd;
	try {
		fd = new win.FormData(form, submit);
	} catch (e) {
		return { error: "FormData falhou: " + (e && e.message ? e.message : e) };
	}
	const entries = [];
	for (const pair of fd.entries()) {
		const name = pair[0];
		const value = pair[1];
		if (value instanceof win.File) {
			entries.push([name, {
				file: true,
				name: value.name,
				type: value.type || "",
				size: value.size,
				text: fileText(win, value),
			}]);
		} else {
			entries.push([name, String(value)]);
		}
	}
	return {
		method: (form.getAttribute("method") || "get").toLowerCase(),
		action: form.getAttribute("action") || "",
		submitValue: fd.get(submitName),
		submitAttr: submit.getAttribute("value"),
		entries: entries,
	};
}

function assertParity(label, base, cand, submitName) {
	check(!base.error && !cand.error, label + ": FormData extraido");
	if (base.error || cand.error) {
		if (base.error) console.log("  baseline: " + base.error);
		if (cand.error) console.log("  cand: " + cand.error);
		return;
	}
	check(base.method === "post" && cand.method === "post", label + ": method POST");
	check(base.action === cand.action, label + ": action " + base.action);
	check(entriesEqual(base.entries, cand.entries), label + ": entries " + entriesPreview(base.entries));
	check(base.submitValue === "1" && cand.submitValue === "1", label + ": submitter value=1");
}

const vipSchema = {
	layer7: {
		exceptions: [{
			id: "vip-isentos",
			enabled: true,
			priority: 9000,
			action: "allow",
			managed_by: "profiles",
			hosts: ["10.0.0.1"],
		}],
		vip_meta: { labels: { "10.0.0.1": "A" } },
	},
};

const SYNTHETIC_IMPORT = {
	name: "vip-import.txt",
	type: "text/plain",
	content: "10.8.0.1, Import\n192.168.8.0/24, Rede import\n",
};

(async function main() {
	/* bulk modo exclusivo */
	const bulkPair = renderPair({ data: vipSchema, vip_bulk_mode: true });
	const fillBulk = function (form) {
		const ta = form.querySelector('[name="vip_bulk_text"]');
		if (ta) ta.value = "10.1.0.1, Um\n192.0.2.0/24, Rede";
	};
	const baseBulk = await serializeFormPayload(
		bulkPair.baseline,
		"save_vip_bulk",
		fillBulk,
		"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip=1"
	);
	const candBulk = await serializeFormPayload(
		bulkPair.candidate,
		"save_vip_bulk",
		fillBulk,
		"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_bulk=1"
	);
	assertParity("bulk", baseBulk, candBulk, "save_vip_bulk");

	/* bulk vazio */
	const fillEmpty = function (form) {
		const ta = form.querySelector('[name="vip_bulk_text"]');
		if (ta) ta.value = "";
	};
	const emptyPair = renderPair({ data: vipSchema, vip_bulk_mode: true });
	const baseEmpty = await serializeFormPayload(emptyPair.baseline, "save_vip_bulk", fillEmpty);
	const candEmpty = await serializeFormPayload(emptyPair.candidate, "save_vip_bulk", fillEmpty);
	assertParity("bulk vazio", baseEmpty, candEmpty, "save_vip_bulk");

	/* import multipart — FormData/File completo (FileList controlada; form render real) */
	const importPair = renderPair({ data: vipSchema, vip_import_mode: true });
	const fillImport = function (form, doc, win) {
		const input = form.querySelector('[name="vip_import_file"]');
		if (!input) return;
		const file = new win.File([SYNTHETIC_IMPORT.content], SYNTHETIC_IMPORT.name, {
			type: SYNTHETIC_IMPORT.type,
		});
		setControlledFileList(win, input, [file]);
	};
	const baseImport = await serializeFormPayload(
		importPair.baseline,
		"import_vip_list",
		fillImport,
		"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_import=1"
	);
	const candImport = await serializeFormPayload(
		importPair.candidate,
		"import_vip_list",
		fillImport,
		"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_import=1"
	);
	assertParity("import", baseImport, candImport, "import_vip_list");
	if (!baseImport.error && !candImport.error) {
		const fileEntry = baseImport.entries.find(function (p) { return p[0] === "vip_import_file"; });
		check(!!fileEntry && fileEntry[1].file, "import: entrada File no FormData");
		if (fileEntry && fileEntry[1].file) {
			check(fileEntry[1].name === SYNTHETIC_IMPORT.name, "import: nome ficheiro");
			check(fileEntry[1].type === SYNTHETIC_IMPORT.type, "import: tipo ficheiro");
			check(fileEntry[1].size === SYNTHETIC_IMPORT.content.length, "import: tamanho ficheiro");
			check(fileEntry[1].text === SYNTHETIC_IMPORT.content, "import: conteudo ficheiro");
		}
	}

	/* export permanece na consulta */
	const listPair = renderPair({ data: vipSchema });
	const baseExp = await serializeFormPayload(
		listPair.baseline,
		"export_vip_list",
		null,
		"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip=1"
	);
	const candExp = await serializeFormPayload(
		listPair.candidate,
		"export_vip_list",
		null,
		"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip=1"
	);
	assertParity("export", baseExp, candExp, "export_vip_list");

	/* retry raw bulk — DOM candidato */
	const retryPair = renderPair({
		data: vipSchema,
		vip_bulk_mode: true,
		post: {
			save_vip_bulk: "1",
			vip_bulk_text: "raw-line\n",
		},
	});
	const retryDom = openDom(retryPair.candidate);
	const ta = retryDom.window.document.querySelector('[name="vip_bulk_text"]');
	check(ta && ta.value === "raw-line\n", "bulk retry: textarea raw retido");

	if (fail) {
		console.error("SOME EXCEPTIONS VIP BULK PAYLOAD TESTS FAILED");
		process.exit(1);
	}
	console.log("ALL EXCEPTIONS VIP BULK PAYLOAD TESTS PASSED");
	process.exit(0);
})().catch(function (e) {
	console.error("FAIL: excepcao payload bulk", e && e.message ? e.message : e);
	process.exit(1);
});
