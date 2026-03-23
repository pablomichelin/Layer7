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

	$iflist = layer7_parse_interfaces_csv($_POST["interfaces_csv"] ?? "", 8);
	if ($iflist === null) {
		$input_errors[] = gettext("Interfaces: ate 8 nomes separados por virgula; apenas letras, numeros, _ e .");
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
		$real_ifaces = array();
		foreach ($iflist as $ifn) {
			$real = convert_friendly_interface_to_real_interface_name($ifn);
			if ($real && $real !== $ifn) {
				$real_ifaces[] = $real;
			} else {
				$real_ifaces[] = $ifn;
			}
		}
		$data["layer7"]["interfaces"] = $real_ifaces;

		if (layer7_save_json($data)) {
			layer7_signal_reload();
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

$ifarr = array();
if (isset($L["interfaces"]) && is_array($L["interfaces"])) {
	$friendly_map = array();
	foreach (get_configured_interface_list(true) as $ifid => $ifdescr) {
		$real = get_real_interface($ifid);
		if ($real) {
			$friendly_map[$real] = $ifid;
		}
	}
	foreach ($L["interfaces"] as $x) {
		if (is_string($x) && strlen($x) <= 32 && preg_match('/^[a-zA-Z0-9_.]+$/', $x)) {
			if (isset($friendly_map[$x])) {
				$ifarr[] = $friendly_map[$x];
			} else {
				$ifarr[] = $x;
			}
		}
	}
}
$interfaces_csv = implode(", ", $ifarr);

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
				<label class="col-sm-3 control-label"><?= gettext("Interfaces reservadas"); ?></label>
				<div class="col-sm-9">
					<input type="text" name="interfaces_csv" class="form-control" style="max-width: 520px;" maxlength="320"
						value="<?= htmlspecialchars($interfaces_csv); ?>" placeholder="lan, opt1" />
					<p class="help-block"><?= gettext("Ate 8 nomes de interface pfSense (ex: lan, opt1). O nome e convertido automaticamente para o device real (em0, igb1, etc.) ao gravar."); ?></p>
				</div>
			</div>

			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-9">
					<button type="submit" name="save" value="1" class="btn btn-primary"><?= gettext("Guardar definicoes"); ?></button>
				</div>
			</div>
			</form>

			<p class="layer7-muted-note small"><?= gettext("Politicas e excecoes existentes sao preservadas quando as definicoes globais sao gravadas."); ?></p>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
