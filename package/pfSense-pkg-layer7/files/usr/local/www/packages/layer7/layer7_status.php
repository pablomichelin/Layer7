<?php
##|+PRIV
##|*IDENT=page-services-layer7
##|*NAME=Services: Layer 7
##|*DESCR=Allow access to the Layer 7 package page.
##|*MATCH=layer7_status.php*
##|-PRIV
/*
 * Status + leitura via layer7d -t (somente leitura; sem gravação de config).
 */

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
	$parse_out = gettext("Binário layer7d não encontrado ou não executável.");
}

$pgtitle = array(gettext("Services"), gettext("Layer 7"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 — estado"); ?></h2>
	</div>
	<div class="panel-body">
		<?php if ($daemon_ver !== "") { ?>
		<p><?= gettext("Binário:"); ?> <code>layer7d -V</code> → <strong><?= htmlspecialchars($daemon_ver); ?></strong></p>
		<?php } ?>
		<p><?= gettext("Config:"); ?> <code><?= htmlspecialchars($cfgpath); ?></code>
		<?php if (!file_exists($cfgpath)) { ?>
			<span class="text-warning"><?= gettext("(ficheiro ausente — copiar layer7.json.sample)"); ?></span>
		<?php } ?>
		</p>
		<p><?= gettext("Interpretação (layer7d -t):"); ?>
		<?php if ($parse_ok === true) { ?>
			<span class="text-success"><?= gettext("OK"); ?></span>
		<?php } elseif ($parse_ok === false) { ?>
			<span class="text-danger"><?= gettext("falhou (ver abaixo)"); ?></span>
		<?php } ?>
		</p>
		<pre class="pre-scrollable" style="max-height:220px;"><?= htmlspecialchars($parse_out); ?></pre>
		<p>
			<a href="layer7_settings.php" class="btn btn-default"><?= gettext("Definições"); ?></a>
			<a href="layer7_policies.php" class="btn btn-default"><?= gettext("Políticas"); ?></a>
			<a href="layer7_exceptions.php" class="btn btn-default"><?= gettext("Exceções"); ?></a>
			<a href="layer7_events.php" class="btn btn-default"><?= gettext("Events"); ?></a>
			<a href="layer7_diagnostics.php" class="btn btn-default"><?= gettext("Diagnostics"); ?></a>
		</p>
		<p class="text-muted small"><?= gettext("Serviço: layer7d — enabled=false → idle."); ?></p>
	</div>
</div>
<?php require_once("foot.inc"); ?>
