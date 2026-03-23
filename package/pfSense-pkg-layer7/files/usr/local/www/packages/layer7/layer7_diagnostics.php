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

$sigusr1_sent = false;
if (isset($_POST["send_sigusr1"]) && $status_ok && $pid !== null) {
	exec("/bin/kill -USR1 " . escapeshellarg($pid) . " 2>&1", $sig_out, $sig_code);
	$sigusr1_sent = ($sig_code === 0);
	usleep(300000);
}

$sighup_sent = false;
if (isset($_POST["send_sighup"]) && $status_ok && $pid !== null) {
	exec("/bin/kill -HUP " . escapeshellarg($pid) . " 2>&1", $sig_out, $sig_code);
	$sighup_sent = ($sig_code === 0);
}

$recent_logs = array();
if (file_exists("/var/log/system.log")) {
	exec("grep 'layer7d' /var/log/system.log | tail -20 2>/dev/null", $recent_logs);
}

$pf_block_entries = array();
exec("/sbin/pfctl -t layer7_block -T show 2>/dev/null", $pf_block_entries, $pf_block_code);
$pf_block_count = ($pf_block_code === 0) ? count(array_filter($pf_block_entries, 'strlen')) : -1;

$pf_tag_entries = array();
exec("/sbin/pfctl -t layer7_tagged -T show 2>/dev/null", $pf_tag_entries, $pf_tag_code);
$pf_tag_count = ($pf_tag_code === 0) ? count(array_filter($pf_tag_entries, 'strlen')) : -1;

$data = layer7_load_or_default();
$L = isset($data["layer7"]) ? $data["layer7"] : array();
$cfg_enabled = !empty($L["enabled"]);
$cfg_mode = isset($L["mode"]) ? (string)$L["mode"] : "monitor";
$cfg_ifaces = isset($L["interfaces"]) && is_array($L["interfaces"]) ? $L["interfaces"] : array();

$protos_path = "/usr/local/etc/layer7-protos.txt";
$protos_exists = file_exists($protos_path);
$protos_rules = 0;
if ($protos_exists) {
	$plines = file($protos_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($plines) {
		foreach ($plines as $pl) {
			$pl = trim($pl);
			if ($pl !== "" && $pl[0] !== '#') {
				$protos_rules++;
			}
		}
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

		<?php if ($sigusr1_sent) { ?>
		<div class="alert alert-success"><?= gettext("SIGUSR1 enviado. As estatisticas aparecem nos logs abaixo."); ?></div>
		<?php } ?>
		<?php if ($sighup_sent) { ?>
		<div class="alert alert-success"><?= gettext("SIGHUP enviado. O daemon recarregou a configuracao."); ?></div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Resumo operacional"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= gettext("Daemon"); ?></dt>
					<dd>
						<?php if ($status_ok) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($status_out); ?></span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($status_out); ?></span>
						<?php } ?>
					</dd>

					<?php if ($layer7d_ver !== "") { ?>
					<dt><?= gettext("Versao"); ?></dt>
					<dd><code><?= htmlspecialchars($layer7d_ver); ?></code></dd>
					<?php } ?>

					<dt><?= gettext("Modo"); ?></dt>
					<dd>
						<?php if (!$cfg_enabled) { ?>
						<span class="label label-warning"><?= gettext("desativado"); ?></span>
						<?php } elseif ($cfg_mode === "enforce") { ?>
						<span class="label label-danger"><?= gettext("enforce"); ?></span>
						<?php } else { ?>
						<span class="label label-info"><?= gettext("monitor"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= gettext("Interfaces"); ?></dt>
					<dd>
						<?php if (count($cfg_ifaces) > 0) { ?>
						<code><?= htmlspecialchars(implode(", ", $cfg_ifaces)); ?></code>
						<?php } else { ?>
						<span class="text-muted"><?= gettext("Nenhuma configurada"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= gettext("Custom protos"); ?></dt>
					<dd>
						<?php if ($protos_exists) { ?>
						<code><?= htmlspecialchars($protos_path); ?></code> (<?= $protos_rules; ?> <?= gettext("regras"); ?>)
						<?php } else { ?>
						<span class="text-muted"><?= gettext("Nao encontrado"); ?></span>
						<small class="text-muted"> — <?= gettext("copie layer7-protos.txt.sample para ativar"); ?></small>
						<?php } ?>
					</dd>

					<dt><?= gettext("Config"); ?></dt>
					<dd><code><?= htmlspecialchars($cfgpath); ?></code></dd>
				</dl>
			</div>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Tabelas PF (enforcement)"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><code>layer7_block</code></dt>
					<dd>
						<?php if ($pf_block_count >= 0) { ?>
						<?= $pf_block_count; ?> <?= gettext("entradas"); ?>
						<?php if ($pf_block_count > 0 && $pf_block_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_block_entries))); ?></code></small>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= gettext("Tabela nao existe. Criar com: pfctl -t layer7_block -T add 127.0.0.255 && pfctl -t layer7_block -T delete 127.0.0.255"); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_tagged</code></dt>
					<dd>
						<?php if ($pf_tag_count >= 0) { ?>
						<?= $pf_tag_count; ?> <?= gettext("entradas"); ?>
						<?php if ($pf_tag_count > 0 && $pf_tag_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_tag_entries))); ?></code></small>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= gettext("Tabela nao existe (opcional, usada com action=tag)."); ?></span>
						<?php } ?>
					</dd>
				</dl>
			</div>
		</div>

		<?php if ($status_ok && $pid !== null) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Acoes"); ?></h3>
			<form method="post" class="form-inline">
				<button type="submit" name="send_sigusr1" value="1" class="btn btn-info">
					<i class="fa fa-bar-chart"></i> <?= gettext("Obter estatisticas (SIGUSR1)"); ?>
				</button>
				<button type="submit" name="send_sighup" value="1" class="btn btn-warning">
					<i class="fa fa-refresh"></i> <?= gettext("Recarregar config (SIGHUP)"); ?>
				</button>
			</form>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Logs recentes"); ?></h3>
			<?php if (count($recent_logs) > 0) { ?>
			<pre class="pre-scrollable" style="max-height: 350px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $recent_logs)); ?></pre>
			<?php } else { ?>
			<div class="alert alert-info"><?= gettext("Nenhum log do layer7d encontrado em /var/log/system.log."); ?></div>
			<?php } ?>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Comandos uteis"); ?></h3>
			<ul class="small">
				<li><code>service layer7d onestart</code> — <?= gettext("arrancar o daemon"); ?></li>
				<li><code>service layer7d onestop</code> — <?= gettext("parar o daemon"); ?></li>
				<li><code>service layer7d onerestart</code> — <?= gettext("reiniciar o daemon"); ?></li>
				<li><code>kill -USR1 $(pgrep layer7d)</code> — <?= gettext("estatisticas (cap_pkts, cap_classified, pf_add_ok, ...)"); ?></li>
				<li><code>pfctl -t layer7_block -T show</code> — <?= gettext("IPs bloqueados"); ?></li>
				<li><code>pfctl -t layer7_block -T delete IP</code> — <?= gettext("desbloquear IP"); ?></li>
				<li><code>sysrc layer7d_enable=YES</code> — <?= gettext("ativar arranque automatico no boot"); ?></li>
			</ul>
		</div>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
