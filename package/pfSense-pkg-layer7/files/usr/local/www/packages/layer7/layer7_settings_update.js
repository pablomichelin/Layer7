/**
 * Updater AJAX — ficheiro externo (pfSense Plus bloqueia scripts inline / onclick).
 */
(function(global) {
	"use strict";

	function cfg() {
		var root = document.getElementById("l7_pkg_update");
		if (!root) {
			return {};
		}
		var raw = root.getAttribute("data-l7-update-cfg") || "{}";
		try {
			return JSON.parse(raw);
		} catch (e) {
			return {};
		}
	}

	function escapeHtml(text) {
		var el = document.createElement("div");
		el.textContent = text == null ? "" : String(text);
		return el.innerHTML;
	}

	function renderResult(data, c) {
		var status = document.getElementById("l7_update_status");
		var versions = document.getElementById("l7_update_versions");
		var actions = document.getElementById("l7_update_actions");
		if (!status || !versions || !actions) {
			return;
		}

		if (!data || !data.ok) {
			status.innerHTML = '<div class="alert alert-danger">' +
				escapeHtml(data && data.error ? data.error : c.parseErr) + "</div>";
			return;
		}

		status.innerHTML = "";
		versions.innerHTML = escapeHtml(c.installed) + ': <code>' + escapeHtml(data.current) +
			"</code> &nbsp;|&nbsp; " + escapeHtml(c.latest) + ': <code>' +
			escapeHtml(data.latest) + "</code>";

		var html = "";
		if (data.has_update && data.pkg_url) {
			html += '<form method="post" action="layer7_settings.php#l7_pkg_update" style="display:inline;">' +
				'<input type="hidden" name="pkg_url" value="' + escapeHtml(data.pkg_url) + '" />' +
				'<button type="submit" name="do_update" value="1" class="btn btn-sm btn-success">' +
				'<i class="fa fa-download"></i> ' + escapeHtml(c.updateBtn + data.latest) +
				"</button></form>";
		} else if (data.no_pkg_asset) {
			html += '<div class="alert alert-warning">' + escapeHtml(c.noPkg) + "</div>";
		} else if (data.up_to_date) {
			html += '<span class="text-success"><i class="fa fa-check-circle"></i> ' +
				escapeHtml(c.upToDate) + "</span>";
		}

		html += '<button type="button" id="l7_btn_check_update" class="btn btn-sm btn-info" style="margin-left:8px;">' +
			'<i class="fa fa-refresh"></i> ' + escapeHtml(c.checkBtn) + "</button>";
		html += ' <form method="post" action="layer7_settings.php#l7_pkg_update" style="display:inline;">' +
			'<button type="submit" name="check_update" value="1" class="btn btn-sm btn-link" style="margin-left:4px;padding:0 4px;">' +
			escapeHtml(c.compatBtn) + "</button></form>";

		actions.innerHTML = html;
		bindCheckButton();
	}

	function checkUpdate() {
		var c = cfg();
		var btn = document.getElementById("l7_btn_check_update");
		var status = document.getElementById("l7_update_status");
		if (btn) {
			btn.disabled = true;
		}
		if (status) {
			status.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> ' +
				escapeHtml(c.checking) + "</div>";
		}

		var url = c.ajaxUrl || "/packages/layer7/layer7_settings_ajax.php?action=check_update";
		url += (url.indexOf("?") >= 0 ? "&" : "?") + "_=" + Date.now();

		var xhr = new XMLHttpRequest();
		xhr.open("GET", url, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState !== 4) {
				return;
			}
			if (btn) {
				btn.disabled = false;
			}
			if (xhr.status !== 200) {
				if (status) {
					status.innerHTML = '<div class="alert alert-danger">' +
						escapeHtml(String(c.httpErr).replace("%d", xhr.status)) + "</div>";
				}
				return;
			}
			var data;
			try {
				data = JSON.parse(xhr.responseText);
			} catch (e) {
				if (status) {
					status.innerHTML = '<div class="alert alert-danger">' + escapeHtml(c.parseErr) + "</div>";
				}
				return;
			}
			renderResult(data, c);
		};
		xhr.send();
	}

	function bindCheckButton() {
		var btn = document.getElementById("l7_btn_check_update");
		if (!btn || btn.getAttribute("data-l7-bound") === "1") {
			return;
		}
		btn.setAttribute("data-l7-bound", "1");
		btn.addEventListener("click", function(ev) {
			ev.preventDefault();
			checkUpdate();
		});
	}

	function init() {
		bindCheckButton();
		var root = document.getElementById("l7_pkg_update");
		if (root && root.getAttribute("data-l7-scroll") === "1") {
			root.scrollIntoView({ behavior: "smooth", block: "start" });
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}

	global.l7CheckUpdate = checkUpdate;
})(window);
