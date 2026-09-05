#!/usr/bin/env node
/**
 * V4-B2b — Editor/criação: baseline via JS original, confirmações obrigatórias,
 * POST erro vs POST esperado, onclick/onsubmit reais.
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM, VirtualConsole } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-policies-edit/render-fixture.php");
const baselinePhp = path.join(__dirname, "harness-policies-edit/render-baseline-fixture.php");

const HOSTILE_HOSTS = "good.example\n\"></textarea><script>alert(1)</script>";

let fail = 0;
const jsdomUnexpected = [];

function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function isEditDomHidden(el) {
	return !el || el.hasAttribute("hidden") || el.classList.contains("hidden");
}

function extractScriptBlocks(html) {
	return [...html.matchAll(/<script>\s*([\s\S]*?)\s*<\/script>/g)].map(function (m) {
		return m[1];
	});
}

function editForm(document) {
	const modal = document.getElementById("l7ProfileEditModal");
	if (modal) {
		const f = modal.querySelector("form");
		if (f) {
			return f;
		}
	}
	const page = document.getElementById("l7-profile-edit");
	if (page) {
		return page.querySelector("form");
	}
	return null;
}

function fdSnapshot(form, submitter) {
	const win = form.ownerDocument.defaultView;
	const fd = new win.FormData(form, submitter || undefined);
	const out = {};
	fd.forEach(function (val, key) {
		if (out[key] === undefined) {
			out[key] = val;
		} else if (Array.isArray(out[key])) {
			out[key].push(val);
		} else {
			out[key] = [out[key], val];
		}
	});
	const keys = Object.keys(out).sort();
	const norm = {};
	keys.forEach(function (k) {
		norm[k] = out[k];
	});
	return { keys: keys, entries: norm };
}

function compareFormData(label, baseSnap, candSnap) {
	check(baseSnap.keys.join("|") === candSnap.keys.join("|"),
		label + ": mesmas chaves FormData");
	baseSnap.keys.forEach(function (k) {
		const b = baseSnap.entries[k];
		const c = candSnap.entries[k];
		const bs = Array.isArray(b) ? b.slice().sort().join("\0") : String(b);
		const cs = Array.isArray(c) ? c.slice().sort().join("\0") : String(c);
		check(bs === cs, label + ": valor " + k);
	});
}

function makeBootstrapStub(win) {
	win.jQuery = function (sel) {
		const el = typeof sel === "string" ? win.document.querySelector(sel) : sel;
		return {
			modal: function (action) {
				if (!el) {
					return;
				}
				if (action === "show") {
					el.classList.add("in");
					el.style.display = "block";
					win.document.body.classList.add("modal-open");
				} else if (action === "hide") {
					el.classList.remove("in");
					el.style.display = "none";
					win.document.body.classList.remove("modal-open");
				}
			},
		};
	};
	win.jQuery.fn = { modal: true };
}

function loadEditScripts(win, html) {
	extractScriptBlocks(html).forEach(function (block) {
		if (block.indexOf("l7ProfileEditData") >= 0 ||
			block.indexOf("function l7showProfileEditModal") >= 0 ||
			block.indexOf("function l7hideProfileEditModal") >= 0 ||
			block.indexOf("function l7profileEditSetVisible") >= 0 ||
			block.indexOf("function l7confirmProfileEditSave") >= 0 ||
			block.indexOf("function l7filter") >= 0 ||
			block.indexOf("function l7clearEditFilter") >= 0 ||
			block.indexOf("function l7toggleProfileDraft") >= 0 ||
			block.indexOf("function l7filterProfileGrid") >= 0 ||
			block.indexOf("function l7collectProfileDraft") >= 0 ||
			block.indexOf("function l7updateProfileDraftBar") >= 0) {
			win.eval(block);
		}
	});
}

function openDom(html, pageUrl, setupWin, withBootstrap) {
	const vc = new VirtualConsole();
	vc.on("jsdomError", function (err) {
		jsdomUnexpected.push(err);
	});
	const dom = new JSDOM(html, {
		url: pageUrl || "http://127.0.0.1/packages/layer7/layer7_policies.php?library=1",
		runScripts: "outside-only",
		pretendToBeVisual: false,
		virtualConsole: vc,
	});
	if (withBootstrap) {
		makeBootstrapStub(dom.window);
	}
	if (typeof setupWin === "function") {
		setupWin(dom.window);
	}
	loadEditScripts(dom.window, html);
	return dom;
}

function invokeAttr(win, el, attrName, ev) {
	const code = el.getAttribute(attrName);
	if (!code) {
		return { ret: undefined, ev: ev };
	}
	const evt = ev || {
		preventDefault: function () { this.prevented = true; },
	};
	const fn = new win.Function("event", code);
	const ret = fn.call(el, evt);
	return { ret: ret, ev: evt };
}

function installNetworkCounters(win) {
	const counters = { fetch: 0, submit: 0, requestSubmit: 0 };
	win.fetch = function () {
		counters.fetch++;
		return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } });
	};
	const proto = win.HTMLFormElement.prototype;
	proto.submit = function () { counters.submit++; };
	proto.requestSubmit = function () { counters.requestSubmit++; };
	return counters;
}

function renderScenario(scenario, baseline) {
	return runPhp(baseline ? baselinePhp : renderPhp, [scenario]);
}

function openModalFromLibrary(html, profileId, isNew) {
	const dom = openDom(html, "http://127.0.0.1/layer7_policies.php?library=1", null, true);
	const win = dom.window;
	const ev = { preventDefault: function () {} };
	win.l7showProfileEditModal(profileId, isNew, ev);
	const form = editForm(win.document);
	return { dom: dom, win: win, form: form, modal: win.document.getElementById("l7ProfileEditModal") };
}

function assertBaselineModalOpened(win, label, profileId, isNew) {
	const modal = win.document.getElementById("l7ProfileEditModal");
	check(!!modal, label + ": modal presente");
	if (!modal) {
		return;
	}
	const visible = modal.style.display !== "none" || modal.classList.contains("in");
	check(visible, label + ": baseline modal abre via JS original");
	const isNewEl = win.document.getElementById("l7EditProfileIsNew");
	const idEl = win.document.getElementById("l7EditProfileId");
	check(!!isNewEl && isNewEl.value === (isNew ? "1" : "0"), label + ": is_new preenchido");
	if (isNew) {
		check(!!idEl && idEl.value === "", label + ": id vazio em novo");
		const iconEl = win.document.getElementById("l7EditProfileIcon");
		check(!!iconEl && iconEl.value === "fa-cube", label + ": icon padrao novo");
	} else {
		check(!!idEl && idEl.value === profileId, label + ": profile_id preenchido");
	}
}

function checkboxValues(form, name) {
	return [...form.querySelectorAll('input[name="' + name + '"]')].map(function (inp) {
		return inp.value;
	}).sort();
}

function checkedValues(form, name) {
	return [...form.querySelectorAll('input[name="' + name + '"]:checked')].map(function (inp) {
		return inp.value;
	}).sort();
}

function compareModalBaselineToPage(label, libraryScenario, pageScenario, profileId, isNew) {
	const baseLib = renderScenario(libraryScenario, true);
	const candPageHtml = renderScenario(pageScenario, false);
	const baseModal = openModalFromLibrary(baseLib, profileId, isNew);
	assertBaselineModalOpened(baseModal.win, label, profileId, isNew);
	check(!!baseModal.form, label + ": baseline form modal");
	const candDom = openDom(
		candPageHtml,
		"http://127.0.0.1/layer7_policies.php",
		null,
		false
	);
	const candForm = editForm(candDom.window.document);
	check(!!candForm, label + ": candidato form pagina");
	if (!baseModal.form || !candForm) {
		return;
	}
	const saveB = baseModal.form.querySelector('[name="save_profile_edit"]');
	const saveC = candForm.querySelector('[name="save_profile_edit"]');
	compareFormData(label, fdSnapshot(baseModal.form, saveB), fdSnapshot(candForm, saveC));
	check(
		checkboxValues(baseModal.form, "edit_profile_apps[]").join("|") ===
		checkboxValues(candForm, "edit_profile_apps[]").join("|"),
		label + ": catalogo apps completo"
	);
	check(
		checkboxValues(baseModal.form, "edit_profile_cats[]").join("|") ===
		checkboxValues(candForm, "edit_profile_cats[]").join("|"),
		label + ": catalogo cats completo"
	);
	check(
		checkedValues(baseModal.form, "edit_profile_apps[]").join("|") ===
		checkedValues(candForm, "edit_profile_apps[]").join("|"),
		label + ": apps seleccionados"
	);
	check(
		checkedValues(baseModal.form, "edit_profile_cats[]").join("|") ===
		checkedValues(candForm, "edit_profile_cats[]").join("|"),
		label + ": cats seleccionados"
	);
	baseModal.dom.window.close();
	candDom.window.close();
}

function assertPostErrorMatches(scenario, expected) {
	const html = renderScenario(scenario, false);
	const dom = openDom(html, "http://127.0.0.1/layer7_policies.php", null, false);
	const form = editForm(dom.window.document);
	check(!!form, scenario + ": form POST erro");
	if (!form) {
		return;
	}
	const saveBtn = form.querySelector('[name="save_profile_edit"]');
	const snap = fdSnapshot(form, saveBtn);
	Object.keys(expected).forEach(function (k) {
		const exp = expected[k];
		const got = snap.entries[k];
		if (Array.isArray(exp)) {
			const gs = Array.isArray(got) ? got.slice().sort().join("\0") : String(got || "");
			const es = exp.slice().sort().join("\0");
			check(gs === es, scenario + ": POST " + k);
		} else {
			check(String(got === undefined ? "" : got) === String(exp), scenario + ": POST " + k);
		}
	});
	check(!("delete_custom_profile" in snap.entries), scenario + ": save sem delete_custom_profile");
	const delBtn = form.querySelector('[name="delete_custom_profile"]');
	if (delBtn) {
		const delSnap = fdSnapshot(form, delBtn);
		check(!("save_profile_edit" in delSnap.entries), scenario + ": delete sem save_profile_edit");
		check(delSnap.entries.delete_custom_profile === "1", scenario + ": delete submitter");
	}
	dom.window.close();
}

/* Baseline: biblioteca + l7showProfileEditModal original vs pagina GET candidata */
compareModalBaselineToPage("factory", "library-links", "edit-factory-get", "social", false);
compareModalBaselineToPage("custom", "library-links", "edit-custom-get", "c-harness-edit", false);
compareModalBaselineToPage("new", "library-links", "edit-new-get", "", true);

/* POST erro: candidato vs POST esperado (sem baseline perdedor) */
assertPostErrorMatches("post-error-empty-fields", {
	edit_profile_id: "c-harness-edit",
	edit_profile_is_new: "0",
	edit_profile_name: "",
	edit_profile_description: "",
	edit_profile_icon: "",
	edit_profile_hosts: "not a valid host!!!",
	save_profile_edit: "1",
});
assertPostErrorMatches("post-error-hostile", {
	edit_profile_id: "c-harness-edit",
	edit_profile_is_new: "0",
	edit_profile_name: 'Nome <img src=x onerror=alert(1)> & "q"',
	edit_profile_description: "Desc <script>alert(1)</script>",
	edit_profile_icon: "fa-bug",
	"edit_profile_apps[]": ["AmazonVideo"],
	"edit_profile_cats[]": ["Advertisement"],
	edit_profile_hosts: HOSTILE_HOSTS,
	save_profile_edit: "1",
});

/* Pagina GET/POST conectada — confirm via onsubmit real */
function testConnectedConfirm(scenario, pageUrl) {
	const html = renderScenario(scenario, false);
	check(html.indexOf("var l7ProfileEditData") >= 0, scenario + ": l7ProfileEditData na pagina");
	check(/"connected"\s*:\s*true/.test(html), scenario + ": connected true no mapa salvo");
	const dom = openDom(html, pageUrl, null, false);
	const win = dom.window;
	const form = editForm(win.document);
	check(!!form, scenario + ": form dedicado");
	if (!form) {
		return;
	}
	let calls = 0;
	win.confirm = function () { calls++; return false; };
	const saveBtn = form.querySelector('[name="save_profile_edit"]');
	const onsubmit = form.getAttribute("onsubmit");
	check(!!onsubmit && onsubmit.indexOf("l7confirmProfileEditSave") >= 0, scenario + ": onsubmit contrato");
	const ev = { submitter: saveBtn, preventDefault: function () {} };
	const blocked = invokeAttr(win, form, "onsubmit", ev).ret === false;
	check(calls >= 1 && blocked, scenario + ": confirm recusado bloqueia onsubmit");
	calls = 0;
	win.confirm = function () { calls++; return true; };
	check(invokeAttr(win, form, "onsubmit", { submitter: saveBtn, preventDefault: function () {} }).ret !== false,
		scenario + ": confirm aceite permite onsubmit");
	const delBtn = form.querySelector('[name="delete_custom_profile"]');
	check(!!delBtn, scenario + ": botao apagar presente");
	check(invokeAttr(win, form, "onsubmit", { submitter: delBtn, preventDefault: function () {} }).ret !== false,
		scenario + ": delete submitter ignora confirm save");
	dom.window.close();
}

testConnectedConfirm("edit-connected", "http://127.0.0.1/layer7_policies.php?profile_edit=social");
testConnectedConfirm("post-error-connected", "http://127.0.0.1/layer7_policies.php");

/* Biblioteca conectada — confirm obrigatoria factory e custom */
function connectedIds(html) {
	const m = html.match(/var l7ProfileEditData = (\{[\s\S]*?\});/);
	if (!m) {
		return [];
	}
	const data = JSON.parse(m[1]);
	return Object.keys(data).filter(function (id) {
		return data[id] && data[id].connected;
	});
}

const connectedLibHtml = renderScenario("library-connected", false);
const connectedIdsList = connectedIds(connectedLibHtml);
check(connectedIdsList.indexOf("social") >= 0, "library-connected: factory ligado no mapa");
check(connectedIdsList.indexOf("c-harness-edit") >= 0, "library-connected: custom ligado no mapa");

["social", "c-harness-edit"].forEach(function (pid) {
	const ctx = openModalFromLibrary(connectedLibHtml, pid, false);
	assertBaselineModalOpened(ctx.win, "library-connected modal " + pid, pid, false);
	const form = ctx.form;
	check(!!form, "library-connected modal " + pid + ": form");
	let calls = 0;
	ctx.win.confirm = function () { calls++; return false; };
	const saveBtn = form.querySelector('[name="save_profile_edit"]');
	check(ctx.win.l7confirmProfileEditSave({ submitter: saveBtn }) === false && calls >= 1,
		"library-connected " + pid + ": confirm recusado");
	calls = 0;
	ctx.win.confirm = function () { calls++; return true; };
	check(ctx.win.l7confirmProfileEditSave({ submitter: saveBtn }) === true,
		"library-connected " + pid + ": confirm aceite");
	const customOnly = form.querySelector(".l7-edit-custom-only");
	const nameInput = form.querySelector("#l7EditProfileName");
	if (pid === "social") {
		check(customOnly && isEditDomHidden(customOnly), "library-connected factory: custom-only hidden");
		check(nameInput && !nameInput.disabled, "library-connected factory: nome nao disabled");
		const delBtnFactory = form.querySelector("#l7EditProfileDeleteBtn");
		check(!!delBtnFactory && isEditDomHidden(delBtnFactory), "library-connected factory: apagar oculto");
	} else {
		check(customOnly && !isEditDomHidden(customOnly), "library-connected custom: custom-only visivel");
	}
	const delBtn = form.querySelector("#l7EditProfileDeleteBtn");
	if (pid === "c-harness-edit") {
		check(!!delBtn && !isEditDomHidden(delBtn), "library-connected custom: apagar visivel");
		check(ctx.win.l7confirmProfileEditSave({ submitter: delBtn }) === true,
			"library-connected custom: delete submitter");
	}
	ctx.dom.window.close();
});

/* onclick cancelar real + rascunho (biblioteca sem politicas ligadas) */
const libraryHtml = renderScenario("library-links", false);
const libraryDom = openDom(libraryHtml, "http://127.0.0.1/layer7_policies.php?library=1", null, true);
const win = libraryDom.window;
const doc = win.document;
const counters = installNetworkCounters(win);
const search = doc.getElementById("l7ProfileSearch");
if (search) {
	search.value = "draft-marker";
}
const draftBtn = doc.querySelector('button[data-profile-id][data-saved="0"]');
check(!!draftBtn, "library: botao rascunho disponivel");
if (draftBtn) {
	const beforeDesired = draftBtn.getAttribute("data-desired");
	win.l7toggleProfileDraft(draftBtn);
	check(draftBtn.getAttribute("data-desired") !== beforeDesired, "library: data-desired alterado");
	const d = win.l7collectProfileDraft();
	check(d.enableIds.length + d.disableIds.length > 0, "library: rascunho com pendencias");
	const bar = doc.getElementById("l7ProfileDraftBar");
	check(bar && bar.hidden === false, "library: barra rascunho visivel");
}
const editLink = doc.querySelector('a[href*="profile_edit="]');
const profileId = decodeURIComponent((editLink.getAttribute("href").match(/profile_edit=([^&#"]+)/) || [])[1]);
win.l7showProfileEditModal(profileId, false, { preventDefault: function () {} });
const form = editForm(doc);
const cancelBtn = form.querySelector('button[onclick*="l7hideProfileEditModal"]');
check(!!cancelBtn, "library: cancelar com onclick");
invokeAttr(win, cancelBtn, "onclick");
check(counters.fetch === 0 && counters.submit === 0 && counters.requestSubmit === 0,
	"library: cancelar onclick sem rede");
check(search.value === "draft-marker", "library: filtro preservado");
if (draftBtn) {
	check(draftBtn.getAttribute("data-desired") !== "0" || win.l7collectProfileDraft().enableIds.length > 0,
		"library: rascunho preservado apos cancelar");
}

/* Filtros apps e cats */
const appsFilter = doc.getElementById("l7EditAppsFilter");
const catsFilter = doc.getElementById("l7EditCatsFilter");
check(!!appsFilter && !!catsFilter, "library: filtros apps e cats");
const appBox = form.querySelector('#l7EditAppsList input[type=checkbox]');
if (appBox && appsFilter) {
	appBox.checked = true;
	appsFilter.value = "zzznomatch";
	win.l7filter(appsFilter, "l7EditAppsList");
	check(appBox.checked, "library: filtrar apps nao desmarca");
	win.l7clearEditFilter("l7EditAppsFilter", "l7EditAppsList");
	check(appsFilter.value === "", "library: limpar filtro apps");
}
if (catsFilter) {
	catsFilter.value = "zzznomatch";
	win.l7filter(catsFilter, "l7EditCatsList");
	win.l7clearEditFilter("l7EditCatsFilter", "l7EditCatsList");
	check(catsFilter.value === "", "library: limpar filtro cats");
}

/* ID ausente / oculto → href GET */
check(win.l7showProfileEditModal("invalid-not-in-map", false, { preventDefault: function () {} }) === true,
	"modal: ID ausente no mapa segue GET");
const hiddenLink = doc.querySelector('a[href*="profile_edit=c-hidden-edit"]');
check(!!hiddenLink, "library: link editar perfil oculto");
check(win.l7showProfileEditModal("c-hidden-edit", false, { preventDefault: function () {} }) === true,
	"modal: perfil oculto ausente no mapa segue GET");

libraryDom.window.close();

/* Fallback sem jQuery */
const fallbackDom = openDom(libraryHtml, "http://127.0.0.1/layer7_policies.php?library=1", null, false);
const fbWin = fallbackDom.window;
check(fbWin.l7showProfileEditModal("social", false, null) === true,
	"fallback: sem jQuery retorna true");
fallbackDom.window.close();

/* Escaping DOM original — hostile textarea, sem scripts */
const hostileHtml = renderScenario("post-error-hostile", false);
const inspectDom = new JSDOM(hostileHtml, {
	url: "http://127.0.0.1/layer7_policies.php",
	runScripts: "outside-only",
	pretendToBeVisual: false,
});
const region = inspectDom.window.document.getElementById("l7-profile-edit");
check(!!region, "post-error-hostile: regiao pagina");
const hostsTa = inspectDom.window.document.querySelector('[name="edit_profile_hosts"]');
check(!!hostsTa, "post-error-hostile: textarea hosts");
check(hostsTa.value === HOSTILE_HOSTS, "post-error-hostile: valor hostil exacto");
check(region.querySelectorAll("script").length === 0, "post-error-hostile: sem script na regiao");
check(region.innerHTML.indexOf("</textarea><script>") === -1,
	"post-error-hostile: textarea nao fecha com script");

if (jsdomUnexpected.length > 0) {
	console.log("FAIL: jsdom errors inesperados (" + jsdomUnexpected.length + ")");
	fail = 1;
} else {
	console.log("PASS: sem jsdom errors inesperados");
}

if (fail) {
	console.error("SOME POLICIES PROFILE EDIT TESTS FAILED");
	process.exit(1);
}
console.log("ALL POLICIES PROFILE EDIT TESTS PASSED");
process.exit(0);
