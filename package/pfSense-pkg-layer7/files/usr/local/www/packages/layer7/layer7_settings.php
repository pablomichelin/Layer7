<?php
##|+PRIV
##|*IDENT=page-services-layer7-settings
##|*NAME=Services: Layer 7 (settings)
##|*DESCR=Allow access to Layer 7 settings.
##|*MATCH=layer7_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$layer7_release_owner = "pablomichelin";
$layer7_release_repo = "Layer7";
$update_info = null;
$update_msg = "";
$update_err = "";
$backup_msg = "";
$backup_err = "";

if ($_POST["export_config"] ?? false) {
	$data = layer7_load_or_default();
	$export = isset($data["layer7"]) ? $data["layer7"] : array();
	unset($export["protos_file"]);
	/* Nunca exportar segredos LDAP (ficheiro separado; limpar se legado). */
	if (isset($export["identity"]["ldap"]["bind_password"])) {
		unset($export["identity"]["ldap"]["bind_password"]);
	}
	if (isset($export["identity"]["radius"]["secret"])) {
		unset($export["identity"]["radius"]["secret"]);
	}
	if (isset($export["identity"]["dc_agent"]["secret"])) {
		unset($export["identity"]["dc_agent"]["secret"]);
	}
	$bl_export = layer7_bl_config_load();
	$profiles_custom_export = layer7_profiles_custom_load();
	$payload = array(
		"layer7_backup" => true,
		"version" => layer7_daemon_version(),
		"timestamp" => date("c"),
		"layer7" => $export,
		"blacklists" => $bl_export,
		"profiles_custom" => $profiles_custom_export
	);
	$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	header("Content-Type: application/json");
	header("Content-Disposition: attachment; filename=\"layer7-backup-" . date("Ymd-His") . ".json\"");
	echo $json;
	exit;
}

if ($_POST["import_config"] ?? false) {
	if (!isset($_FILES["import_file"]) || $_FILES["import_file"]["error"] !== UPLOAD_ERR_OK) {
		$backup_err = l7_t("Nenhum ficheiro enviado ou erro no upload.");
	} else {
		$raw = @file_get_contents($_FILES["import_file"]["tmp_name"]);
		if (!is_string($raw) || $raw === "") {
			$backup_err = l7_t("Ficheiro vazio.");
		} else {
			$imported = @json_decode($raw, true);
			if (!is_array($imported)) {
				$backup_err = l7_t("JSON invalido.");
			} else {
				$l7_import = null;
				if (isset($imported["layer7"]) && is_array($imported["layer7"])) {
					$l7_import = $imported["layer7"];
				} elseif (isset($imported["layer7_backup"]) && isset($imported["layer7"]) && is_array($imported["layer7"])) {
					$l7_import = $imported["layer7"];
				}
				if ($l7_import === null) {
					$backup_err = l7_t("Ficheiro nao contem seccao 'layer7' valida.");
				} else {
					$data = layer7_load_or_default();
					$preserve_keys = array("protos_file");
					foreach ($preserve_keys as $pk) {
						if (isset($data["layer7"][$pk])) {
							$l7_import[$pk] = $data["layer7"][$pk];
						}
					}
					$data["layer7"] = $l7_import;
					if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
						$data["layer7"]["policies"] = array();
					}
					if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
						$data["layer7"]["exceptions"] = array();
					}
					$save_ok = layer7_save_json($data);
					if ($save_ok && isset($imported["blacklists"]) && is_array($imported["blacklists"])) {
						$save_ok = layer7_bl_config_save($imported["blacklists"]) &&
							layer7_bl_sync_custom_category_files($imported["blacklists"]);
					}
					if ($save_ok && isset($imported["profiles_custom"]) &&
					    is_array($imported["profiles_custom"])) {
						$save_ok = layer7_profiles_custom_save($imported["profiles_custom"]);
					}
					if ($save_ok) {
						layer7_signal_reload();
						layer7_filter_configure_safe();
						$backup_msg = l7_t("Configuracao importada com sucesso.");
					} else {
						$backup_err = l7_t("Erro ao gravar a configuracao importada.");
					}
				}
			}
		}
	}
}

if ($_POST["check_update"] ?? false) {
	$check = layer7_check_for_update($layer7_release_owner, $layer7_release_repo);
	if (!$check["ok"]) {
		$update_err = $check["error"];
	} else {
		$update_info = array(
			"current" => $check["current"],
			"latest" => $check["latest"],
			"tag" => $check["tag"],
			"pkg_url" => $check["pkg_url"],
			"name" => $check["name"]
		);
	}
}

if ($_POST["do_update"] ?? false) {
	$pkg_url = isset($_POST["pkg_url"]) ? trim($_POST["pkg_url"]) : "";
	if ($pkg_url === "" ||
	    strpos($pkg_url, "https://github.com/pablomichelin/Layer7/") !== 0) {
		$update_err = l7_t("URL do pacote invalida.");
	} else {
		@mkdir("/var/db/layer7", 0755, true);
		$pkg_file = "/var/db/layer7/layer7-update.pkg";
		@unlink($pkg_file);

		$stop = layer7_exec_timeout(
		    "/usr/sbin/service layer7d onestop",
		    L7_CTRL_TIMEOUT_SERVICE
		);
		if (!empty($stop["timed_out"])) {
			layer7_log_pkg_warn(
			    "Layer7 update: onestop timeout — " . $stop["error"]
			);
		}
		$dl = layer7_exec_timeout(
		    "/usr/bin/fetch -qo " . escapeshellarg($pkg_file) . " " .
			escapeshellarg($pkg_url),
		    L7_CTRL_TIMEOUT_FETCH
		);
		if (!$dl["ok"] || !file_exists($pkg_file)) {
			$update_err = l7_t("Falha ao baixar o pacote do GitHub.") .
			    (($dl["error"] !== "") ? (" " . $dl["error"]) : "");
			layer7_exec_timeout(
			    "/usr/sbin/service layer7d onestart",
			    L7_CTRL_TIMEOUT_SERVICE
			);
		} else {
			$inst = layer7_exec_timeout(
			    "IGNORE_OSVERSION=yes /usr/sbin/pkg add -f " .
				escapeshellarg($pkg_file),
			    L7_CTRL_TIMEOUT_PKG
			);
			@unlink($pkg_file);
			if (!$inst["ok"]) {
				$update_err = l7_t("Falha na instalacao do pacote: ") .
				    (($inst["error"] !== "") ? $inst["error"] : $inst["output"]);
				layer7_exec_timeout(
				    "/usr/sbin/service layer7d onestart",
				    L7_CTRL_TIMEOUT_SERVICE
				);
			} else {
				layer7_exec_timeout(
				    "/usr/sbin/service layer7d onestart",
				    L7_CTRL_TIMEOUT_SERVICE
				);
				sleep(1);
				$new_ver = layer7_pkg_version();
				if ($new_ver === "") {
					$new_ver = layer7_daemon_version();
				}
				$update_msg = l7_t("Pacote actualizado com sucesso para a versao ") . ($new_ver !== "" ? $new_ver : "nova") . ".";
			}
		}
	}
}

if ($_POST["register_license"] ?? false) {
	$license_code_raw = trim((string)($_POST["license_code"] ?? ""));
	$license_code = preg_replace('/[^A-Za-z0-9]/', '', $license_code_raw);
	if ($license_code === "" || preg_match('/^[A-Za-z0-9]{16,128}$/', $license_code) !== 1) {
		$input_errors[] = l7_t("Informe um codigo de licenca valido.");
	} else {
		$act = layer7_exec_timeout(
		    "/usr/local/sbin/layer7d --activate " . escapeshellarg($license_code),
		    L7_CTRL_TIMEOUT_ACTIVATE
		);
		$out = ($act["output"] !== "")
		    ? preg_split("/\r\n|\n|\r/", $act["output"])
		    : array();
		$rc = (int)$act["rc"];
		if (!empty($act["ok"])) {
			$data = layer7_load_or_default();
			$data["layer7"]["license_key_mask"] = substr($license_code, 0, 5) . "************";
			if (layer7_save_json($data)) {
				layer7_restart_service();
				$savemsg = l7_t("Licenca registada com sucesso.");
			}
		} else {
			if (!empty($act["timed_out"])) {
				$input_errors[] = l7_t("Timeout ao activar licenca.") .
				    (($act["error"] !== "") ? (" " . $act["error"]) : "");
			} else {
				$input_errors[] = l7_t("Licenca invalida.");
			}
		}
	}
}

/* 30.14 / BG-118 — toggle check-in (isolados = OFF documentado). */
if ($_POST["save_check_in"] ?? false) {
	$data = layer7_load_or_default();
	$data["layer7"]["check_in_enabled"] = isset($_POST["check_in_enabled"]);
	if (layer7_save_json($data)) {
		layer7_restart_service();
		if (!empty($data["layer7"]["check_in_enabled"])) {
			$savemsg = l7_t("Check-in online activado. O appliance contacta periodicamente o servidor de licencas; uma falha de rede nao desliga o bloqueio.");
		} else {
			$savemsg = l7_t("Check-in online desactivado. A revogacao remota nao aplica ate reactivar.");
		}
	}
}

if ($_POST["revoke_license"] ?? false) {
	$lic_file = layer7_lic_path();
	if (file_exists($lic_file)) {
		@unlink($lic_file);
	}
	$data = layer7_load_or_default();
	if (isset($data["layer7"]["license_key_mask"])) {
		unset($data["layer7"]["license_key_mask"]);
	}
	if (layer7_save_json($data)) {
		layer7_restart_service();
		$savemsg = l7_t("Licenca revogada com sucesso.");
	}
}

if ($_POST["save"] ?? false) {
	$current_data = layer7_load_or_default();
	$current_l7 = isset($current_data["layer7"]) && is_array($current_data["layer7"]) ?
	    $current_data["layer7"] : array();
	$current_reports = layer7_reports_config();
	$save_scope = trim((string)($_POST["save_scope"] ?? "general"));
	$is_reports_save = ($save_scope === "reports");
	$is_general_save = !$is_reports_save;

	$mode = $is_general_save ? ($_POST["mode"] ?? "monitor") :
	    (isset($current_l7["mode"]) ? $current_l7["mode"] : "monitor");
	if (!in_array($mode, array("monitor", "enforce"), true)) {
		$mode = "monitor";
	}
	$log_level = $is_general_save ? ($_POST["log_level"] ?? "info") :
	    (isset($current_l7["log_level"]) ? $current_l7["log_level"] : "info");
	if (!in_array($log_level, array("error", "warn", "info", "debug"), true)) {
		$log_level = "info";
	}
	$enabled = $is_general_save ? isset($_POST["enabled"]) :
	    !empty($current_l7["enabled"]);
	$syslog_remote = $is_general_save ? isset($_POST["syslog_remote"]) :
	    !empty($current_l7["syslog_remote"]);
	$sr_host = $is_general_save ? trim($_POST["syslog_remote_host"] ?? "") :
	    trim((string)($current_l7["syslog_remote_host"] ?? ""));
	$sr_port = $is_general_save ? (int)($_POST["syslog_remote_port"] ?? 514) :
	    (int)($current_l7["syslog_remote_port"] ?? 514);
	if ($sr_port < 1 || $sr_port > 65535) {
		$sr_port = 514;
	}
	if ($is_general_save && $syslog_remote && $sr_host === "") {
		$input_errors[] = l7_t("Syslog remoto: indique o host ou desative a opcao.");
	}
	if ($is_general_save && $syslog_remote && $sr_host !== "" && !layer7_syslog_remote_host_valid($sr_host)) {
		$input_errors[] = l7_t("Host syslog: use IPv4 ou hostname valido.");
	}

	$selected_ifaces = array();
	if (!$is_general_save && isset($current_l7["interfaces"]) &&
	    is_array($current_l7["interfaces"])) {
		foreach ($current_l7["interfaces"] as $ifname) {
			$ifname = trim((string)$ifname);
			if ($ifname !== "" && preg_match('/^[a-zA-Z0-9_.-]+$/', $ifname)) {
				$selected_ifaces[] = $ifname;
			}
		}
	}
	if ($is_general_save && isset($_POST["iface_sel"]) && is_array($_POST["iface_sel"])) {
		foreach ($_POST["iface_sel"] as $ifid) {
			if (is_string($ifid) && preg_match('/^[a-zA-Z0-9_.]+$/', $ifid)) {
				$real = layer7_real_interface_name($ifid);
				if ($real !== "") {
					$selected_ifaces[] = $real;
				}
			}
		}
	}
	$selected_ifaces = array_values(array_unique($selected_ifaces));
	if ($is_general_save && count($selected_ifaces) > 8) {
		$input_errors[] = l7_t("Maximo de 8 interfaces.");
	}

	$language = $is_general_save ? ($_POST["language"] ?? "pt") :
	    (isset($current_l7["language"]) ? $current_l7["language"] : "pt");
	if (!in_array($language, array("pt", "en"), true)) {
		$language = "pt";
	}

	$dbgm = $is_general_save ? (int)($_POST["debug_minutes"] ?? 0) :
	    (int)($current_l7["debug_minutes"] ?? 0);
	if ($dbgm < 0) {
		$dbgm = 0;
	}
	if ($dbgm > 720) {
		$dbgm = 720;
	}
	$log_file_max_mb = $is_general_save
	    ? (int)($_POST["log_file_max_mb"] ?? 5)
	    : (int)($current_l7["log_file_max_mb"] ?? 5);
	if ($log_file_max_mb < 1) {
		$log_file_max_mb = 1;
	}
	if ($log_file_max_mb > 100) {
		$log_file_max_mb = 100;
	}
	$log_file_keep = $is_general_save
	    ? (int)($_POST["log_file_keep"] ?? 3)
	    : (int)($current_l7["log_file_keep"] ?? 3);
	if ($log_file_keep < 1) {
		$log_file_keep = 1;
	}
	if ($log_file_keep > 10) {
		$log_file_keep = 10;
	}

	/* block_quic_interfaces: nova forma (array de interfaces reais). */
	$block_quic_ifaces = array();
	if ($is_general_save) {
		if (isset($_POST["block_quic_iface_sel"]) && is_array($_POST["block_quic_iface_sel"])) {
			foreach ($_POST["block_quic_iface_sel"] as $ifid) {
				$ifid = trim((string)$ifid);
				if ($ifid === "" || !preg_match('/^[a-zA-Z0-9_.]+$/', $ifid)) {
					continue;
				}
				$real = layer7_real_interface_name($ifid);
				if ($real !== "") {
					$block_quic_ifaces[] = $real;
				}
			}
		}
		$block_quic_ifaces = array_values(array_unique($block_quic_ifaces));
	} else {
		if (!empty($current_l7["block_quic_interfaces"]) &&
		    is_array($current_l7["block_quic_interfaces"])) {
			$block_quic_ifaces = $current_l7["block_quic_interfaces"];
		}
	}

	/* block_dot_doq: toggle explicito do anti-bypass porta 853 (Bloco 2).
	 * Defeito false para evitar falsos positivos (ex.: DNS privado Android,
	 * apps de banco que falham se 853 nao resolver). */
	$block_dot_doq = $is_general_save
	    ? isset($_POST["block_dot_doq"])
	    : !empty($current_l7["block_dot_doq"]);

	/* sni_inspection: toggle do A3 (Caminho A). Quando ligado, o daemon usa
	 * o SNI (TLS) / Host (HTTP) extraido pelo nDPI como host para matching de
	 * politicas — melhora bloqueio em CDNs e quando o DNS esta cifrado/cache.
	 * Defeito false (opt-in). Nao afecta regras PF; aplica-se no reload. */
	$sni_inspection = $is_general_save
	    ? isset($_POST["sni_inspection"])
	    : !empty($current_l7["sni_inspection"]);

	/* enforcement_model: Caminho B / E0. legacy_global = comportamento actual
	 * (layer7_block_dst global). scoped_hybrid = PF escopado (E2+); so activar
	 * apos validacao lab. Defeito legacy_global. */
	$enforcement_model = "legacy_global";
	if ($is_general_save) {
		$em_post = trim((string)($_POST["enforcement_model"] ?? "legacy_global"));
		if ($em_post === "scoped_hybrid") {
			$enforcement_model = "scoped_hybrid";
		}
	} else {
		$em_cur = isset($current_l7["enforcement_model"])
		    ? (string)$current_l7["enforcement_model"] : "legacy_global";
		$enforcement_model = ($em_cur === "scoped_hybrid")
		    ? "scoped_hybrid" : "legacy_global";
	}

	/* Pagina de bloqueio (ADR-0017): opt-in, requer mode=enforce. */
	$bp_cfg = layer7_blockpage_config_get($current_data);
	if ($is_general_save) {
		$bp_enabled = isset($_POST["block_page_enabled"]);
		$bp_portal = trim((string)($_POST["block_page_portal_ip"] ?? ""));
		$bp_title = trim((string)($_POST["block_page_title"] ?? ""));
		$bp_message = trim((string)($_POST["block_page_message"] ?? ""));
		$bp_contact = trim((string)($_POST["block_page_contact"] ?? ""));
		$bp_show_host = isset($_POST["block_page_show_host"]);
		$bp_show_policy = isset($_POST["block_page_show_policy"]);
		$bp_sinkhole_bl = isset($_POST["block_page_sinkhole_blacklists"]);
		$bp_bl_limit = (int)($_POST["block_page_blacklist_limit"] ?? 256);
		$bp_force_dns = isset($_POST["block_page_force_dns"]);
	} else {
		$bp_enabled = !empty($bp_cfg["enabled"]);
		$bp_portal = $bp_cfg["portal_ip"];
		$bp_title = $bp_cfg["title"];
		$bp_message = $bp_cfg["message"];
		$bp_contact = $bp_cfg["contact"];
		$bp_show_host = !empty($bp_cfg["show_host"]);
		$bp_show_policy = !empty($bp_cfg["show_policy"]);
		$bp_sinkhole_bl = !empty($bp_cfg["sinkhole_blacklists"]);
		$bp_bl_limit = (int)$bp_cfg["blacklist_domain_limit"];
		$bp_force_dns = !empty($bp_cfg["force_dns"]);
	}
	if ($bp_portal !== "" && !layer7_ipv4_valid($bp_portal)) {
		$input_errors[] = l7_t("IP portal da pagina de bloqueio invalido.");
	}
	if ($bp_title === "") {
		$bp_title = "Acesso bloqueado";
	}
	if (strlen($bp_title) > 120) {
		$bp_title = substr($bp_title, 0, 120);
	}
	if (strlen($bp_message) > 2000) {
		$bp_message = substr($bp_message, 0, 2000);
	}
	if (strlen($bp_contact) > 500) {
		$bp_contact = substr($bp_contact, 0, 500);
	}
	if ($bp_bl_limit < 1) {
		$bp_bl_limit = 256;
	}
	if ($bp_bl_limit > 4096) {
		$bp_bl_limit = 4096;
	}

	$rpt_enabled = !empty($current_reports["enabled"]);
	$rpt_retention = (int)($current_reports["retention_days"] ?? 30);
	$rpt_interval = (int)($current_reports["collect_interval"] ?? 5);
	$rpt_event_enabled = !empty($current_reports["event_log_enabled"]);
	$rpt_event_retention = (int)($current_reports["event_retention_days"] ?? 7);
	$rpt_event_max_mb = (int)($current_reports["event_max_mb"] ?? 100);
	$rpt_event_ifaces = layer7_reports_normalize_interfaces(
	    $current_reports["event_interfaces"] ?? array());
	if ($is_reports_save) {
		$rpt_enabled = isset($_POST["reports_enabled"]);
		$rpt_preset = trim((string)($_POST["reports_retention_preset"] ?? "custom"));
		if ($rpt_preset !== "custom" && ctype_digit($rpt_preset)) {
			$rpt_retention = (int)$rpt_preset;
		} else {
			$rpt_retention = (int)($_POST["reports_retention"] ?? 30);
		}
		if ($rpt_retention < 1) {
			$rpt_retention = 1;
		}
		if ($rpt_retention > 365) {
			$rpt_retention = 365;
		}
		$rpt_interval = (int)($_POST["reports_interval"] ?? 5);
		if (!in_array($rpt_interval, array(5, 10, 15, 30, 60), true)) {
			$rpt_interval = 5;
		}

		$rpt_event_enabled = isset($_POST["reports_event_log_enabled"]);
		$rpt_event_preset = trim((string)($_POST["reports_event_retention_preset"] ?? "custom"));
		if ($rpt_event_preset !== "custom" && ctype_digit($rpt_event_preset)) {
			$rpt_event_retention = (int)$rpt_event_preset;
		} else {
			$rpt_event_retention = (int)($_POST["reports_event_retention"] ?? 15);
		}
		if ($rpt_event_retention < 1) {
			$rpt_event_retention = 1;
		}
		if ($rpt_event_retention > 365) {
			$rpt_event_retention = 365;
		}
		$rpt_event_max_mb = (int)($_POST["reports_event_max_mb"] ?? 100);
		if ($rpt_event_max_mb < 25) {
			$rpt_event_max_mb = 25;
		}
		if ($rpt_event_max_mb > 1000) {
			$rpt_event_max_mb = 1000;
		}

		$rpt_event_ifaces = array();
		if (isset($_POST["reports_iface_sel"]) && is_array($_POST["reports_iface_sel"])) {
			foreach ($_POST["reports_iface_sel"] as $ifid) {
				if (is_string($ifid) && preg_match('/^[a-zA-Z0-9_.]+$/', $ifid)) {
					$real = layer7_real_interface_name($ifid);
					if ($real !== "") {
						$rpt_event_ifaces[] = $real;
					}
				}
			}
		}
		$rpt_event_ifaces = layer7_reports_normalize_interfaces($rpt_event_ifaces);
		if (count($rpt_event_ifaces) > 8) {
			$input_errors[] = l7_t("Maximo de 8 interfaces para log detalhado.");
		}
	}

	if (empty($input_errors)) {
		$data = $current_data;
		$data["layer7"]["language"] = $language;
		$data["layer7"]["enabled"] = $enabled;
		$data["layer7"]["mode"] = $mode;
		$data["layer7"]["log_level"] = $log_level;
		$data["layer7"]["log_file_max_mb"] = $log_file_max_mb;
		$data["layer7"]["log_file_keep"] = $log_file_keep;
		$data["layer7"]["syslog_remote"] = $syslog_remote;
		$data["layer7"]["syslog_remote_host"] = $sr_host;
		$data["layer7"]["syslog_remote_port"] = $sr_port;
		$data["layer7"]["debug_minutes"] = $dbgm;
		$data["layer7"]["interfaces"] = array_values(array_unique($selected_ifaces));

		$old_quic_ifaces = isset($current_l7["block_quic_interfaces"]) &&
		    is_array($current_l7["block_quic_interfaces"])
		    ? $current_l7["block_quic_interfaces"] : array();
		$data["layer7"]["block_quic"] = false;
		$data["layer7"]["block_quic_interfaces"] = $block_quic_ifaces;
		$data["layer7"]["block_dot_doq"] = $block_dot_doq;
		$data["layer7"]["sni_inspection"] = $sni_inspection;
		$data["layer7"]["enforcement_model"] = $enforcement_model;
		$data["layer7"]["block_page"] = array(
			"enabled" => $bp_enabled,
			"portal_ip" => $bp_portal,
			"title" => $bp_title,
			"message" => $bp_message,
			"contact" => $bp_contact,
			"show_host" => $bp_show_host,
			"show_policy" => $bp_show_policy,
			"sinkhole_blacklists" => $bp_sinkhole_bl,
			"blacklist_domain_limit" => $bp_bl_limit,
			"force_dns" => $bp_force_dns
		);

		$data["layer7"]["reports"] = array(
			"enabled" => $rpt_enabled,
			"retention_days" => $rpt_retention,
			"collect_interval" => $rpt_interval,
			"event_log_enabled" => $rpt_event_enabled,
			"event_retention_days" => $rpt_event_retention,
			"event_max_mb" => $rpt_event_max_mb,
			"event_interfaces" => $rpt_event_ifaces
		);

		if (layer7_save_json($data)) {
			$old_em = (isset($current_l7["enforcement_model"]) &&
			    (string)$current_l7["enforcement_model"] === "scoped_hybrid")
			    ? "scoped_hybrid" : "legacy_global";
			$em_changed = ($old_em !== $enforcement_model);
			layer7_signal_reload();
			$old_mode = isset($current_l7["mode"]) ? (string)$current_l7["mode"] : "monitor";
			$old_enabled = !empty($current_l7["enabled"]);
			$old_dot_doq = !empty($current_l7["block_dot_doq"]);
			$old_bp = layer7_blockpage_config_get($current_data);
			$bp_changed = (
			    !empty($old_bp["enabled"]) !== (bool)$bp_enabled ||
			    (string)$old_bp["portal_ip"] !== (string)$bp_portal ||
			    (string)$old_bp["title"] !== (string)$bp_title ||
			    (string)$old_bp["message"] !== (string)$bp_message ||
			    (string)$old_bp["contact"] !== (string)$bp_contact ||
			    !empty($old_bp["show_host"]) !== (bool)$bp_show_host ||
			    !empty($old_bp["show_policy"]) !== (bool)$bp_show_policy ||
			    !empty($old_bp["sinkhole_blacklists"]) !== (bool)$bp_sinkhole_bl ||
			    (int)$old_bp["blacklist_domain_limit"] !== (int)$bp_bl_limit ||
			    !empty($old_bp["force_dns"]) !== (bool)$bp_force_dns
			);
			$pf_relevant_changed = (
			    $old_quic_ifaces !== $data["layer7"]["block_quic_interfaces"] ||
			    $old_mode !== $data["layer7"]["mode"] ||
			    $old_enabled !== (bool)$data["layer7"]["enabled"] ||
			    $old_dot_doq !== (bool)$data["layer7"]["block_dot_doq"] ||
			    $em_changed ||
			    $bp_changed
			);
			if ($pf_relevant_changed) {
				if ($em_changed) {
					layer7_flush_dynamic_tables();
				}
				layer7_filter_configure_safe();
			} elseif ($bp_changed) {
				layer7_blockpage_sync($data);
			}
			layer7_reports_setup_cron(($rpt_enabled || $rpt_event_enabled), $rpt_interval);
			$savemsg = l7_t("Configuracao gravada. SIGHUP enviado ao layer7d se o servico estiver em execucao.");
		}
	}
}

$data = layer7_load_or_default();
$L = $data["layer7"];
$en = !empty($L["enabled"]);
$mode = isset($L["mode"]) ? $L["mode"] : "monitor";
$ll = isset($L["log_level"]) ? $L["log_level"] : "info";
$sr = !empty($L["syslog_remote"]);
$sr_host = isset($L["syslog_remote_host"]) ? (string)$L["syslog_remote_host"] : "";
$sr_port = isset($L["syslog_remote_port"]) ? (int)$L["syslog_remote_port"] : 514;
$log_file_max_mb = isset($L["log_file_max_mb"]) ? (int)$L["log_file_max_mb"] : 5;
$log_file_keep = isset($L["log_file_keep"]) ? (int)$L["log_file_keep"] : 3;
if ($sr_port < 1 || $sr_port > 65535) {
	$sr_port = 514;
}
$dbgm = isset($L["debug_minutes"]) ? (int)$L["debug_minutes"] : 0;
if ($dbgm < 0 || $dbgm > 720) {
	$dbgm = 0;
}
$block_quic_ifaces = isset($L["block_quic_interfaces"]) && is_array($L["block_quic_interfaces"])
    ? $L["block_quic_interfaces"] : array();
$block_dot_doq = !empty($L["block_dot_doq"]);
$sni_inspection = !empty($L["sni_inspection"]);
$enforcement_model = (isset($L["enforcement_model"]) &&
    (string)$L["enforcement_model"] === "scoped_hybrid")
    ? "scoped_hybrid" : "legacy_global";
$bp_cfg = layer7_blockpage_config_get($data);
$bp_portal_detected = layer7_blockpage_portal_ip($data);
$bp_domain_info = layer7_blockpage_collect_domains($data);
$cur_lang = isset($L["language"]) ? $L["language"] : "pt";
if (!in_array($cur_lang, array("pt", "en"), true)) {
	$cur_lang = "pt";
}

$configured_real = array();
if (isset($L["interfaces"]) && is_array($L["interfaces"])) {
	foreach ($L["interfaces"] as $x) {
		if (is_string($x) && strlen($x) <= 32) {
			$configured_real[] = $x;
		}
	}
}

$pfsense_ifaces = array();
foreach (layer7_get_pfsense_interfaces() as $ifc) {
	$ifc["checked"] = in_array($ifc["real"], $configured_real, true) ||
	    in_array($ifc["ifid"], $configured_real, true);
	$pfsense_ifaces[] = $ifc;
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Settings"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - definicoes"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("settings"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>
			<form method="post" action="layer7_settings.php#l7-servico" class="form-horizontal">
			<input type="hidden" name="save_scope" value="general" />

			<div class="layer7-admin-block" id="l7-servico">
				<div class="layer7-admin-block__header"><?= l7_t("Configuracao do servico"); ?></div>
				<div class="layer7-admin-block__body">
					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Idioma"); ?> / Language</label>
						<div class="col-sm-9">
							<select name="language" class="form-control" style="max-width: 260px;">
								<option value="pt" <?= $cur_lang === "pt" ? 'selected="selected"' : ""; ?>>Portugues</option>
								<option value="en" <?= $cur_lang === "en" ? 'selected="selected"' : ""; ?>>English</option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Ativar pacote"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="enabled" value="1" <?= $en ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Executar o daemon Layer7"); ?>
							</label>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Modo global"); ?></label>
						<div class="col-sm-9">
							<select name="mode" class="form-control" style="max-width: 260px;">
								<option value="monitor" <?= $mode === "monitor" ? 'selected="selected"' : ""; ?>><?= l7_t("monitor"); ?></option>
								<option value="enforce" <?= $mode === "enforce" ? 'selected="selected"' : ""; ?>><?= l7_t("enforce"); ?></option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Bloquear QUIC (UDP 443)"); ?></label>
						<div class="col-sm-9">
							<?php if (empty($pfsense_ifaces)) { ?>
								<p class="form-control-static text-muted"><?= l7_t("Nenhuma interface configurada no pfSense."); ?></p>
							<?php } else { ?>
							<?php foreach ($pfsense_ifaces as $ifc) { ?>
							<div class="checkbox">
								<label>
									<input type="checkbox" name="block_quic_iface_sel[]"
										value="<?= htmlspecialchars($ifc["ifid"]); ?>"
										<?= in_array($ifc["real"], $block_quic_ifaces, true) ? 'checked="checked"' : ''; ?> />
									<strong><?= htmlspecialchars($ifc["descr"]); ?></strong>
									<span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
								</label>
							</div>
							<?php } ?>
							<?php } ?>
							<p class="help-block"><?= l7_t("Selecione as interfaces onde QUIC (UDP 443) deve ser bloqueado. Vazio = desativado. Forca apps a usar HTTPS/TLS, melhorando a deteccao por SNI."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Bloquear DoT / DoQ (porta 853)"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="block_dot_doq" value="1" <?= $block_dot_doq ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Bloquear DNS-over-TLS / DNS-over-QUIC (TCP/UDP 853) para destinos externos"); ?>
							</label>
							<p class="help-block"><?= l7_t("Desligado por defeito. Activar reforca o anti-bypass DNS, mas pode quebrar 'DNS privado' em Android e algumas apps moveis (incluindo bancos) que dependem de DoT. So activar apos confirmar em laboratorio."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Inspecao por SNI (TLS)"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="sni_inspection" value="1" <?= $sni_inspection ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Usar o SNI (nome do servidor no TLS) e o Host (HTTP) para casar politicas por site"); ?>
							</label>
							<p class="help-block"><?= l7_t("Desligado por defeito. Quando ligado, o motor usa o hostname pedido em cada ligacao (extraido pelo nDPI) em vez de depender so de DNS reverso. Melhora bloqueio em CDNs (ex.: googlevideo) e quando o DNS do cliente esta em cache ou cifrado. Nao usa MITM e nao decifra trafego; nao funciona se o SNI estiver cifrado (TLS 1.3 ECH)."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Modelo de enforcement PF"); ?></label>
						<div class="col-sm-9">
							<select name="enforcement_model" class="form-control" style="max-width: 420px;">
								<option value="legacy_global" <?= $enforcement_model === "legacy_global" ? 'selected="selected"' : ""; ?>><?= l7_t("Legacy global (actual — tabela layer7_block_dst partilhada)"); ?></option>
								<option value="scoped_hybrid" <?= $enforcement_model === "scoped_hybrid" ? 'selected="selected"' : ""; ?>><?= l7_t("Escopado hibrido (experimental — Caminho B; requer blocos E2+)"); ?></option>
							</select>
							<p class="help-block"><?= l7_t("Por defeito: legacy global (recomendado). O modo escopado hibrido e experimental: bloqueia por politica e origem via tabelas layer7_pdst_* / layer7_psrc_*. So IPv4; DoH/DoT/QUIC podem contornar DNS/SNI; politicas app-only exigem flag quarantine_origin para quarentenar origem. Validar em laboratorio antes de produzao."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Pagina de bloqueio"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="block_page_enabled" value="1"
									<?= !empty($bp_cfg["enabled"]) ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Mostrar pagina informativa ao utilizador (requer mode=enforce)"); ?>
							</label>
							<p class="help-block"><?= l7_t("DNS sinkhole via Unbound + pagina HTTP no IP portal. Funciona em HTTP; HTTPS mostra erro de certificado (sem MITM). CDN/QUIC/DoH podem contornar. Desligado por defeito."); ?></p>
							<?php if (!empty($bp_cfg["enabled"]) && $mode !== "enforce") { ?>
							<p class="text-warning"><i class="fa fa-warning"></i> <?= l7_t("A pagina so e activa com Servico ligado e modo enforce."); ?></p>
							<?php } ?>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("IP portal"); ?></label>
						<div class="col-sm-9">
							<input type="text" name="block_page_portal_ip" class="form-control" style="max-width: 220px;"
								value="<?= htmlspecialchars($bp_cfg["portal_ip"]); ?>"
								placeholder="<?= $bp_portal_detected ? htmlspecialchars($bp_portal_detected) : '192.168.1.1'; ?>" />
							<p class="help-block"><?= l7_t("Vazio = auto (primeira interface de captura). Detectado agora:"); ?>
								<code><?= $bp_portal_detected ? htmlspecialchars($bp_portal_detected) : l7_t("indisponivel"); ?></code>
							</p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Titulo da pagina"); ?></label>
						<div class="col-sm-9">
							<input type="text" name="block_page_title" class="form-control" style="max-width: 420px;"
								maxlength="120" value="<?= htmlspecialchars($bp_cfg["title"]); ?>" />
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Mensagem"); ?></label>
						<div class="col-sm-9">
							<textarea name="block_page_message" class="form-control" rows="3" style="max-width: 520px;"
								maxlength="2000"><?= htmlspecialchars($bp_cfg["message"]); ?></textarea>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Contacto admin"); ?></label>
						<div class="col-sm-9">
							<input type="text" name="block_page_contact" class="form-control" style="max-width: 420px;"
								maxlength="500" value="<?= htmlspecialchars($bp_cfg["contact"]); ?>"
								placeholder="suporte@empresa.pt" />
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Detalhes na pagina"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="block_page_show_host" value="1"
									<?= !empty($bp_cfg["show_host"]) ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Mostrar dominio bloqueado"); ?>
							</label>
							<label class="checkbox-inline" style="margin-left:12px;">
								<input type="checkbox" name="block_page_show_policy" value="1"
									<?= !empty($bp_cfg["show_policy"]) ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Mostrar nome da politica"); ?>
							</label>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("DNS forcado (anti-bypass)"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="block_page_force_dns" value="1"
									<?= !empty($bp_cfg["force_dns"]) ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Redireccionar todo o DNS (porta 53) dos clientes para o resolver local"); ?>
							</label>
							<p class="help-block"><?= l7_t("Impede que clientes contornem o sinkhole usando DNS externo (8.8.8.8, 1.1.1.1). Activa tambem anti-DoH no Unbound (NXDOMAIN para resolvers DoH conhecidos + canario Firefox). Recomendado combinar com bloqueio DoT/DoQ (porta 853) e anti-QUIC nas interfaces LAN."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Blacklists UT1"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="block_page_sinkhole_blacklists" value="1"
									<?= !empty($bp_cfg["sinkhole_blacklists"]) ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Incluir dominios de categorias activas no sinkhole"); ?>
							</label>
							<div style="margin-top:8px;">
								<label><?= l7_t("Limite de dominios blacklist"); ?></label>
								<input type="number" name="block_page_blacklist_limit" class="form-control"
									style="max-width:120px; display:inline-block;" min="1" max="4096"
									value="<?= (int)$bp_cfg["blacklist_domain_limit"]; ?>" />
							</div>
							<p class="help-block"><?= l7_t("Politicas activas:"); ?>
								<strong><?= count($bp_domain_info["domains"]); ?></strong>
								<?= l7_t("dominio(s) sinkhole"); ?>
								<?= !empty($bp_domain_info["truncated"]) ? ' — <span class="text-warning">' . l7_t("lista blacklist truncada") . '</span>' : ''; ?>
							</p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Interfaces de captura"); ?></label>
						<div class="col-sm-9">
							<?php if (empty($pfsense_ifaces)) { ?>
								<p class="form-control-static text-muted"><?= l7_t("Nenhuma interface configurada no pfSense."); ?></p>
							<?php } else { ?>
							<?php foreach ($pfsense_ifaces as $ifc) { ?>
							<div class="checkbox">
								<label>
									<input type="checkbox" name="iface_sel[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>"
										<?= $ifc["checked"] ? 'checked="checked"' : ''; ?> />
									<strong><?= htmlspecialchars($ifc["descr"]); ?></strong>
									<span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
								</label>
							</div>
							<?php } ?>
							<?php } ?>
						</div>
					</div>

					<div style="margin-top:12px;">
						<a data-toggle="collapse" href="#l7-logging-advanced" style="cursor:pointer;">
							<i class="fa fa-cog"></i> <?= l7_t("Logging avancado"); ?> <i class="fa fa-chevron-down"></i>
						</a>
					</div>
					<div id="l7-logging-advanced" class="collapse" style="margin-top:12px; padding-top:12px; border-top:1px solid #eee;">
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Nivel de log"); ?></label>
							<div class="col-sm-9">
								<select name="log_level" class="form-control" style="max-width: 260px;">
									<?php foreach (array("error", "warn", "info", "debug") as $v) { ?>
									<option value="<?= htmlspecialchars($v); ?>" <?= $ll === $v ? 'selected="selected"' : ""; ?>><?= htmlspecialchars($v); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Syslog remoto"); ?></label>
							<div class="col-sm-9">
								<label class="checkbox-inline">
									<input type="checkbox" name="syslog_remote" value="1" <?= $sr ? 'checked="checked"' : ""; ?> />
									<?= l7_t("Duplicar eventos por UDP (RFC 3164)"); ?>
								</label>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Host syslog"); ?></label>
							<div class="col-sm-9">
								<input type="text" name="syslog_remote_host" class="form-control" style="max-width: 360px;" maxlength="255"
									value="<?= htmlspecialchars($sr_host); ?>" placeholder="192.168.1.50" />
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Porta UDP"); ?></label>
							<div class="col-sm-9">
								<input type="number" name="syslog_remote_port" class="form-control" style="max-width: 140px;" value="<?= (int)$sr_port; ?>" min="1" max="65535" />
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Janela debug (min)"); ?></label>
							<div class="col-sm-9">
								<input type="number" name="debug_minutes" class="form-control" style="max-width: 140px;" value="<?= (int)$dbgm; ?>" min="0" max="720" />
								<p class="help-block"><?= l7_t("0 = normal. 1-720 para LOG_DEBUG temporario."); ?></p>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Limite por arquivo de log"); ?></label>
							<div class="col-sm-9">
								<div style="display:flex; gap:8px; align-items:center;">
									<input type="number" name="log_file_max_mb" class="form-control"
										style="max-width:140px;" value="<?= (int)$log_file_max_mb; ?>" min="1" max="100" />
									<span class="text-muted">MiB</span>
								</div>
								<p class="help-block"><?= l7_t("Aplica-se separadamente ao log operacional e ao arquivo temporario de eventos."); ?></p>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Copias rotacionadas"); ?></label>
							<div class="col-sm-9">
								<input type="number" name="log_file_keep" class="form-control"
									style="max-width:140px;" value="<?= (int)$log_file_keep; ?>" min="1" max="10" />
								<p class="help-block"><?= l7_t("Quantidade maxima de arquivos antigos mantidos por log."); ?></p>
							</div>
						</div>
					</div>

					<div style="margin-top:16px;">
						<button type="submit" name="save" value="1" class="btn btn-primary"><?= l7_t("Guardar definicoes"); ?></button>
					</div>
				</div>
			</div>
			</form>

			<?php
			$lic_status = layer7_read_license_status();
			$lic_valid = !empty($lic_status["valid"]);
			$lic_expired = !empty($lic_status["expired"]);
			$lic_grace = !empty($lic_status["grace"]);
			$lic_dev = !empty($lic_status["dev_mode"]);
			$lic_clock_suspect = !empty($lic_status["clock_suspect"]);
			$lic_hw = isset($lic_status["hardware_id"]) ? $lic_status["hardware_id"] : "";
			$lic_customer = isset($lic_status["customer"]) ? $lic_status["customer"] : "";
			$lic_expiry = isset($lic_status["expiry"]) ? $lic_status["expiry"] : "";
			$lic_days = isset($lic_status["days_left"]) ? (int)$lic_status["days_left"] : 0;
			$lic_err = isset($lic_status["error"]) ? $lic_status["error"] : "";
			$lic_mask = isset($L["license_key_mask"]) ? trim((string)$L["license_key_mask"]) : "";

			if ($lic_dev) {
				$lic_badge = '<span class="label label-warning">DEV MODE</span>';
			} elseif ($lic_clock_suspect) {
				$lic_badge = '<span class="label label-danger">' . l7_t("Relogio suspeito") . '</span>';
			} elseif ($lic_valid && !$lic_expired) {
				$lic_badge = '<span class="label label-success">' . l7_t("Valida") . '</span>';
			} elseif ($lic_valid && $lic_grace) {
				$lic_badge = '<span class="label label-warning">' . l7_t("Grace period") . '</span>';
			} else {
				$lic_badge = '<span class="label label-danger">' . l7_t("Sem licenca") . '</span>';
			}
			$cs_st = function_exists("layer7_content_subscription_status")
			    ? layer7_content_subscription_status(null, $lic_hw)
			    : array("ok" => false, "status" => "missing", "message" => "");
			if (!empty($cs_st["ok"])) {
				$cs_badge = '<span class="label label-success">' . l7_t("OK") . '</span>';
			} elseif (($cs_st["status"] ?? "") === "expired") {
				$cs_badge = '<span class="label label-warning">' . l7_t("Expirada") . '</span>';
			} elseif (($cs_st["status"] ?? "") === "missing") {
				$cs_badge = '<span class="label label-warning">' . l7_t("Ausente") . '</span>';
			} else {
				$cs_badge = '<span class="label label-danger">' . l7_t("Invalida") . '</span>';
			}
			?>

			<?php
			$rpt_cfg = layer7_reports_config();
			$rpt_en = !empty($rpt_cfg["enabled"]);
			$rpt_ret = (int)($rpt_cfg["retention_days"] ?? 30);
			$rpt_int = (int)($rpt_cfg["collect_interval"] ?? 5);
			$rpt_evt_en = !empty($rpt_cfg["event_log_enabled"]);
			$rpt_evt_ret = (int)($rpt_cfg["event_retention_days"] ?? 7);
			$rpt_evt_max_mb = (int)($rpt_cfg["event_max_mb"] ?? 100);
			$rpt_evt_ifaces = layer7_reports_normalize_interfaces($rpt_cfg["event_interfaces"] ?? array());
			$rpt_presets = array(7, 15, 30, 60, 90, 180, 365);
			$rpt_selected_preset = in_array($rpt_ret, $rpt_presets, true) ? (string)$rpt_ret : "custom";
			$rpt_evt_selected_preset = in_array($rpt_evt_ret, $rpt_presets, true) ? (string)$rpt_evt_ret : "custom";
			?>
			<div class="layer7-admin-block" id="l7-relatorios">
				<div class="layer7-admin-block__header"><?= l7_t("Relatorios"); ?></div>
				<div class="layer7-admin-block__body">
					<form method="post" action="layer7_settings.php#l7-relatorios" class="form-horizontal">
						<input type="hidden" name="save_scope" value="reports">

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Historico executivo"); ?></label>
							<div class="col-sm-9">
								<label class="checkbox-inline">
									<input type="checkbox" name="reports_enabled" <?= $rpt_en ? 'checked' : ''; ?>>
									<?= l7_t("Activar"); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Retencao executivo"); ?></label>
							<div class="col-sm-9">
								<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
									<select class="form-control" name="reports_retention_preset" id="l7_rpt_preset" style="width:180px;" onchange="document.getElementById('l7_rpt_custom').style.display=this.value==='custom'?'inline-block':'none';">
										<?php foreach ($rpt_presets as $rp) { ?>
										<option value="<?= $rp; ?>" <?= $rpt_selected_preset === (string)$rp ? 'selected' : ''; ?>><?= $rp; ?> <?= l7_t("dias"); ?></option>
										<?php } ?>
										<option value="custom" <?= $rpt_selected_preset === "custom" ? 'selected' : ''; ?>><?= l7_t("Personalizado"); ?></option>
									</select>
									<input type="number" class="form-control" name="reports_retention" id="l7_rpt_custom" value="<?= $rpt_ret; ?>" min="1" max="365" style="width:110px;<?= $rpt_selected_preset !== "custom" ? 'display:none;' : ''; ?>">
								</div>
							</div>
						</div>

						<hr style="margin:12px 0;">

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Log detalhado"); ?></label>
							<div class="col-sm-9">
								<label class="checkbox-inline">
									<input type="checkbox" name="reports_event_log_enabled" <?= $rpt_evt_en ? 'checked' : ''; ?>>
									<?= l7_t("Activar"); ?>
								</label>
								<p class="help-block"><?= l7_t("Desligado por defeito. Bloqueios e erros continuam preservados como auditoria essencial."); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Retencao detalhado"); ?></label>
							<div class="col-sm-9">
								<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
									<select class="form-control" name="reports_event_retention_preset" id="l7_evt_preset" style="width:180px;" onchange="document.getElementById('l7_evt_custom').style.display=this.value==='custom'?'inline-block':'none';">
										<?php foreach ($rpt_presets as $rp) { ?>
										<option value="<?= $rp; ?>" <?= $rpt_evt_selected_preset === (string)$rp ? 'selected' : ''; ?>><?= $rp; ?> <?= l7_t("dias"); ?></option>
										<?php } ?>
										<option value="custom" <?= $rpt_evt_selected_preset === "custom" ? 'selected' : ''; ?>><?= l7_t("Personalizado"); ?></option>
									</select>
									<input type="number" class="form-control" name="reports_event_retention" id="l7_evt_custom" value="<?= $rpt_evt_ret; ?>" min="1" max="365" style="width:110px;<?= $rpt_evt_selected_preset !== "custom" ? 'display:none;' : ''; ?>">
								</div>
								<p class="help-block"><?= l7_t("Recomendado: 7 a 15 dias. Bloco que mais cresce em disco."); ?></p>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Limite do banco detalhado"); ?></label>
							<div class="col-sm-9">
								<div style="display:flex; gap:8px; align-items:center;">
									<input type="number" class="form-control" name="reports_event_max_mb"
										value="<?= $rpt_evt_max_mb; ?>" min="25" max="1000" style="width:110px;">
									<span class="text-muted">MiB</span>
								</div>
								<p class="help-block"><?= l7_t("O primeiro limite atingido, dias ou tamanho, dispara o expurgo dos eventos mais antigos."); ?></p>
							</div>
						</div>

						<hr style="margin:12px 0;">

						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Intervalo de recolha"); ?></label>
							<div class="col-sm-9">
								<select class="form-control" name="reports_interval" style="width:150px;">
									<?php foreach (array(5, 10, 15, 30, 60) as $iv) { ?>
									<option value="<?= $iv; ?>" <?= ($rpt_int === $iv) ? 'selected' : ''; ?>><?= $iv; ?> <?= l7_t("minutos"); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>

						<?php if (!empty($pfsense_ifaces)) { ?>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= l7_t("Interfaces do log"); ?></label>
							<div class="col-sm-9">
								<?php foreach ($pfsense_ifaces as $ifc) { ?>
								<div class="checkbox">
									<label>
										<input type="checkbox" name="reports_iface_sel[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>"
											<?= in_array($ifc["real"], $rpt_evt_ifaces, true) ? 'checked="checked"' : ''; ?> />
										<strong><?= htmlspecialchars($ifc["descr"]); ?></strong>
										<span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
									</label>
								</div>
								<?php } ?>
								<p class="help-block"><?= l7_t("Vazio = todas as interfaces capturadas."); ?></p>
							</div>
						</div>
						<?php } ?>

						<input type="hidden" name="save" value="1">
						<div style="margin-top:12px;">
							<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= l7_t("Guardar relatorios"); ?></button>
						</div>
					</form>
				</div>
			</div>

			<div class="layer7-admin-block" id="l7-sistema">
				<div class="layer7-admin-block__header"><?= l7_t("Sistema"); ?></div>
				<div class="layer7-admin-block__body">

				<h4 style="margin-top:0;"><?= l7_t("Licenca"); ?></h4>
				<dl class="dl-horizontal layer7-summary">
					<dt><?= l7_t("Estado"); ?></dt>
					<dd><?= $lic_badge; ?></dd>
					<dt><?= l7_t("Hardware ID"); ?></dt>
					<dd><code style="font-size: 11px; word-break: break-all;"><?= htmlspecialchars($lic_hw); ?></code></dd>
					<?php if ($lic_customer !== "") { ?>
					<dt><?= l7_t("Cliente"); ?></dt>
					<dd><?= htmlspecialchars($lic_customer); ?></dd>
					<?php } ?>
					<?php if ($lic_expiry !== "") { ?>
					<dt><?= l7_t("Expira"); ?></dt>
					<dd><?= htmlspecialchars($lic_expiry); ?>
						<?php if ($lic_days > 0) { ?>
						<small class="text-muted">(<?= $lic_days; ?> <?= l7_t("dias restantes"); ?>)</small>
						<?php } ?>
					</dd>
					<?php } ?>
					<?php if ($lic_clock_suspect && $lic_err !== "") { ?>
					<dt><?= l7_t("Detalhe"); ?></dt>
					<dd><span class="text-danger"><?= htmlspecialchars($lic_err); ?></span>
						<p class="help-block" style="margin-top:6px;">
							<?= l7_t("Relogio do sistema atrasado face a marca observada. Sincronize a hora (NTP) e reinicie o servico layer7d. Ver runbook anti-rollback."); ?>
						</p>
					</dd>
					<?php } elseif (!$lic_valid && $lic_err !== "" && !$lic_dev) { ?>
					<dt><?= l7_t("Detalhe"); ?></dt>
					<dd><span class="text-muted"><?= htmlspecialchars($lic_err); ?></span></dd>
					<?php } ?>
					<dt><?= l7_t("Subscricao de conteudo"); ?></dt>
					<dd><?= $cs_badge; ?>
						<small class="text-muted" style="margin-left:8px;"><?= htmlspecialchars((string)($cs_st["message"] ?? "")); ?></small>
					</dd>
					<?php
					$check_in_on = function_exists("layer7_check_in_effective_enabled")
					    ? layer7_check_in_effective_enabled(array("layer7" => $L))
					    : !empty($L["check_in_enabled"]);
					?>
					<dt><?= l7_t("Check-in online"); ?></dt>
					<dd><?= $check_in_on
					    ? '<span class="label label-success">' . l7_t("Activo") . '</span>'
					    : '<span class="label label-default">' . l7_t("Desactivo") . '</span>'; ?></dd>
				</dl>
				<form method="post" action="layer7_settings.php#l7-sistema" class="form-horizontal" style="margin-bottom:12px;">
					<input type="hidden" name="save_check_in" value="1">
					<div class="form-group" style="margin-bottom:8px;">
						<label class="col-sm-3 control-label"><?= l7_t("Check-in periodico"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="check_in_enabled" <?= $check_in_on ? 'checked' : ''; ?>>
								<?= l7_t("Activar (recomendado — revogacao remota)"); ?>
							</label>
							<p class="help-block">
								<?= l7_t("Activo por defeito. Num appliance sem acesso a Internet, desactive. Uma falha de rede nao desliga o bloqueio enquanto a licenca estiver valida."); ?>
							</p>
							<button type="submit" class="btn btn-sm btn-primary"><?= l7_t("Guardar check-in"); ?></button>
						</div>
					</div>
				</form>
				<?php if ($lic_valid && !$lic_expired && !$lic_dev): ?>
					<form method="post" action="layer7_settings.php#l7-sistema" style="display:inline;">
						<button type="submit" name="revoke_license" value="1" class="btn btn-sm btn-danger"
							onclick="return confirm(<?= json_encode(l7_t('Deseja revogar a licenca activa?')) ?>);">
							<i class="fa fa-ban"></i> <?= l7_t("Revogar licenca"); ?>
						</button>
					</form>
				<?php else: ?>
					<form method="post" action="layer7_settings.php#l7-sistema" style="margin-top:8px;">
						<div class="input-group" style="max-width:400px;">
							<input type="text" name="license_code" class="form-control" maxlength="128" placeholder="ABCD1234EFGH5678">
							<span class="input-group-btn">
								<button type="submit" name="register_license" value="1" class="btn btn-success">
									<i class="fa fa-check"></i> <?= l7_t("Registar"); ?>
								</button>
							</span>
						</div>
					</form>
				<?php endif; ?>

				<hr>

				<h4><?= l7_t("Backup e restore"); ?></h4>
				<?php if ($backup_msg !== "") { ?>
				<div class="alert alert-success"><?= htmlspecialchars($backup_msg); ?></div>
				<?php } ?>
				<?php if ($backup_err !== "") { ?>
				<div class="alert alert-danger"><?= htmlspecialchars($backup_err); ?></div>
				<?php } ?>
				<div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap; margin-bottom:12px;">
					<form method="post" action="layer7_settings.php#l7-sistema" style="display:inline;">
						<button type="submit" name="export_config" value="1" class="btn btn-sm btn-info">
							<i class="fa fa-download"></i> <?= l7_t("Exportar"); ?>
						</button>
					</form>
					<form method="post" action="layer7_settings.php#l7-sistema" enctype="multipart/form-data" style="display:inline-flex; gap:6px; align-items:center;">
						<input type="file" name="import_file" accept=".json" style="display:inline-block; width:auto;" />
						<button type="submit" name="import_config" value="1" class="btn btn-sm btn-warning"
							onclick="return confirm(<?= json_encode(l7_t('Substituir a configuracao actual? Esta accao nao pode ser desfeita.')) ?>);">
							<i class="fa fa-upload"></i> <?= l7_t("Importar"); ?>
						</button>
					</form>
				</div>

				<hr>

				<?php
				$l7_update_js_cfg = array(
					"ajaxUrl" => "/packages/layer7/layer7_settings_ajax.php?action=check_update",
					"checking" => l7_t("A verificar actualizacao..."),
					"httpErr" => l7_t("Erro ao verificar actualizacao (HTTP %d)."),
					"parseErr" => l7_t("Resposta invalida ao verificar actualizacao."),
					"installed" => l7_t("Versao instalada"),
					"latest" => l7_t("Mais recente"),
					"upToDate" => l7_t("Ja esta na versao mais recente."),
					"noPkg" => l7_t("Release encontrado mas sem artefacto .pkg."),
					"updateBtn" => l7_t("Actualizar para "),
					"checkBtn" => l7_t("Verificar actualizacao"),
					"compatBtn" => l7_t("Modo compatibilidade"),
				);
				$l7_update_scroll = ($update_msg !== "" || $update_err !== "" ||
				    isset($_POST["do_update"]) || isset($_POST["check_update"])) ? "1" : "0";
				$l7_update_cfg_json = json_encode($l7_update_js_cfg,
				    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
				if (!is_string($l7_update_cfg_json)) {
					$l7_update_cfg_json = "{}";
				}
				?>
				<div id="l7_pkg_update" data-l7-scroll="<?= $l7_update_scroll ?>"
					data-l7-update-cfg="<?= htmlspecialchars($l7_update_cfg_json, ENT_QUOTES, 'UTF-8') ?>">
				<h4><?= l7_t("Actualizacao"); ?></h4>
				<div id="l7_update_status">
				<?php if ($update_msg !== "") { ?>
				<div class="alert alert-success"><?= htmlspecialchars($update_msg); ?></div>
				<?php } ?>
				<?php if ($update_err !== "") { ?>
				<div class="alert alert-danger"><?= htmlspecialchars($update_err); ?></div>
				<?php } ?>
				</div>

				<?php
				$disp_pkg = layer7_pkg_version();
				$disp_daemon = layer7_daemon_version();
				if ($disp_pkg === "" && $disp_daemon === "") {
					$disp_ver = l7_t("nao instalado");
				} elseif ($disp_pkg !== "") {
					$disp_ver = $disp_pkg;
				} else {
					$disp_ver = $disp_daemon;
				}
				?>
				<p id="l7_update_versions"><?= l7_t("Versao instalada"); ?>: <code><?= htmlspecialchars($disp_ver); ?></code>
				<?php if ($disp_pkg !== "" && $disp_daemon !== "" && $disp_pkg !== $disp_daemon) { ?>
				&nbsp;<small class="text-muted">(<?= l7_t("daemon"); ?>: <code><?= htmlspecialchars($disp_daemon); ?></code>)</small>
				<?php } ?>
				<?php if ($update_info !== null) { ?>
				&nbsp;|&nbsp; <?= l7_t("Mais recente"); ?>: <code><?= htmlspecialchars($update_info["latest"]); ?></code>
				<?php } ?>
				</p>

				<div id="l7_update_actions">
				<?php if ($update_info !== null) { ?>
					<?php if (version_compare($update_info["latest"], $update_info["current"], ">") && $update_info["pkg_url"] !== "") { ?>
					<form method="post" action="layer7_settings.php#l7_pkg_update" style="display:inline;">
						<input type="hidden" name="pkg_url" value="<?= htmlspecialchars($update_info["pkg_url"]); ?>" />
						<button type="submit" name="do_update" value="1" class="btn btn-sm btn-success">
							<i class="fa fa-download"></i>
							<?= l7_t("Actualizar para ") . htmlspecialchars($update_info["latest"]); ?>
						</button>
					</form>
					<?php } elseif ($update_info["pkg_url"] === "") { ?>
					<div class="alert alert-warning"><?= l7_t("Release encontrado mas sem artefacto .pkg."); ?></div>
					<?php } else { ?>
					<span class="text-success"><i class="fa fa-check-circle"></i> <?= l7_t("Ja esta na versao mais recente."); ?></span>
					<?php } ?>
				<?php } ?>
				<button type="button" id="l7_btn_check_update" class="btn btn-sm btn-info" style="margin-left:8px;">
					<i class="fa fa-refresh"></i> <?= l7_t("Verificar actualizacao"); ?>
				</button>
				<noscript>
				<form method="post" action="layer7_settings.php#l7_pkg_update" style="display:inline;">
					<button type="submit" name="check_update" value="1" class="btn btn-sm btn-default" style="margin-left:4px;">
						<?= l7_t("Verificar (POST)"); ?>
					</button>
				</form>
				</noscript>
				<form method="post" action="layer7_settings.php#l7_pkg_update" id="l7_check_update_post" style="display:inline;">
					<button type="submit" name="check_update" value="1" class="btn btn-sm btn-link" style="margin-left:4px; padding:0 4px;">
						<?= l7_t("Modo compatibilidade"); ?>
					</button>
				</form>
				</div>
				</div>

				</div>
			</div>
		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<script src="/packages/layer7/layer7_settings_update.js?v=<?= htmlspecialchars(layer7_pkg_version() !== "" ? layer7_pkg_version() : "1", ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require_once("foot.inc"); ?>
