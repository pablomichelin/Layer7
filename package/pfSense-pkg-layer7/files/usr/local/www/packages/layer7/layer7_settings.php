<?php
##|+PRIV
##|*IDENT=page-services-layer7-settings
##|*NAME=Services: Layer 7 (settings)
##|*DESCR=Allow access to Layer 7 settings.
##|*MATCH=layer7_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

if ($_POST["save"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token de formulário inválido — atualize a página.");
	} else {
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
			$input_errors[] = gettext("Syslog remoto: indique o host ou desative a opção.");
		}
		if ($syslog_remote && $sr_host !== "" && !layer7_syslog_remote_host_valid($sr_host)) {
			$input_errors[] = gettext("Host syslog: use IPv4 (ex.: 192.168.1.50) ou hostname válido (letras, números, pontos, hífens).");
		}

		$iflist = layer7_parse_interfaces_csv($_POST["interfaces_csv"] ?? "", 8);
		if ($iflist === null) {
			$input_errors[] = gettext("Interfaces: até 8 nomes (ex.: lan, opt1) separados por vírgula; apenas letras, números, _ e .");
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
			$data["layer7"]["interfaces"] = $iflist;

			if (layer7_save_json($data)) {
				layer7_csrf_rotate();
				layer7_signal_reload();
				$savemsg = gettext("Configuração gravada. SIGHUP ao layer7d se em execução.");
			}
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

$ifarr = array();
if (isset($L["interfaces"]) && is_array($L["interfaces"])) {
	foreach ($L["interfaces"] as $x) {
		if (is_string($x) && strlen($x) <= 32 && preg_match('/^[a-zA-Z0-9_.]+$/', $x)) {
			$ifarr[] = $x;
		}
	}
}
$interfaces_csv = implode(", ", $ifarr);

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Settings"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 — definições"); ?></h2>
	</div>
	<div class="panel-body">
		<p><a href="layer7_status.php"><?= gettext("← Estado"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_policies.php"><?= gettext("Políticas"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_exceptions.php"><?= gettext("Exceções"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_events.php"><?= gettext("Events"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_diagnostics.php"><?= gettext("Diagnostics"); ?></a></p>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ativar"); ?></label>
				<div class="col-sm-10">
					<input type="checkbox" name="enabled" value="1" <?= $en ? "checked=\"checked\"" : ""; ?> />
					<span class="help-block"><?= gettext("layer7.enabled — desmarcado → daemon em idle."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Modo"); ?></label>
				<div class="col-sm-10">
					<select name="mode" class="form-control" style="max-width:200px;">
						<option value="monitor" <?= $mode === "monitor" ? "selected" : ""; ?>><?= gettext("monitor"); ?></option>
						<option value="enforce" <?= $mode === "enforce" ? "selected" : ""; ?>><?= gettext("enforce"); ?></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Log level"); ?></label>
				<div class="col-sm-10">
					<select name="log_level" class="form-control" style="max-width:200px;">
						<?php foreach (array("error", "warn", "info", "debug") as $v) { ?>
						<option value="<?= htmlspecialchars($v); ?>" <?= $ll === $v ? "selected" : ""; ?>><?= htmlspecialchars($v); ?></option>
						<?php } ?>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Syslog remoto"); ?></label>
				<div class="col-sm-10">
					<input type="checkbox" name="syslog_remote" value="1" <?= $sr ? "checked=\"checked\"" : ""; ?> />
					<span class="help-block"><?= gettext("Duplicar logs do daemon por UDP (RFC 3164) para o host abaixo (IPv4 ou hostname)."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Host syslog"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="syslog_remote_host" class="form-control" style="max-width:320px;" maxlength="255"
						value="<?= htmlspecialchars($sr_host); ?>" placeholder="192.168.1.50" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Porta UDP"); ?></label>
				<div class="col-sm-10">
					<input type="number" name="syslog_remote_port" class="form-control" style="max-width:120px;" value="<?= (int)$sr_port; ?>" min="1" max="65535" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Debug (min)"); ?></label>
				<div class="col-sm-10">
					<input type="number" name="debug_minutes" class="form-control" style="max-width:120px;" value="<?= (int)$dbgm; ?>" min="0" max="720" />
					<span class="help-block"><?= gettext("0 = normal. 1–720 = forçar LOG_DEBUG no daemon durante N minutos após cada reload (SIGHUP)."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>interfaces</code></label>
				<div class="col-sm-10">
					<input type="text" name="interfaces_csv" class="form-control" style="max-width:480px;" maxlength="320"
						value="<?= htmlspecialchars($interfaces_csv); ?>" placeholder="lan, opt1" />
					<span class="help-block"><?= gettext("Até 8 nomes de interface (pfSense: lan, wan, opt1, …). Vazio = lista vazia. Reservado para nDPI."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="save" value="1" class="btn btn-primary"><?= gettext("Guardar"); ?></button>
				</div>
			</div>
		</form>
		<p class="text-muted small"><?= gettext("Políticas/exceções mantêm-se ao gravar."); ?></p>
	</div>
</div>
<?php require_once("foot.inc"); ?>
