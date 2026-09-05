#!/usr/bin/env node
/**
 * BG-174 / V4-B1 Policies — biblioteca cumulativa: navegação, details, rascunho,
 * filtro (oninput/onchange), apply/fetch/fallback, hidden activo, forms parity.
 * Scripts do HTML renderizado; vm+fachada location para bookmark; onclick real.
 */
"use strict";

const vm = require("vm");
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM, VirtualConsole } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-policies-library/render-fixture.php");
const navPhp = path.join(__dirname, "harness-policies-library/render-nav-fixture.php");
const hiddenPhp = path.join(__dirname, "harness-policies-library/render-hidden-active.php");
const formsParityPhp = path.join(__dirname, "harness-policies-library/forms-parity-fixture.php");

let fail = 0;
let jsdomNavKnown = 0;
const jsdomUnexpected = [];

function isKnownJsdomNavError(err) {
	const msg = err && (err.message || String(err));
	return msg.indexOf("Not implemented: navigation (except hash changes)") >= 0;
}

function makeVirtualConsole() {
	const vc = new VirtualConsole();
	vc.on("jsdomError", function (err) {
		if (isKnownJsdomNavError(err)) {
			jsdomNavKnown++;
			return;
		}
		jsdomUnexpected.push(err);
		console.error(err);
	});
	vc.on("error", function (msg) {
		const text = typeof msg === "string" ? msg : String(msg);
		if (text.indexOf("Not implemented: navigation (except hash changes)") >= 0) {
			jsdomNavKnown++;
			return;
		}
		console.error(msg);
	});
	return vc;
}

function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function extractScriptBlocks(html) {
	return [...html.matchAll(/<script>\s*([\s\S]*?)\s*<\/script>/g)].map(function (m) {
		return m[1];
	});
}

function stripScripts(html) {
	return html.replace(/<script>[\s\S]*?<\/script>/g, "<!-- L7PL_SCRIPT_STRIPPED -->");
}

function loadLibraryScripts(win, html) {
	extractScriptBlocks(html).forEach(function (block) {
		if (block.indexOf("l7ProfileEditData") >= 0) {
			win.eval(block);
		}
		if (block.indexOf("function l7toggleProfileDraft") >= 0 ||
			block.indexOf("function l7initProfileGroups") >= 0) {
			win.eval(block);
		}
	});
}

function invokeAttr(win, el, attrName) {
	const code = el.getAttribute(attrName);
	if (!code) {
		return false;
	}
	const fn = new win.Function("event", code);
	fn.call(el, { target: el, currentTarget: el, preventDefault: function () {} });
	return true;
}

function visibleCards(document) {
	return Array.prototype.filter.call(
		document.querySelectorAll(".l7-profile-card"),
		function (tr) {
			return !tr.hidden;
		}
	);
}

function fdEntries(fd) {
	const out = {};
	if (!fd || typeof fd.forEach !== "function") {
		return out;
	}
	fd.forEach(function (val, key) {
		out[key] = val;
	});
	return out;
}

function openFixture(html, pageUrl, setupWin) {
	const dom = new JSDOM(stripScripts(html), {
		url: pageUrl || "http://127.0.0.1/packages/layer7/layer7_policies.php?library=1",
		runScripts: "outside-only",
		pretendToBeVisual: false,
		virtualConsole: makeVirtualConsole(),
	});
	const win = dom.window;
	if (!win.Element.prototype.scrollIntoView) {
		win.Element.prototype.scrollIntoView = function () {};
	}
	if (typeof setupWin === "function") {
		setupWin(win, dom);
	}
	loadLibraryScripts(win, html);
	return { win: win, document: win.document, dom: dom };
}

function captureLegacyRedirect(html, pageUrl) {
	const dom = new JSDOM(stripScripts(html), {
		url: pageUrl,
		runScripts: "outside-only",
		virtualConsole: makeVirtualConsole(),
	});
	const doc = dom.window.document;
	if (!doc.defaultView.Element.prototype.scrollIntoView) {
		doc.defaultView.Element.prototype.scrollIntoView = function () {};
	}
	let replaceUrl = null;
	const pageHash = pageUrl.indexOf("#") >= 0 ? pageUrl.slice(pageUrl.indexOf("#")) : "";
	const loc = {
		hash: pageHash,
		href: pageUrl,
		replace: function (u) {
			replaceUrl = String(u);
		},
	};
	const sandbox = {
		window: {
			location: loc,
			addEventListener: function () {},
			removeEventListener: function () {},
		},
		document: doc,
		console: console,
		alert: function () {},
		confirm: function () {
			return true;
		},
		addEventListener: function () {},
		removeEventListener: function () {},
		setTimeout: dom.window.setTimeout.bind(dom.window),
		clearTimeout: dom.window.clearTimeout.bind(dom.window),
	};
	sandbox.self = sandbox;
	sandbox.globalThis = sandbox;
	extractScriptBlocks(html)
		.filter(function (b) {
			return b.indexOf("l7LegacyLibraryRedirectOk") >= 0 || b.indexOf("l7initProfileGroups") >= 0;
		})
		.forEach(function (block) {
			vm.runInNewContext(block, sandbox, { filename: "rendered-library.js" });
		});
	if (typeof sandbox.l7initProfileGroups === "function") {
		sandbox.l7initProfileGroups();
	}
	dom.window.close();
	return replaceUrl;
}

function flushPromises() {
	return new Promise(function (resolve) {
		setImmediate(resolve);
	});
}

function assertFetchPayload(call, expected) {
	check(call.url === "layer7_policies.php", "apply: endpoint exacto layer7_policies.php");
	check(call.opts.method === "POST", "apply: metodo POST");
	check(call.opts.credentials === "same-origin", "apply: credentials same-origin");
	check(call.opts.headers["X-Requested-With"] === "XMLHttpRequest", "apply: header X-Requested-With");
	const e = fdEntries(call.opts.body);
	const keys = Object.keys(e).sort();
	const wantKeys = Object.keys(expected).sort();
	check(keys.join("|") === wantKeys.join("|"), "apply: conjunto de chaves FormData");
	Object.keys(expected).forEach(function (k) {
		check(e[k] === expected[k], "apply: campo " + k + "=" + expected[k]);
	});
}

function makeApplyCtx(html, opts) {
	opts = opts || {};
	const ctx = openFixture(html, "http://127.0.0.1/packages/layer7/layer7_policies.php?library=1", function (win) {
		win.fetchCalls = [];
		win.fetchImpl = opts.fetchImpl || function () {
			return Promise.resolve({
				ok: true,
				json: function () {
					return Promise.resolve({ ok: true });
				},
			});
		};
		win.fetch = function (url, fetchOpts) {
			win.fetchCalls.push({ url: url, opts: fetchOpts });
			return win.fetchImpl(url, fetchOpts);
		};
		win.confirmImpl = opts.confirmImpl !== undefined ? opts.confirmImpl : function () {
			return true;
		};
		win.confirm = function () {
			return win.confirmImpl();
		};
		win.alerts = [];
		win.alert = function (msg) {
			win.alerts.push(String(msg));
		};
		win.submittedForms = [];
		const FormProto = win.HTMLFormElement.prototype;
		const origSubmit = FormProto.submit;
		FormProto.submit = function () {
			const snap = {
				method: this.method,
				action: this.action,
				fields: {},
			};
			this.querySelectorAll("input").forEach(function (inp) {
				if (inp.name) {
					snap.fields[inp.name] = inp.value;
				}
			});
			win.submittedForms.push(snap);
			if (opts.onFormSubmit) {
				opts.onFormSubmit(snap, this);
			}
		};
		win._restoreFormSubmit = function () {
			FormProto.submit = origSubmit;
		};
	});
	const csrf = ctx.document.createElement("input");
	csrf.type = "hidden";
	csrf.name = "__csrf_magic";
	csrf.value = "test-csrf-token";
	ctx.document.body.appendChild(csrf);
	return ctx;
}

function btnByProfileId(document, profileId) {
	const buttons = document.querySelectorAll("button[data-profile-id]");
	for (let i = 0; i < buttons.length; i++) {
		if (buttons[i].getAttribute("data-profile-id") === profileId) {
			return buttons[i];
		}
	}
	return null;
}

function firstOffToggle(document) {
	const buttons = document.querySelectorAll('button[data-profile-id][data-saved="0"]');
	for (let i = 0; i < buttons.length; i++) {
		if (buttons[i].getAttribute("onclick")) {
			return buttons[i];
		}
	}
	return null;
}

function firstOnToggle(document) {
	const buttons = document.querySelectorAll('button[data-profile-id][data-saved="1"]');
	for (let i = 0; i < buttons.length; i++) {
		if (buttons[i].getAttribute("onclick")) {
			return buttons[i];
		}
	}
	return null;
}

function firstToggleBtn(document) {
	return document.querySelector('button[data-profile-id][onclick*="l7toggleProfileDraft"]');
}

(async function () {
	const libraryHtml = runPhp(renderPhp, ["library"]);
	const listHtml = runPhp(renderPhp, ["list"]);
	const listPostHtml = runPhp(navPhp, ["post-error"]);
	const hiddenHtml = runPhp(hiddenPhp, []);
	const formsCount = parseInt(runPhp(formsParityPhp, []), 10);

	check(libraryHtml.includes('id="l7ProfileSearch"'), "library HTML renderizado");
	check(hiddenHtml.includes("c-hidden-active"), "hidden-active HTML renderizado");
	check(listHtml.includes("l7LegacyLibraryRedirectOk = true"), "flags: list redirect true");
	check(listPostHtml.includes("l7LegacyLibraryRedirectOk = false"), "flags: POST erro redirect false");
	check(libraryHtml.includes("l7LegacyLibraryRedirectOk = false"), "flags: library redirect false");
	check(formsCount === 105, "forms: 105 toggle/unhide payload integral (" + formsCount + ")");

	/* Navegação bookmark GET */
	const navProfiles = captureLegacyRedirect(
		listHtml,
		"http://127.0.0.1/packages/layer7/layer7_policies.php#l7-profiles"
	);
	check(navProfiles === "layer7_policies.php?library=1#l7-profiles", "nav: GET #l7-profiles (vm)");

	const navRa = captureLegacyRedirect(
		listHtml,
		"http://127.0.0.1/packages/layer7/layer7_policies.php#l7-ra"
	);
	check(navRa === "layer7_policies.php?library=1#l7-ra", "nav: GET #l7-ra (vm)");

	const navPost = captureLegacyRedirect(
		listPostHtml,
		"http://127.0.0.1/packages/layer7/layer7_policies.php#l7-ra"
	);
	check(navPost === null, "nav: POST erro nao redireciona com hash");
	check(listPostHtml.includes("Erro simulado na lista"), "nav: POST erro preserva mensagem");

	const lib = openFixture(
		libraryHtml,
		"http://127.0.0.1/packages/layer7/layer7_policies.php?library=1"
	);

	/* details / summary nativos */
	const details = lib.document.querySelectorAll("details.l7-profile-group");
	check(details.length > 0, "details: grupos presentes");
	check(!details[0].open, "details: fechado inicialmente");
	details[0].open = true;
	check(details[0].open, "details: abre sem JS");
	const summary = details[0].querySelector("summary");
	check(!!summary, "details: summary presente");
	check(!summary.getAttribute("role"), "details: summary sem role button");
	check(!summary.getAttribute("onclick"), "details: summary sem onclick duplo");
	check(!summary.getAttribute("tabindex"), "details: summary sem tabindex");
	const wasOpen = details[0].open;
	summary.click();
	check(details[0].open !== wasOpen, "details: summary.click alterna open");
	summary.click();
	check(details[0].open === wasOpen, "details: summary.click reverte");

	const expandBtn = lib.document.querySelector('button[onclick*="l7setAllProfileGroups(true)"]');
	const collapseBtn = lib.document.querySelector('button[onclick*="l7setAllProfileGroups(false)"]');
	check(!!expandBtn && !!collapseBtn, "details: botoes expandir/recolher presentes");
	check(invokeAttr(lib.win, expandBtn, "onclick"), "details: onclick expandir tudo");
	check(Array.prototype.every.call(details, function (d) {
		return d.open;
	}), "details: expandir tudo via onclick");
	check(invokeAttr(lib.win, collapseBtn, "onclick"), "details: onclick recolher tudo");
	check(Array.prototype.every.call(details, function (d) {
		return !d.open;
	}), "details: recolher tudo via onclick");

	/* #l7-ra abre grupo remoto na biblioteca */
	const ra = lib.document.getElementById("l7-ra");
	check(!!ra && ra.tagName === "DETAILS", "ra: grupo details com id l7-ra");
	lib.dom.window.location.hash = "#l7-ra";
	lib.win.l7initProfileGroups();
	check(ra.open, "ra: hash abre grupo remoto");

	/* filtro via oninput/onchange */
	const search = lib.document.getElementById("l7ProfileSearch");
	const activeOnly = lib.document.getElementById("l7ProfileActiveOnly");
	check(!!search && !!activeOnly, "filtro: controlos presentes");
	const totalBefore = visibleCards(lib.document).length;
	search.value = "zzznomatch999";
	check(invokeAttr(lib.win, search, "oninput"), "filtro: oninput presente");
	check(visibleCards(lib.document).length === 0, "filtro: oninput busca sem resultado");
	search.value = "";
	invokeAttr(lib.win, search, "oninput");
	check(visibleCards(lib.document).length === totalBefore, "filtro: oninput limpar");
	activeOnly.checked = true;
	check(invokeAttr(lib.win, activeOnly, "onchange"), "filtro: onchange presente");
	invokeAttr(lib.win, activeOnly, "onchange");
	check(visibleCards(lib.document).length < totalBefore, "filtro: onchange so ligados");
	activeOnly.checked = false;
	invokeAttr(lib.win, activeOnly, "onchange");

	/* rascunho: toggle, barra, contadores global/grupo, descarte */
	const btn = firstToggleBtn(lib.document);
	check(!!btn, "rascunho: botao toggle presente");
	const saved = btn.getAttribute("data-saved");
	const draftMsg = lib.document.getElementById("l7ProfileDraftMsg");
	const draftBar = lib.document.getElementById("l7ProfileDraftBar");
	const groupEl = btn.closest("details.l7-profile-group") || btn.closest(".l7-profile-group");
	const groupBadge = groupEl ? groupEl.querySelector(".l7-profile-group-pending-badge") : null;
	invokeAttr(lib.win, btn, "onclick");
	check(btn.getAttribute("data-desired") !== saved, "rascunho: toggle altera desired");
	check(draftBar && !draftBar.hidden, "rascunho: barra visivel");
	check(draftMsg && draftMsg.textContent.length > 0, "rascunho: contador global pendente");
	check(groupBadge && !groupBadge.hidden, "rascunho: badge grupo pendente visivel");
	invokeAttr(lib.win, lib.document.getElementById("l7ProfileDraftDiscard"), "onclick");
	check(draftBar.hidden, "rascunho: descartar esconde barra");
	check(btn.getAttribute("data-desired") === saved, "rascunho: descartar restaura estado");
	check(!groupBadge || groupBadge.hidden, "rascunho: badge grupo oculto apos descarte");

	/* apply enable — endpoint e chaves exactas */
	const enableCtx = makeApplyCtx(libraryHtml);
	const offBtn = firstOffToggle(enableCtx.document);
	check(!!offBtn, "apply-enable: botao saved=0");
	const enableId = offBtn.getAttribute("data-profile-id");
	invokeAttr(enableCtx.win, offBtn, "onclick");
	invokeAttr(enableCtx.win, enableCtx.document.getElementById("l7ProfileDraftApply"), "onclick");
	await flushPromises();
	check(enableCtx.win.fetchCalls.length === 1, "apply-enable: uma chamada fetch");
	if (enableCtx.win.fetchCalls.length === 1) {
		assertFetchPayload(enableCtx.win.fetchCalls[0], {
			apply_profiles_batch: "1",
			ajax: "1",
			enable_ids: enableId,
			disable_ids: "",
			__csrf_magic: "test-csrf-token",
		});
	}
	enableCtx.dom.window.close();

	/* desligar activo — cancelar confirmacao */
	const cancelCtx = makeApplyCtx(libraryHtml, {
		confirmImpl: function () {
			return false;
		},
	});
	const onBtn = firstOnToggle(cancelCtx.document);
	check(!!onBtn, "apply-cancel: botao saved=1");
	const offId = onBtn.getAttribute("data-profile-id");
	invokeAttr(cancelCtx.win, onBtn, "onclick");
	invokeAttr(cancelCtx.win, cancelCtx.document.getElementById("l7ProfileDraftApply"), "onclick");
	await flushPromises();
	check(cancelCtx.win.fetchCalls.length === 0, "apply-cancel: zero fetch");
	check(onBtn.getAttribute("data-desired") === "0", "apply-cancel: rascunho preservado");
	check(!cancelCtx.document.getElementById("l7ProfileDraftBar").hidden, "apply-cancel: barra visivel");
	cancelCtx.dom.window.close();

	/* desligar activo — confirmar */
	const disableCtx = makeApplyCtx(libraryHtml, { confirmImpl: function () { return true; } });
	const onBtn2 = firstOnToggle(disableCtx.document);
	invokeAttr(disableCtx.win, onBtn2, "onclick");
	invokeAttr(disableCtx.win, disableCtx.document.getElementById("l7ProfileDraftApply"), "onclick");
	await flushPromises();
	check(disableCtx.win.fetchCalls.length === 1, "apply-disable: uma chamada");
	if (disableCtx.win.fetchCalls.length === 1) {
		assertFetchPayload(disableCtx.win.fetchCalls[0], {
			apply_profiles_batch: "1",
			ajax: "1",
			enable_ids: "",
			disable_ids: offId,
			__csrf_magic: "test-csrf-token",
		});
	}
	disableCtx.dom.window.close();

	/* busy — segunda aplicacao ignorada */
	const busyCtx = makeApplyCtx(libraryHtml, {
		fetchImpl: function () {
			return new Promise(function () {});
		},
	});
	const offBtnB = firstOffToggle(busyCtx.document);
	invokeAttr(busyCtx.win, offBtnB, "onclick");
	const applyBtnB = busyCtx.document.getElementById("l7ProfileDraftApply");
	invokeAttr(busyCtx.win, applyBtnB, "onclick");
	invokeAttr(busyCtx.win, applyBtnB, "onclick");
	await flushPromises();
	check(busyCtx.win.fetchCalls.length === 1, "apply-busy: uma chamada somente");
	check(busyCtx.win.l7ProfileDraftBusy === true, "apply-busy: flag busy activa");
	busyCtx.dom.window.close();

	/* fetch rejeitado — fallback form.submit stubado */
	const fallbackCtx = makeApplyCtx(libraryHtml, {
		fetchImpl: function () {
			return Promise.reject(new Error("network"));
		},
	});
	const offBtnF = firstOffToggle(fallbackCtx.document);
	const enableF = offBtnF.getAttribute("data-profile-id");
	invokeAttr(fallbackCtx.win, offBtnF, "onclick");
	invokeAttr(fallbackCtx.win, fallbackCtx.document.getElementById("l7ProfileDraftApply"), "onclick");
	await flushPromises();
	await flushPromises();
	check(fallbackCtx.win.submittedForms.length === 1, "fallback: form.submit capturado");
	if (fallbackCtx.win.submittedForms.length === 1) {
		const sf = fallbackCtx.win.submittedForms[0];
		check(sf.method === "post", "fallback: method post");
		check(sf.action.indexOf("layer7_policies.php#l7-policies") >= 0, "fallback: action #l7-policies");
		check(sf.fields.apply_profiles_batch === "1", "fallback: apply_profiles_batch");
		check(sf.fields.enable_ids === enableF, "fallback: enable_ids exacto");
		check(sf.fields.disable_ids === "", "fallback: disable_ids vazio");
		check(sf.fields.__csrf_magic === "test-csrf-token", "fallback: CSRF");
	}
	fallbackCtx.dom.window.close();

	/* HTTP/JSON erro — busy liberado, botao reabilitado, alerta, rascunho */
	const errCtx = makeApplyCtx(libraryHtml, {
		fetchImpl: function () {
			return Promise.resolve({
				ok: false,
				json: function () {
					return Promise.resolve({ ok: false, msg: "erro harness" });
				},
			});
		},
	});
	const offBtnE = firstOffToggle(errCtx.document);
	invokeAttr(errCtx.win, offBtnE, "onclick");
	const applyBtnE = errCtx.document.getElementById("l7ProfileDraftApply");
	invokeAttr(errCtx.win, applyBtnE, "onclick");
	await flushPromises();
	check(errCtx.win.l7ProfileDraftBusy === false, "apply-erro: busy liberado");
	check(applyBtnE.disabled === false, "apply-erro: botao reabilitado");
	check(errCtx.win.alerts.indexOf("erro harness") >= 0, "apply-erro: alert apresentado");
	check(!errCtx.document.getElementById("l7ProfileDraftBar").hidden, "apply-erro: rascunho preservado");
	errCtx.dom.window.close();

	/* hidden activo */
	const hid = openFixture(hiddenHtml);
	const hiddenBtn = btnByProfileId(hid.document, "c-hidden-active");
	check(!!hiddenBtn, "hidden-active: botao perfil oculto");
	check(hiddenBtn.getAttribute("data-saved") === "1", "hidden-active: data-saved=1");
	check(hiddenBtn.closest(".l7-profile-card").getAttribute("data-profile-active") === "1",
		"hidden-active: data-profile-active=1");
	const hiddenRow = hiddenBtn.closest(".l7-profile-card");
	check(!!hiddenRow, "hidden-active: linha perfil oculto");
	const unhideForm = hiddenRow.querySelector('form button[name="unhide_profile"]')?.closest("form");
	check(!!unhideForm, "hidden-active: form unhide");
	check(unhideForm.getAttribute("action") === "layer7_policies.php#l7-profiles", "hidden-active: action unhide");
	const unhideBtn = unhideForm.querySelector('button[name="unhide_profile"]');
	check(!!unhideBtn, "hidden-active: botao unhide");
	const unhidePid = unhideForm.querySelector('input[name="profile_id"]');
	check(unhidePid && unhidePid.value === "c-hidden-active", "hidden-active: unhide profile_id");
	invokeAttr(hid.win, hiddenBtn, "onclick");
	const desiredBeforeDiscard = hiddenBtn.getAttribute("data-desired");
	invokeAttr(hid.win, hid.document.getElementById("l7ProfileDraftDiscard"), "onclick");
	check(hiddenBtn.getAttribute("data-desired") === hiddenBtn.getAttribute("data-saved"),
		"hidden-active: descarte restaura saved");
	check(hiddenBtn.getAttribute("data-desired") !== desiredBeforeDiscard || desiredBeforeDiscard === "1",
		"hidden-active: descarte nao altera saved");
	const hidActiveOnly = hid.document.getElementById("l7ProfileActiveOnly");
	hidActiveOnly.checked = true;
	invokeAttr(hid.win, hidActiveOnly, "onchange");
	check(!!hiddenRow && !hiddenRow.hidden, "hidden-active: card oculto activo visivel com so ligados");
	check(hid.document.body.innerHTML.indexOf("editado") >= 0 || hid.document.body.innerHTML.indexOf("label-warning") >= 0,
		"hidden-active: factory override facebook");
	hid.dom.window.close();

	lib.dom.window.close();

	check(jsdomUnexpected.length === 0,
		"jsdom: sem erros inesperados (" + jsdomUnexpected.length + ")");
	if (jsdomNavKnown > 0) {
		console.log("NOTE: jsdom navigation href nao executada (limite conhecido, " +
			jsdomNavKnown + " ocorrencia(s)) — nao e falha do produto");
	}

	console.log("Runtime: scripts HTML renderizado; vm nav; onclick/oninput/onchange via Function");
	console.log("Limites: nao e browser, nao e pfSense, sem layout/tema/visual/CSRF/appliance");

	if (fail) {
		console.error("SOME POLICIES LIBRARY JS TESTS FAILED");
		process.exit(1);
	}
	console.log("ALL POLICIES LIBRARY JS TESTS PASSED");
	process.exit(0);
})();
