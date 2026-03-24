<?php
##|+PRIV
##|*IDENT=page-services-layer7-settings
##|*NAME=Services: Layer 7 (settings)
##|*DESCR=Allow access to Layer 7 settings.
##|*MATCH=layer7_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$update_info = null;
$update_msg = "";
$update_err = "";
$backup_msg = "";
$backup_err = "";

if ($_POST["export_config"] ?? false) {
	$data = layer7_load_or_default();
	$export = isset($data["layer7"]) ? $data["layer7"] : array();
	unset($export["protos_file"]);
	$payload = array("layer7_backup" => true, "version" => layer7_daemon_version(), "timestamp" => date("c"), "layer7" => $export);
	$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	header("Content-Type: application/json");
	header("Content-Disposition: attachment; filename=\"layer7-backup-" . date("Ymd-His") . ".json\"");
	echo $json;
	exit;
}

if ($_POST["import_config"] ?? false) {
	if (!isset($_FILES["import_file"]) || $_FILES["import_file"]["error"] !== UPLOAD_ERR_OK) {
		$backup_err = gettext("Nenhum ficheiro enviado ou erro no upload.");
	} else {
		$raw = @file_get_contents($_FILES["import_file"]["tmp_name"]);
		if (!is_string($raw) || $raw === "") {
			$backup_err = gettext("Ficheiro vazio.");
		} else {
			$imported = @json_decode($raw, true);
			if (!is_array($imported)) {
				$backup_err = gettext("JSON invalido.");
			} else {
				$l7_import = null;
				if (isset($imported["layer7"]) && is_array($imported["layer7"])) {
					$l7_import = $imported["layer7"];
				} elseif (isset($imported["layer7_backup"]) && isset($imported["layer7"]) && is_array($imported["layer7"])) {
					$l7_import = $imported["layer7"];
				}
				if ($l7_import === null) {
					$backup_err = gettext("Ficheiro nao contem seccao 'layer7' valida.");
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
					if (layer7_save_json($data)) {
						layer7_signal_reload();
						if (function_exists("filter_configure")) {
							filter_configure();
						}
						$backup_msg = gettext("Configuracao importada com sucesso.");
					} else {
						$backup_err = gettext("Erro ao gravar a configuracao importada.");
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
	$gh_api = "https://api.github.com/repos/pablomichelin/pfsense-layer7/releases/latest";
	$tmp_json = "/tmp/layer7-gh-latest.json";
	@unlink($tmp_json);
	exec("/usr/bin/fetch -qo " . escapeshellarg($tmp_json) . " " . escapeshellarg($gh_api) . " 2>&1", $fetch_out, $fetch_rc);
	if ($fetch_rc !== 0 || !file_exists($tmp_json)) {
		$update_err = gettext("Nao foi possivel contactar o GitHub. Verifique a ligacao a Internet.");
	} else {
		$gh_raw = @file_get_contents($tmp_json);
		$gh = is_string($gh_raw) ? @json_decode($gh_raw, true) : null;
		@unlink($tmp_json);
		if (!is_array($gh) || !isset($gh["tag_name"])) {
			$update_err = gettext("Resposta do GitHub invalida ou repositorio sem releases.");
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
		$update_err = gettext("URL do pacote invalida.");
	} else {
		$pkg_file = "/tmp/layer7-update.pkg";
		@unlink($pkg_file);

		exec("service layer7d onestop 2>&1", $stop_out, $stop_rc);
		exec("/usr/bin/fetch -qo " . escapeshellarg($pkg_file) . " " . escapeshellarg($pkg_url) . " 2>&1", $dl_out, $dl_rc);
		if ($dl_rc !== 0 || !file_exists($pkg_file)) {
			$update_err = gettext("Falha ao baixar o pacote do GitHub.");
			exec("service layer7d onestart 2>&1");
		} else {
			exec("IGNORE_OSVERSION=yes /usr/sbin/pkg add -f " . escapeshellarg($pkg_file) . " 2>&1", $inst_out, $inst_rc);
			@unlink($pkg_file);
			if ($inst_rc !== 0) {
				$update_err = gettext("Falha na instalacao do pacote: ") . implode(" ", $inst_out);
			} else {
				exec("service layer7d onestart 2>&1");
				sleep(1);
				$new_ver = layer7_daemon_version();
				$update_msg = gettext("Pacote actualizado com sucesso para a versao ") . ($new_ver !== "" ? $new_ver : "nova") . ".";
			}
		}
	}
}

if ($_POST["save"] ?? false) {
	$mode = $_POST["mode"] ?? "monitor";
	if (!in_array($mode, array("monitor", "enforce"), true)) {
		$mode = "monitor";
	}
	$log_level = $_POST["log_level"] ?? "info";
	if (!in_array($log_level, array("error", "warn", "info", "debug"), true)) {
		$log_level = "info";
	}
	$enabled = isset($_POST["enabled"]);
	$syslog_remote = isset($_POST["syslog_remote"]);
	$sr_host = trim($_POST["syslog_remote_host"] ?? "");
	$sr_port = (int)($_POST["syslog_remote_port"] ?? 514);
	if ($sr_port < 1 || $sr_port > 65535) {
		$sr_port = 514;
	}
	if ($syslog_remote && $sr_host === "") {
		$input_errors[] = gettext("Syslog remoto: indique o host ou desative a opcao.");
	}
	if ($syslog_remote && $sr_host !== "" && !layer7_syslog_remote_host_valid($sr_host)) {
		$input_errors[] = gettext("Host syslog: use IPv4 ou hostname valido.");
	}

	$selected_ifaces = array();
	if (isset($_POST["iface_sel"]) && is_array($_POST["iface_sel"])) {
		foreach ($_POST["iface_sel"] as $ifid) {
			if (is_string($ifid) && preg_match('/^[a-zA-Z0-9_.]+$/', $ifid)) {
				$real = convert_friendly_interface_to_real_interface_name($ifid);
				$selected_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
			}
		}
	}
	if (count($selected_ifaces) > 8) {
		$input_errors[] = gettext("Maximo de 8 interfaces.");
	}

	if (empty($input_errors)) {
		$data = layer7_load_or_default();
		$data["layer7"]["enabled"] = $enabled;
		$data["layer7"]["mode"] = $mode;
		$data["layer7"]["log_level"] = $log_level;
		$data["layer7"]["syslog_remote"] = $syslog_remote;
		$data["layer7"]["syslog_remote_host"] = $sr_host;
		$data["layer7"]["syslog_remote_port"] = $sr_port;
		$dbgm = (int)($_POST["debug_minutes"] ?? 0);
		if ($dbgm < 0) {
			$dbgm = 0;
		}
		if ($dbgm > 720) {
			$dbgm = 720;
		}
		$data["layer7"]["debug_minutes"] = $dbgm;
		$data["layer7"]["interfaces"] = array_values(array_unique($selected_ifaces));

		$old_block_quic = !empty($data["layer7"]["block_quic"]);
		$data["layer7"]["block_quic"] = isset($_POST["block_quic"]);

		if (layer7_save_json($data)) {
			layer7_signal_reload();
			if ($old_block_quic !== $data["layer7"]["block_quic"]) {
				if (function_exists("filter_configure")) {
					filter_configure();
				}
			}
			$savemsg = gettext("Configuracao gravada. SIGHUP enviado ao layer7d se o servico estiver em execucao.");
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

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Settings"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - definicoes"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("settings"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>
			<p class="layer7-lead"><?= gettext("Parametros basicos do daemon, logging remoto e reservas de interface para a fase de captura nDPI."); ?></p>

			<form method="post" class="form-horizontal">

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Ativar pacote"); ?></label>
				<div class="col-sm-9">
					<label class="checkbox-inline">
						<input type="checkbox" name="enabled" value="1" <?= $en ? 'checked="checked"' : ""; ?> />
						<?= gettext("Executar o daemon Layer7"); ?>
					</label>
					<p class="help-block"><?= gettext("Quando desmarcado, o layer7d permanece em idle para permitir validacao segura da GUI e do pacote."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Modo global"); ?></label>
				<div class="col-sm-9">
					<select name="mode" class="form-control" style="max-width: 260px;">
						<option value="monitor" <?= $mode === "monitor" ? 'selected="selected"' : ""; ?>><?= gettext("monitor"); ?></option>
						<option value="enforce" <?= $mode === "enforce" ? 'selected="selected"' : ""; ?>><?= gettext("enforce"); ?></option>
					</select>
					<p class="help-block"><?= gettext("Monitor apenas observa e regista. Enforce prepara o caminho para acoes reais quando o loop de classificacao estiver ativo."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Nivel de log"); ?></label>
				<div class="col-sm-9">
					<select name="log_level" class="form-control" style="max-width: 260px;">
						<?php foreach (array("error", "warn", "info", "debug") as $v) { ?>
						<option value="<?= htmlspecialchars($v); ?>" <?= $ll === $v ? 'selected="selected"' : ""; ?>><?= htmlspecialchars($v); ?></option>
						<?php } ?>
					</select>
					<p class="help-block"><?= gettext("Define a verbosidade do daemon no syslog local e, se ativo, no syslog remoto."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Syslog remoto"); ?></label>
				<div class="col-sm-9">
					<label class="checkbox-inline">
						<input type="checkbox" name="syslog_remote" value="1" <?= $sr ? 'checked="checked"' : ""; ?> />
						<?= gettext("Duplicar eventos por UDP (RFC 3164)"); ?>
					</label>
					<p class="help-block"><?= gettext("Use um coletor do lab para validar eventos do daemon fora do syslog local do pfSense."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Host syslog"); ?></label>
				<div class="col-sm-9">
					<input type="text" name="syslog_remote_host" class="form-control" style="max-width: 360px;" maxlength="255"
						value="<?= htmlspecialchars($sr_host); ?>" placeholder="192.168.1.50" />
					<p class="help-block"><?= gettext("Aceita IPv4 ou hostname simples. Deixe vazio se o envio remoto estiver desativado."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Porta UDP"); ?></label>
				<div class="col-sm-9">
					<input type="number" name="syslog_remote_port" class="form-control" style="max-width: 140px;" value="<?= (int)$sr_port; ?>" min="1" max="65535" />
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Janela debug (min)"); ?></label>
				<div class="col-sm-9">
					<input type="number" name="debug_minutes" class="form-control" style="max-width: 140px;" value="<?= (int)$dbgm; ?>" min="0" max="720" />
					<p class="help-block"><?= gettext("0 = normal. Entre 1 e 720 para elevar temporariamente o daemon a LOG_DEBUG apos cada reload."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Bloquear QUIC"); ?></label>
				<div class="col-sm-9">
					<label class="checkbox-inline">
						<input type="checkbox" name="block_quic" value="1" <?= $block_quic ? 'checked="checked"' : ""; ?> />
						<?= gettext("Bloquear QUIC (UDP 443) globalmente"); ?>
					</label>
					<p class="help-block"><?= gettext("Forca aplicacoes a usar HTTPS (TCP 443) em vez de QUIC, onde o SNI e visivel ao nDPI. Melhora significativamente a eficacia do bloqueio por DNS/SNI. Adiciona regra PF: block drop quick proto udp to port 443."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<label class="col-sm-3 control-label"><?= gettext("Interfaces de captura"); ?></label>
				<div class="col-sm-9">
					<?php if (empty($pfsense_ifaces)) { ?>
						<p class="form-control-static text-muted"><?= gettext("Nenhuma interface configurada no pfSense."); ?></p>
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
					<p class="help-block"><?= gettext("Selecione as interfaces onde o Layer7 ira capturar e classificar trafego via nDPI. Maximo 8."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-9">
					<button type="submit" name="save" value="1" class="btn btn-primary"><?= gettext("Guardar definicoes"); ?></button>
				</div>
			</div>
			</form>

			<p class="layer7-muted-note small"><?= gettext("Politicas e excecoes existentes sao preservadas quando as definicoes globais sao gravadas."); ?></p>

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

			if ($lic_dev) {
				$lic_badge = '<span class="label label-warning">DEV MODE</span>';
				$lic_desc = gettext("Chave de desenvolvimento — sem verificacao de licenca. Substitua a chave publica no binario antes de distribuir.");
			} elseif ($lic_valid && !$lic_expired) {
				$lic_badge = '<span class="label label-success">' . gettext("Valida") . '</span>';
				$lic_desc = sprintf(gettext("Licenca activa. Expira em %s (%d dias restantes)."), htmlspecialchars($lic_expiry), $lic_days);
			} elseif ($lic_valid && $lic_grace) {
				$lic_badge = '<span class="label label-warning">' . gettext("Grace period") . '</span>';
				$lic_desc = sprintf(gettext("Licenca expirada em %s. Periodo de graca activo (%d dias restantes)."), htmlspecialchars($lic_expiry), 14 + $lic_days);
			} else {
				$lic_badge = '<span class="label label-danger">' . gettext("Sem licenca") . '</span>';
				$lic_desc = gettext("Sem licenca valida. O daemon opera apenas em modo monitor.") . ($lic_err !== "" ? " " . htmlspecialchars($lic_err) : "");
			}
			?>

			<div class="layer7-section" style="margin-top: 36px;">
				<h3 class="layer7-section-title"><?= gettext("Licenca"); ?></h3>
				<div class="layer7-callout">
					<dl class="dl-horizontal layer7-summary">
						<dt><?= gettext("Estado"); ?></dt>
						<dd><?= $lic_badge; ?></dd>

						<dt><?= gettext("Hardware ID"); ?></dt>
						<dd><code style="font-size: 11px; word-break: break-all;"><?= htmlspecialchars($lic_hw); ?></code></dd>

						<?php if ($lic_customer !== "") { ?>
						<dt><?= gettext("Cliente"); ?></dt>
						<dd><?= htmlspecialchars($lic_customer); ?></dd>
						<?php } ?>

						<?php if ($lic_expiry !== "") { ?>
						<dt><?= gettext("Expira"); ?></dt>
						<dd><?= htmlspecialchars($lic_expiry); ?>
							<?php if ($lic_days > 0) { ?>
							<small class="text-muted">(<?= $lic_days; ?> <?= gettext("dias restantes"); ?>)</small>
							<?php } ?>
						</dd>
						<?php } ?>
					</dl>

					<p class="text-muted small"><?= $lic_desc; ?></p>

					<p class="text-muted small" style="margin-top: 12px;">
						<?= gettext("Para activar uma licenca: no terminal do pfSense execute"); ?>
						<code>layer7d --activate CHAVE</code>
						<?= gettext("ou coloque o ficheiro"); ?> <code>/usr/local/etc/layer7.lic</code> <?= gettext("manualmente."); ?>
					</p>
				</div>
			</div>

			<div class="layer7-section" style="margin-top: 36px;">
				<h3 class="layer7-section-title"><?= gettext("Backup e restore"); ?></h3>
				<div class="layer7-callout">

				<?php if ($backup_msg !== "") { ?>
				<div class="alert alert-success"><?= htmlspecialchars($backup_msg); ?></div>
				<?php } ?>
				<?php if ($backup_err !== "") { ?>
				<div class="alert alert-danger"><?= htmlspecialchars($backup_err); ?></div>
				<?php } ?>

				<p><?= gettext("Exporte toda a configuracao Layer7 (definicoes, politicas, excepcoes, grupos) como ficheiro JSON. Importe noutro pfSense ou para restaurar uma configuracao anterior."); ?></p>

				<form method="post" style="margin-bottom: 18px;">
					<button type="submit" name="export_config" value="1" class="btn btn-info">
						<i class="fa fa-download"></i> <?= gettext("Exportar configuracao"); ?>
					</button>
				</form>

				<form method="post" enctype="multipart/form-data">
					<div class="form-group">
						<label><?= gettext("Importar configuracao"); ?></label>
						<input type="file" name="import_file" accept=".json" />
						<p class="help-block"><?= gettext("Selecione um ficheiro JSON exportado anteriormente. A configuracao actual sera substituida."); ?></p>
					</div>
					<button type="submit" name="import_config" value="1" class="btn btn-warning"
						onclick="return confirm('<?= gettext("Substituir a configuracao actual? Esta accao nao pode ser desfeita."); ?>');">
						<i class="fa fa-upload"></i> <?= gettext("Importar"); ?>
					</button>
				</form>

				</div>
			</div>

			<div class="layer7-section" style="margin-top: 36px;">
				<h3 class="layer7-section-title"><?= gettext("Actualizacao do pacote"); ?></h3>
				<div class="layer7-callout">

				<?php if ($update_msg !== "") { ?>
				<div class="alert alert-success"><?= htmlspecialchars($update_msg); ?></div>
				<?php } ?>
				<?php if ($update_err !== "") { ?>
				<div class="alert alert-danger"><?= htmlspecialchars($update_err); ?></div>
				<?php } ?>

				<?php
				$disp_ver = layer7_daemon_version();
				if ($disp_ver === "") { $disp_ver = gettext("nao instalado"); }
				?>

				<dl class="dl-horizontal layer7-summary">
					<dt><?= gettext("Versao instalada"); ?></dt>
					<dd><code><?= htmlspecialchars($disp_ver); ?></code></dd>
				</dl>

				<?php if ($update_info !== null) { ?>
				<dl class="dl-horizontal layer7-summary">
					<dt><?= gettext("Versao mais recente"); ?></dt>
					<dd>
						<code><?= htmlspecialchars($update_info["latest"]); ?></code>
						<small class="text-muted"> — <?= htmlspecialchars($update_info["name"]); ?></small>
					</dd>
				</dl>

				<?php if (version_compare($update_info["latest"], $update_info["current"], ">") && $update_info["pkg_url"] !== "") { ?>
				<form method="post" style="margin-top: 12px;">
					<input type="hidden" name="pkg_url" value="<?= htmlspecialchars($update_info["pkg_url"]); ?>" />
					<button type="submit" name="do_update" value="1" class="btn btn-success"
						onclick="return confirm('<?= gettext("Actualizar o pacote Layer7? O daemon sera reiniciado."); ?>');">
						<i class="fa fa-download"></i>
						<?= gettext("Actualizar para ") . htmlspecialchars($update_info["latest"]); ?>
					</button>
					<p class="help-block" style="margin-top: 8px;">
						<?= gettext("O daemon sera parado, o pacote substituido e o daemon reiniciado. As politicas e configuracoes sao preservadas."); ?>
					</p>
				</form>
				<?php } elseif ($update_info["pkg_url"] === "") { ?>
				<div class="alert alert-warning" style="margin-top: 12px;"><?= gettext("Release encontrado mas sem artefacto .pkg. Verifique o GitHub."); ?></div>
				<?php } else { ?>
				<div class="alert alert-info" style="margin-top: 12px;">
					<i class="fa fa-check-circle"></i> <?= gettext("Ja esta na versao mais recente."); ?>
				</div>
				<?php } ?>
				<?php } ?>

				<form method="post" style="margin-top: 12px;">
					<button type="submit" name="check_update" value="1" class="btn btn-info">
						<i class="fa fa-refresh"></i> <?= gettext("Verificar actualizacao"); ?>
					</button>
				</form>

				<p class="layer7-muted-note small" style="margin-top: 12px;">
					<?= gettext("Verifica a ultima versao publicada no GitHub Releases. As politicas, excecoes e configuracoes existentes sao sempre preservadas durante a actualizacao."); ?>
				</p>
				</div>
			</div>
		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
