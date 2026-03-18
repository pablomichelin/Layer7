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
		$status_out = gettext("Serviço: layer7d está a correr (PID ") . $pid . ").";
		$status_ok = true;
	} else {
		$status_out = gettext("Serviço: ficheiro PID inválido ou vazio.");
		$status_ok = false;
	}
} else {
	$status_out = gettext("Serviço: layer7d não está a correr (sem ficheiro PID).");
	$status_ok = false;
}

/* Se temos PID, verificar se o processo existe */
if ($status_ok && $pid !== null) {
	exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $kout, $kcode);
	if ($kcode !== 0) {
		$status_out = gettext("Serviço: PID ") . $pid . " " . gettext("não existe (processo terminado).");
		$status_ok = false;
	}
}

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Diagnostics"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 — diagnostics"); ?></h2>
	</div>
	<div class="panel-body">
		<p><a href="layer7_status.php"><?= gettext("← Estado"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_settings.php"><?= gettext("Definições"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_policies.php"><?= gettext("Políticas"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_exceptions.php"><?= gettext("Exceções"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_events.php"><?= gettext("Events"); ?></a></p>

		<?php if ($layer7d_ver !== "") { ?>
		<h3><?= gettext("Binário"); ?></h3>
		<p class="small"><code>layer7d -V</code>: <strong><?= htmlspecialchars($layer7d_ver); ?></strong></p>
		<?php } ?>

		<h3><?= gettext("Serviço"); ?></h3>
		<p><?php if ($status_ok) { ?>
			<span class="text-success"><?= htmlspecialchars($status_out); ?></span>
		<?php } else { ?>
			<span class="text-warning"><?= htmlspecialchars($status_out); ?></span>
		<?php } ?></p>
		<?php if ($pid !== null && $pid !== "" && $status_ok) { ?>
		<p class="small"><?= gettext("Recarregar config (SIGHUP):"); ?> <code>kill -HUP <?= htmlspecialchars($pid); ?></code><br />
		<?= gettext("Estatísticas no syslog (SIGUSR1):"); ?> <code>kill -USR1 <?= htmlspecialchars($pid); ?></code></p>
		<?php } ?>

		<h3><?= gettext("Logs"); ?></h3>
		<p><?= gettext("Os logs do layer7d são enviados para o syslog do sistema (facilidade LOG_DAEMON, ident 'layer7d')."); ?>
		<?= gettext("No pfSense: consulte os logs do sistema e filtre por 'layer7d'."); ?>
		<?= gettext("O nível de detalhe depende de"); ?> <code>layer7.log_level</code> <?= gettext("em"); ?> <a href="layer7_settings.php"><?= gettext("Definições"); ?></a>.</p>

		<h3><?= gettext("Comandos úteis"); ?></h3>
		<ul class="small">
			<li><code>service layer7d onestart</code> — <?= gettext("arrancar"); ?></li>
			<li><code>service layer7d onestop</code> — <?= gettext("parar"); ?></li>
			<li><code>service layer7d status</code> — <?= gettext("estado"); ?></li>
			<li><code>sysrc layer7d_enable=YES</code> — <?= gettext("ativar no boot"); ?></li>
		</ul>
		<p class="text-muted small"><?= gettext("Config:"); ?> <code><?= htmlspecialchars($cfgpath); ?></code>
		<?php if (!file_exists($cfgpath)) { ?>
			<span class="text-warning"><?= gettext("(ausente)"); ?></span>
		<?php } ?></p>
	</div>
</div>
<?php require_once("foot.inc"); ?>
