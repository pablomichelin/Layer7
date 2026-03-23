<?php
##|+PRIV
##|*IDENT=page-services-layer7
##|*NAME=Services: Layer 7
##|*DESCR=Allow access to the Layer 7 package page.
##|*MATCH=layer7_status.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$cfgpath = layer7_cfg_path();
$layer7d = "/usr/local/sbin/layer7d";
$daemon_ver = layer7_daemon_version();
$parse_out = "";
$parse_ok = null;

if (is_executable($layer7d)) {
	$cmd = escapeshellarg($layer7d) . " -t -c " . escapeshellarg($cfgpath) . " 2>&1";
	exec($cmd, $lines, $code);
	$parse_out = implode("\n", $lines);
	$parse_ok = ($code === 0);
} else {
	$parse_out = gettext("Binario layer7d nao encontrado ou nao executavel.");
}

$pidfile = "/var/run/layer7d.pid";
$running = false;
$pid = null;
if (file_exists($pidfile)) {
	$pid = trim(@file_get_contents($pidfile));
	if ($pid !== "" && ctype_digit($pid)) {
		exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $chk, $chk_code);
		$running = ($chk_code === 0);
	}
}

$data = layer7_load_or_default();
$L = isset($data["layer7"]) ? $data["layer7"] : array();
$cfg_enabled = !empty($L["enabled"]);
$cfg_mode = isset($L["mode"]) ? (string)$L["mode"] : "monitor";
$cfg_ifaces = isset($L["interfaces"]) && is_array($L["interfaces"]) ? $L["interfaces"] : array();
$n_policies = isset($L["policies"]) && is_array($L["policies"]) ? count($L["policies"]) : 0;
$n_exceptions = isset($L["exceptions"]) && is_array($L["exceptions"]) ? count($L["exceptions"]) : 0;
$n_policies_active = 0;
$n_block_policies = 0;
if (isset($L["policies"]) && is_array($L["policies"])) {
	foreach ($L["policies"] as $p) {
		if (!empty($p["enabled"])) {
			$n_policies_active++;
			if (isset($p["action"]) && $p["action"] === "block") {
				$n_block_policies++;
			}
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Layer 7"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - estado"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("status"); ?>
		<div class="layer7-content">
		<p class="layer7-lead"><?= gettext("Visao geral do daemon, configuracao e estado operacional do Layer7."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Estado do sistema"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= gettext("Daemon"); ?></dt>
					<dd>
						<?php if ($running) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= gettext("Em execucao"); ?> (PID <?= htmlspecialchars($pid); ?>)</span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= gettext("Parado"); ?></span>
						<?php } ?>
					</dd>

					<?php if ($daemon_ver !== "") { ?>
					<dt><?= gettext("Versao"); ?></dt>
					<dd><code><?= htmlspecialchars($daemon_ver); ?></code></dd>
					<?php } ?>

					<dt><?= gettext("Modo"); ?></dt>
					<dd>
						<?php if (!$cfg_enabled) { ?>
						<span class="label label-warning"><?= gettext("desativado"); ?></span>
						<?php } elseif ($cfg_mode === "enforce") { ?>
						<span class="label label-danger"><?= gettext("enforce"); ?></span>
						— <?= gettext("o daemon bloqueia trafego conforme as politicas"); ?>
						<?php } else { ?>
						<span class="label label-info"><?= gettext("monitor"); ?></span>
						— <?= gettext("o daemon classifica mas nao bloqueia"); ?>
						<?php } ?>
					</dd>

					<dt><?= gettext("Interfaces"); ?></dt>
					<dd>
						<?php if (count($cfg_ifaces) > 0) { ?>
						<code><?= htmlspecialchars(implode(", ", $cfg_ifaces)); ?></code>
						<?php } else { ?>
						<span class="text-muted"><?= gettext("Nenhuma"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= gettext("Politicas"); ?></dt>
					<dd>
						<?= $n_policies_active; ?>/<?= $n_policies; ?> <?= gettext("ativas"); ?>
						<?php if ($n_block_policies > 0) { ?>
						(<span class="text-danger"><?= $n_block_policies; ?> block</span>)
						<?php } ?>
					</dd>

					<dt><?= gettext("Excecoes"); ?></dt>
					<dd><?= $n_exceptions; ?></dd>
				</dl>
			</div>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Validacao da configuracao"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= gettext("Config"); ?></dt>
					<dd>
						<code><?= htmlspecialchars($cfgpath); ?></code>
						<?php if (!file_exists($cfgpath)) { ?>
						<span class="text-warning"> — <?= gettext("ausente, copie layer7.json.sample"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= gettext("Interpretacao"); ?></dt>
					<dd>
						<?php if ($parse_ok === true) { ?>
						<span class="text-success"><i class="fa fa-check"></i> <?= gettext("OK"); ?></span>
						<?php } elseif ($parse_ok === false) { ?>
						<span class="text-danger"><i class="fa fa-times"></i> <?= gettext("Falhou"); ?></span>
						<?php } else { ?>
						<span class="text-muted"><?= gettext("Nao executado"); ?></span>
						<?php } ?>
					</dd>
				</dl>
			</div>
			<pre class="pre-scrollable" style="max-height: 280px;"><?= htmlspecialchars($parse_out); ?></pre>
		</div>

		<div class="layer7-toolbar">
			<a href="layer7_settings.php" class="btn btn-primary"><?= gettext("Abrir definicoes"); ?></a>
			<a href="layer7_policies.php" class="btn btn-default"><?= gettext("Ver politicas"); ?></a>
			<a href="layer7_diagnostics.php" class="btn btn-default"><?= gettext("Diagnostics"); ?></a>
			<a href="layer7_events.php" class="btn btn-default"><?= gettext("Events"); ?></a>
		</div>

		<p class="layer7-muted-note small"><?= gettext("Quando enabled=false, o daemon permanece em idle. A interface continua acessivel para configuracao."); ?></p>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
