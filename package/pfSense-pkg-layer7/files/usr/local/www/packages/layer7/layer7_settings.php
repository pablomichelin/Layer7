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
	$bl_export = layer7_bl_config_load();
	$payload = array(
		"layer7_backup" => true,
		"version" => layer7_daemon_version(),
		"timestamp" => date("c"),
		"layer7" => $export,
		"blacklists" => $bl_export
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
						layer7_bl_config_save($imported["blacklists"]);
						layer7_bl_sync_custom_category_files($imported["blacklists"]);
					}
					if ($save_ok) {
						layer7_signal_reload();
						if (function_exists("filter_configure")) {
							filter_configure();
						}
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
	$current_ver = layer7_daemon_version();
	if ($current_ver === "") {
		$current_ver = "desconhecida";
	}
	$gh_api = "https://api.github.com/repos/" . $layer7_release_owner . "/" . $layer7_release_repo . "/releases/latest";
	$tmp_json = "/tmp/layer7-gh-latest.json";
	@unlink($tmp_json);
	exec("/usr/bin/fetch -qo " . escapeshellarg($tmp_json) . " " . escapeshellarg($gh_api) . " 2>&1", $fetch_out, $fetch_rc);
	if ($fetch_rc !== 0 || !file_exists($tmp_json)) {
		$update_err = l7_t("Nao foi possivel contactar o GitHub. Verifique a ligacao a Internet.");
	} else {
		$gh_raw = @file_get_contents($tmp_json);
		$gh = is_string($gh_raw) ? @json_decode($gh_raw, true) : null;
		@unlink($tmp_json);
		if (!is_array($gh) || !isset($gh["tag_name"])) {
			$update_err = l7_t("Resposta do GitHub invalida ou repositorio sem releases.");
		} else {
			$latest_tag = $gh["tag_name"];
			$latest_ver = ltrim($latest_tag, "vV");
			$pkg_url = "";
			if (isset($gh["assets"]) && is_array($gh["assets"])) {
				foreach ($gh["assets"] as $asset) {
					if (isset($asset["browser_download_url"]) && strpos($asset["browser_download_url"], ".pkg") !== false) {
						$pkg_url = $asset["browser_download_url"];
						break;
					}
				}
			}
			$update_info = array(
				"current" => $current_ver,
				"latest" => $latest_ver,
				"tag" => $latest_tag,
				"pkg_url" => $pkg_url,
				"name" => isset($gh["name"]) ? $gh["name"] : $latest_tag
			);
		}
	}
}

if ($_POST["do_update"] ?? false) {
	$pkg_url = isset($_POST["pkg_url"]) ? trim($_POST["pkg_url"]) : "";
	if ($pkg_url === "" || strpos($pkg_url, "https://github.com/") !== 0) {
		$update_err = l7_t("URL do pacote invalida.");
	} else {
		$pkg_file = "/tmp/layer7-update.pkg";
		@unlink($pkg_file);

		exec("service layer7d onestop 2>&1", $stop_out, $stop_rc);
		exec("/usr/bin/fetch -qo " . escapeshellarg($pkg_file) . " " . escapeshellarg($pkg_url) . " 2>&1", $dl_out, $dl_rc);
		if ($dl_rc !== 0 || !file_exists($pkg_file)) {
			$update_err = l7_t("Falha ao baixar o pacote do GitHub.");
			exec("service layer7d onestart 2>&1");
		} else {
			exec("IGNORE_OSVERSION=yes /usr/sbin/pkg add -f " . escapeshellarg($pkg_file) . " 2>&1", $inst_out, $inst_rc);
			@unlink($pkg_file);
			if ($inst_rc !== 0) {
				$update_err = l7_t("Falha na instalacao do pacote: ") . implode(" ", $inst_out);
			} else {
				exec("service layer7d onestart 2>&1");
				sleep(1);
				$new_ver = layer7_daemon_version();
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
		$out = array();
		$rc = 0;
		exec("/usr/local/sbin/layer7d --activate " . escapeshellarg($license_code) . " 2>&1", $out, $rc);
		if ($rc === 0) {
			$data = layer7_load_or_default();
			$data["layer7"]["license_key_mask"] = substr($license_code, 0, 5) . "************";
			if (layer7_save_json($data)) {
				layer7_restart_service();
				$savemsg = l7_t("Licenca registada com sucesso.");
			}
		} else {
			$input_errors[] = l7_t("Licenca invalida.");
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
				$real = convert_friendly_interface_to_real_interface_name($ifid);
				$selected_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
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

	$block_quic = $is_general_save ? isset($_POST["block_quic"]) :
	    !empty($current_l7["block_quic"]);

	$rpt_enabled = !empty($current_reports["enabled"]);
	$rpt_retention = (int)($current_reports["retention_days"] ?? 30);
	$rpt_interval = (int)($current_reports["collect_interval"] ?? 5);
	$rpt_event_enabled = !empty($current_reports["event_log_enabled"]);
	$rpt_event_retention = (int)($current_reports["event_retention_days"] ?? 15);
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

		$rpt_event_ifaces = array();
		if (isset($_POST["reports_iface_sel"]) && is_array($_POST["reports_iface_sel"])) {
			foreach ($_POST["reports_iface_sel"] as $ifid) {
				if (is_string($ifid) && preg_match('/^[a-zA-Z0-9_.]+$/', $ifid)) {
					$real = convert_friendly_interface_to_real_interface_name($ifid);
					$rpt_event_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
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
		$data["layer7"]["syslog_remote"] = $syslog_remote;
		$data["layer7"]["syslog_remote_host"] = $sr_host;
		$data["layer7"]["syslog_remote_port"] = $sr_port;
		$data["layer7"]["debug_minutes"] = $dbgm;
		$data["layer7"]["interfaces"] = array_values(array_unique($selected_ifaces));

		$old_block_quic = !empty($current_l7["block_quic"]);
		$data["layer7"]["block_quic"] = $block_quic;

		$data["layer7"]["reports"] = array(
			"enabled" => $rpt_enabled,
			"retention_days" => $rpt_retention,
			"collect_interval" => $rpt_interval,
			"event_log_enabled" => $rpt_event_enabled,
			"event_retention_days" => $rpt_event_retention,
			"event_interfaces" => $rpt_event_ifaces
		);

		if (layer7_save_json($data)) {
			layer7_signal_reload();
			if ($old_block_quic !== $data["layer7"]["block_quic"]) {
				if (function_exists("filter_configure")) {
					filter_configure();
				}
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
if ($sr_port < 1 || $sr_port > 65535) {
	$sr_port = 514;
}
$dbgm = isset($L["debug_minutes"]) ? (int)$L["debug_minutes"] : 0;
if ($dbgm < 0 || $dbgm > 720) {
	$dbgm = 0;
}
$block_quic = !empty($L["block_quic"]);
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
	$ifc["checked"] = in_array($ifc["real"], $configured_real, true);
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
			<form method="post" class="form-horizontal">
			<input type="hidden" name="save_scope" value="general" />

			<div class="layer7-admin-block">
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
						<label class="col-sm-3 control-label"><?= l7_t("Bloquear QUIC"); ?></label>
						<div class="col-sm-9">
							<label class="checkbox-inline">
								<input type="checkbox" name="block_quic" value="1" <?= $block_quic ? 'checked="checked"' : ""; ?> />
								<?= l7_t("Bloquear QUIC (UDP 443) globalmente"); ?>
							</label>
							<p class="help-block"><?= l7_t("Forca apps a usar HTTPS em vez de QUIC, melhorando a deteccao por SNI."); ?></p>
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
			$lic_hw = isset($lic_status["hardware_id"]) ? $lic_status["hardware_id"] : "";
			$lic_customer = isset($lic_status["customer"]) ? $lic_status["customer"] : "";
			$lic_expiry = isset($lic_status["expiry"]) ? $lic_status["expiry"] : "";
			$lic_days = isset($lic_status["days_left"]) ? (int)$lic_status["days_left"] : 0;
			$lic_err = isset($lic_status["error"]) ? $lic_status["error"] : "";
			$lic_mask = isset($L["license_key_mask"]) ? trim((string)$L["license_key_mask"]) : "";

			if ($lic_dev) {
				$lic_badge = '<span class="label label-warning">DEV MODE</span>';
			} elseif ($lic_valid && !$lic_expired) {
				$lic_badge = '<span class="label label-success">' . l7_t("Valida") . '</span>';
			} elseif ($lic_valid && $lic_grace) {
				$lic_badge = '<span class="label label-warning">' . l7_t("Grace period") . '</span>';
			} else {
				$lic_badge = '<span class="label label-danger">' . l7_t("Sem licenca") . '</span>';
			}
			?>

			<?php
			$rpt_cfg = layer7_reports_config();
			$rpt_en = !empty($rpt_cfg["enabled"]);
			$rpt_ret = (int)($rpt_cfg["retention_days"] ?? 30);
			$rpt_int = (int)($rpt_cfg["collect_interval"] ?? 5);
			$rpt_evt_en = !empty($rpt_cfg["event_log_enabled"]);
			$rpt_evt_ret = (int)($rpt_cfg["event_retention_days"] ?? 15);
			$rpt_evt_ifaces = layer7_reports_normalize_interfaces($rpt_cfg["event_interfaces"] ?? array());
			$rpt_presets = array(7, 15, 30, 60, 90, 180, 365);
			$rpt_selected_preset = in_array($rpt_ret, $rpt_presets, true) ? (string)$rpt_ret : "custom";
			$rpt_evt_selected_preset = in_array($rpt_evt_ret, $rpt_presets, true) ? (string)$rpt_evt_ret : "custom";
			?>
			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header"><?= l7_t("Relatorios"); ?></div>
				<div class="layer7-admin-block__body">
					<form method="post" class="form-horizontal">
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

			<div class="layer7-admin-block">
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
				</dl>
				<?php if ($lic_valid && !$lic_expired && !$lic_dev): ?>
					<form method="post" style="display:inline;">
						<button type="submit" name="revoke_license" value="1" class="btn btn-sm btn-danger"
							onclick="return confirm(<?= json_encode(l7_t('Deseja revogar a licenca activa?')) ?>);">
							<i class="fa fa-ban"></i> <?= l7_t("Revogar licenca"); ?>
						</button>
					</form>
				<?php else: ?>
					<form method="post" style="margin-top:8px;">
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
					<form method="post" style="display:inline;">
						<button type="submit" name="export_config" value="1" class="btn btn-sm btn-info">
							<i class="fa fa-download"></i> <?= l7_t("Exportar"); ?>
						</button>
					</form>
					<form method="post" enctype="multipart/form-data" style="display:inline-flex; gap:6px; align-items:center;">
						<input type="file" name="import_file" accept=".json" style="display:inline-block; width:auto;" />
						<button type="submit" name="import_config" value="1" class="btn btn-sm btn-warning"
							onclick="return confirm(<?= json_encode(l7_t('Substituir a configuracao actual? Esta accao nao pode ser desfeita.')) ?>);">
							<i class="fa fa-upload"></i> <?= l7_t("Importar"); ?>
						</button>
					</form>
				</div>

				<hr>

				<h4><?= l7_t("Actualizacao"); ?></h4>
				<?php if ($update_msg !== "") { ?>
				<div class="alert alert-success"><?= htmlspecialchars($update_msg); ?></div>
				<?php } ?>
				<?php if ($update_err !== "") { ?>
				<div class="alert alert-danger"><?= htmlspecialchars($update_err); ?></div>
				<?php } ?>

				<?php
				$disp_ver = layer7_daemon_version();
				if ($disp_ver === "") { $disp_ver = l7_t("nao instalado"); }
				?>
				<p><?= l7_t("Versao instalada"); ?>: <code><?= htmlspecialchars($disp_ver); ?></code>
				<?php if ($update_info !== null) { ?>
				&nbsp;|&nbsp; <?= l7_t("Mais recente"); ?>: <code><?= htmlspecialchars($update_info["latest"]); ?></code>
				<?php } ?>
				</p>

				<?php if ($update_info !== null) { ?>
					<?php if (version_compare($update_info["latest"], $update_info["current"], ">") && $update_info["pkg_url"] !== "") { ?>
					<form method="post" style="display:inline;">
						<input type="hidden" name="pkg_url" value="<?= htmlspecialchars($update_info["pkg_url"]); ?>" />
						<button type="submit" name="do_update" value="1" class="btn btn-sm btn-success"
							onclick="return confirm(<?= json_encode(l7_t('Actualizar o pacote Layer7? O daemon sera reiniciado.')) ?>);">
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
				<form method="post" style="display:inline; margin-left:8px;">
					<button type="submit" name="check_update" value="1" class="btn btn-sm btn-info">
						<i class="fa fa-refresh"></i> <?= l7_t("Verificar actualizacao"); ?>
					</button>
				</form>

				</div>
			</div>
		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
