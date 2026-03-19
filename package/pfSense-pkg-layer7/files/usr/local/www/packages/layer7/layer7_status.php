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
		<p class="layer7-lead"><?= gettext("Resumo visual do daemon, do ficheiro de configuracao e da leitura atual do JSON pelo layer7d."); ?></p>

		<div class="layer7-callout">
			<dl class="dl-horizontal layer7-summary">
				<?php if ($daemon_ver !== "") { ?>
				<dt><?= gettext("Binario"); ?></dt>
				<dd><code>layer7d -V</code> <strong><?= htmlspecialchars($daemon_ver); ?></strong></dd>
				<?php } ?>

				<dt><?= gettext("Config"); ?></dt>
				<dd>
					<code><?= htmlspecialchars($cfgpath); ?></code>
					<?php if (!file_exists($cfgpath)) { ?>
					<span class="text-warning"><?= gettext("Ficheiro ausente - copie layer7.json.sample."); ?></span>
					<?php } ?>
				</dd>

				<dt><?= gettext("Interpretacao"); ?></dt>
				<dd>
					<?php if ($parse_ok === true) { ?>
					<span class="text-success"><?= gettext("OK"); ?></span>
					<?php } elseif ($parse_ok === false) { ?>
					<span class="text-danger"><?= gettext("Falhou - ver saida abaixo."); ?></span>
					<?php } else { ?>
					<span class="text-muted"><?= gettext("Nao executado."); ?></span>
					<?php } ?>
				</dd>
			</dl>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Saida do layer7d -t"); ?></h3>
			<pre class="pre-scrollable" style="max-height: 280px;"><?= htmlspecialchars($parse_out); ?></pre>
		</div>

		<div class="layer7-toolbar">
			<a href="layer7_settings.php" class="btn btn-primary"><?= gettext("Abrir definicoes"); ?></a>
			<a href="layer7_policies.php" class="btn btn-default"><?= gettext("Ver politicas"); ?></a>
			<a href="layer7_diagnostics.php" class="btn btn-default"><?= gettext("Abrir diagnostics"); ?></a>
		</div>

		<p class="layer7-muted-note small"><?= gettext("Quando enabled=false, o daemon permanece em idle, mas o pacote e a interface continuam acessiveis para configuracao e validacao."); ?></p>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
