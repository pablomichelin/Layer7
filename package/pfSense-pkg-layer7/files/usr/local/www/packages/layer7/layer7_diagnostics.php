<?php
##|+PRIV
##|*IDENT=page-services-layer7-diagnostics
##|*NAME=Services: Layer 7 (diagnostics)
##|*DESCR=Diagnostics and troubleshooting for Layer 7.
##|*MATCH=layer7_diagnostics.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$cfgpath = layer7_cfg_path();
$layer7d_ver = layer7_daemon_version();
$pidfile = "/var/run/layer7d.pid";
$status_out = "";
$status_ok = null;
$pid = null;

if (file_exists($pidfile)) {
	$pid = trim(@file_get_contents($pidfile));
	if ($pid !== "" && ctype_digit($pid)) {
		$status_out = gettext("Servico layer7d em execucao (PID ") . $pid . ").";
		$status_ok = true;
	} else {
		$status_out = gettext("Ficheiro PID invalido ou vazio.");
		$status_ok = false;
	}
} else {
	$status_out = gettext("Servico layer7d parado (sem ficheiro PID).");
	$status_ok = false;
}

if ($status_ok && $pid !== null) {
	exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $kout, $kcode);
	if ($kcode !== 0) {
		$status_out = gettext("PID ") . $pid . gettext(" nao existe mais.");
		$status_ok = false;
	}
}

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Diagnostics"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - diagnostics"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("diagnostics"); ?>
		<div class="layer7-content">
		<p class="layer7-lead"><?= gettext("Painel de apoio operacional para validar binario, estado do servico, sinais e caminhos de troubleshooting no pfSense."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Resumo do binario"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= gettext("Versao"); ?></dt>
					<dd><?php if ($layer7d_ver !== "") { ?><code>layer7d -V</code> <strong><?= htmlspecialchars($layer7d_ver); ?></strong><?php } else { ?><span class="text-warning"><?= gettext("Binario nao encontrado."); ?></span><?php } ?></dd>
					<dt><?= gettext("Config"); ?></dt>
					<dd><code><?= htmlspecialchars($cfgpath); ?></code></dd>
				</dl>
			</div>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Estado do servico"); ?></h3>
			<p><?php if ($status_ok) { ?>
				<span class="text-success"><?= htmlspecialchars($status_out); ?></span>
			<?php } else { ?>
				<span class="text-warning"><?= htmlspecialchars($status_out); ?></span>
			<?php } ?></p>
			<?php if ($pid !== null && $pid !== "" && $status_ok) { ?>
			<dl class="dl-horizontal layer7-summary">
				<dt><?= gettext("Reload"); ?></dt>
				<dd><code>kill -HUP <?= htmlspecialchars($pid); ?></code></dd>
				<dt><?= gettext("Estatisticas"); ?></dt>
				<dd><code>kill -USR1 <?= htmlspecialchars($pid); ?></code></dd>
			</dl>
			<?php } ?>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Logs"); ?></h3>
			<p><?= gettext("Os eventos do daemon sao enviados para o syslog do sistema com ident 'layer7d'. Filtre os logs do pfSense por esse nome para acompanhar arranque, stop, reload e falhas."); ?></p>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Comandos uteis"); ?></h3>
			<ul class="small">
				<li><code>service layer7d onestart</code> - <?= gettext("arrancar para teste manual"); ?></li>
				<li><code>service layer7d onestop</code> - <?= gettext("parar o daemon"); ?></li>
				<li><code>service layer7d status</code> - <?= gettext("confirmar o estado atual"); ?></li>
				<li><code>sysrc layer7d_enable=YES</code> - <?= gettext("ativar arranque no boot quando o gate de persistencia for fechado"); ?></li>
			</ul>
		</div>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
