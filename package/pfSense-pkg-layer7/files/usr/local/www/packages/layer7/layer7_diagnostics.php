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
$log_path = "/var/log/layer7d.log";
$pf_helper = layer7_pf_helper_path();
$pf_rules = layer7_pf_rules_path();
$pf_rules_sample = layer7_pf_rules_sample_path();
$layer7d_ver = layer7_daemon_version();
$pidfile = "/var/run/layer7d.pid";
$status_out = "";
$status_ok = null;
$pid = null;

if (file_exists($pidfile)) {
	$pid = trim(@file_get_contents($pidfile));
	if ($pid !== "" && ctype_digit($pid)) {
		$status_out = l7_t("Servico layer7d em execucao (PID ") . $pid . ").";
		$status_ok = true;
	} else {
		$status_out = l7_t("Ficheiro PID invalido ou vazio.");
		$status_ok = false;
	}
} else {
	$status_out = l7_t("Servico layer7d parado (sem ficheiro PID).");
	$status_ok = false;
}

if ($status_ok && $pid !== null) {
	exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $kout, $kcode);
	if ($kcode !== 0) {
		$status_out = l7_t("PID ") . $pid . l7_t(" nao existe mais.");
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
if (file_exists($log_path)) {
	exec("/usr/bin/tail -n 20 " . escapeshellarg($log_path) . " 2>/dev/null", $recent_logs);
} elseif (file_exists("/var/log/system.log")) {
	exec("grep 'layer7d' /var/log/system.log | tail -20 2>/dev/null", $recent_logs);
}

$pf_block_entries = array();
exec("/sbin/pfctl -t layer7_block -T show 2>/dev/null", $pf_block_entries, $pf_block_code);
$pf_block_count = ($pf_block_code === 0) ? count(array_filter($pf_block_entries, 'strlen')) : -1;

$pf_block_dst_entries = array();
exec("/sbin/pfctl -t layer7_block_dst -T show 2>/dev/null", $pf_block_dst_entries, $pf_block_dst_code);
$pf_block_dst_count = ($pf_block_dst_code === 0) ? count(array_filter($pf_block_dst_entries, 'strlen')) : -1;

$pf_tag_entries = array();
exec("/sbin/pfctl -t layer7_tagged -T show 2>/dev/null", $pf_tag_entries, $pf_tag_code);
$pf_tag_count = ($pf_tag_code === 0) ? count(array_filter($pf_tag_entries, 'strlen')) : -1;

$pf_rules_exists = file_exists($pf_rules);
$pf_rules_preview = array();
if ($pf_rules_exists) {
	$pf_rules_preview = @file($pf_rules, FILE_IGNORE_NEW_LINES);
	if (is_array($pf_rules_preview)) {
		$pf_rules_preview = array_slice($pf_rules_preview, 0, 20);
	} else {
		$pf_rules_preview = array();
	}
}
$pf_generated_rules = layer7_generate_rules("filter");
$pf_generated_preview = array_slice(preg_split('/\r?\n/', trim($pf_generated_rules)), 0, 20);
$pf_hook_ready = function_exists("layer7_generate_rules");
$pf_rules_debug_path = "/tmp/rules.debug";
$pf_rules_debug_hits = array();
$pf_rules_debug_has_layer7 = false;
if (file_exists($pf_rules_debug_path)) {
	exec("/usr/bin/grep -n 'layer7:block' " . escapeshellarg($pf_rules_debug_path) . " 2>/dev/null", $pf_rules_debug_hits, $pf_rules_debug_code);
	$pf_rules_debug_has_layer7 = ($pf_rules_debug_code === 0 && count($pf_rules_debug_hits) > 0);
}
$pf_active_rules_hits = array();
exec("/sbin/pfctl -sr 2>/dev/null | /usr/bin/grep 'layer7:' 2>/dev/null", $pf_active_rules_hits, $pf_active_rules_code);
$pf_active_rules_loaded = ($pf_active_rules_code === 0 && count($pf_active_rules_hits) > 0);

$pf_anti_dot_hits = array();
exec("/sbin/pfctl -sr 2>/dev/null | /usr/bin/grep 'layer7:anti-' 2>/dev/null", $pf_anti_dot_hits, $pf_anti_dot_code);
$pf_anti_dot_loaded = ($pf_anti_dot_code === 0 && count($pf_anti_dot_hits) > 0);

$unbound_anti_doh = false;
if (file_exists("/var/unbound/unbound.conf")) {
	exec("/usr/bin/grep -c 'Layer7 anti-DoH' /var/unbound/unbound.conf 2>/dev/null", $ub_out, $ub_code);
	$unbound_anti_doh = ($ub_code === 0 && !empty($ub_out[0]) && (int)$ub_out[0] > 0);
}

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

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Diagnostics"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - diagnostics"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("diagnostics"); ?>
		<div class="layer7-content">

		<?php if ($sigusr1_sent) { ?>
		<div class="alert alert-success"><?= l7_t("SIGUSR1 enviado. As estatisticas aparecem nos logs abaixo."); ?></div>
		<?php } ?>
		<?php if ($sighup_sent) { ?>
		<div class="alert alert-success"><?= l7_t("SIGHUP enviado. O daemon recarregou a configuracao."); ?></div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Resumo operacional"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= l7_t("Daemon"); ?></dt>
					<dd>
						<?php if ($status_ok) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($status_out); ?></span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($status_out); ?></span>
						<?php } ?>
					</dd>

					<?php if ($layer7d_ver !== "") { ?>
					<dt><?= l7_t("Versao"); ?></dt>
					<dd><code><?= htmlspecialchars($layer7d_ver); ?></code></dd>
					<?php } ?>

					<dt><?= l7_t("Modo"); ?></dt>
					<dd>
						<?php if (!$cfg_enabled) { ?>
						<span class="label label-warning"><?= l7_t("desativado"); ?></span>
						<?php } elseif ($cfg_mode === "enforce") { ?>
						<span class="label label-danger"><?= l7_t("enforce"); ?></span>
						<?php } else { ?>
						<span class="label label-info"><?= l7_t("monitor"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Interfaces"); ?></dt>
					<dd>
						<?php if (count($cfg_ifaces) > 0) { ?>
						<code><?= htmlspecialchars(implode(", ", $cfg_ifaces)); ?></code>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Nenhuma configurada"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Custom protos"); ?></dt>
					<dd>
						<?php if ($protos_exists) { ?>
						<code><?= htmlspecialchars($protos_path); ?></code> (<?= $protos_rules; ?> <?= l7_t("regras"); ?>)
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Nao encontrado"); ?></span>
						<small class="text-muted"> — <?= l7_t("copie layer7-protos.txt.sample para ativar"); ?></small>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Config"); ?></dt>
					<dd><code><?= htmlspecialchars($cfgpath); ?></code></dd>

					<dt><?= l7_t("Log local"); ?></dt>
					<dd><code><?= htmlspecialchars($log_path); ?></code></dd>
				</dl>
			</div>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Tabelas PF (enforcement)"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= l7_t("Helper PF"); ?></dt>
					<dd>
						<?php if (is_executable($pf_helper)) { ?>
						<code><?= htmlspecialchars($pf_helper); ?></code>
						<?php } else { ?>
						<span class="text-warning"><?= l7_t("Nao encontrado"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Snippet PF"); ?></dt>
					<dd>
						<?php if ($pf_rules_exists) { ?>
						<code><?= htmlspecialchars($pf_rules); ?></code>
						<?php } else { ?>
						<span class="text-warning"><?= l7_t("Nao gerado"); ?></span>
						<?php if (file_exists($pf_rules_sample)) { ?>
						<small class="text-muted"> — <?= l7_t("sample disponivel em"); ?> <code><?= htmlspecialchars($pf_rules_sample); ?></code></small>
						<?php } ?>
						<?php } ?>
						<br><small class="text-muted"><?= l7_t("A regra minima do pacote e publicada por layer7_generate_rules() durante o reload oficial do filtro; a prova final continua a ser confirmar a presenca em rules.debug e pfctl -sr no appliance."); ?></small>
					</dd>

					<dt><?= l7_t("Hook do pacote"); ?></dt>
					<dd>
						<?php if ($pf_hook_ready) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <code>layer7_generate_rules("filter")</code></span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Hook nao encontrado"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("rules.debug"); ?></dt>
					<dd>
						<?php if (file_exists($pf_rules_debug_path)) { ?>
						<code><?= htmlspecialchars($pf_rules_debug_path); ?></code>
						<?php if ($pf_rules_debug_has_layer7) { ?>
						<br><span class="text-success"><?= l7_t("Regra Layer7 encontrada no rules.debug atual."); ?></span>
						<?php } else { ?>
						<br><span class="text-warning"><?= l7_t("Arquivo presente, mas sem label layer7:block:src."); ?></span>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Arquivo nao encontrado no appliance atual."); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Filtro ativo"); ?></dt>
					<dd>
						<?php if ($pf_active_rules_loaded) { ?>
						<span class="text-success"><?= l7_t("Regra Layer7 encontrada em pfctl -sr."); ?></span>
						<?php } else { ?>
						<span class="text-warning"><?= l7_t("Regra Layer7 ainda nao apareceu em pfctl -sr."); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_block</code></dt>
					<dd>
						<?php if ($pf_block_count >= 0) { ?>
						<?= $pf_block_count; ?> <?= l7_t("entradas"); ?>
						<?php if ($pf_block_count > 0 && $pf_block_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_block_entries))); ?></code></small>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Tabela nao existe. Criar com: pfctl -t layer7_block -T add 127.0.0.255 && pfctl -t layer7_block -T delete 127.0.0.255"); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_block_dst</code></dt>
					<dd>
						<?php if ($pf_block_dst_count >= 0) { ?>
						<?= $pf_block_dst_count; ?> <?= l7_t("entradas (destinos bloqueados)"); ?>
						<?php if ($pf_block_dst_count > 0 && $pf_block_dst_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_block_dst_entries))); ?></code></small>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Tabela nao existe ainda."); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_tagged</code></dt>
					<dd>
						<?php if ($pf_tag_count >= 0) { ?>
						<?= $pf_tag_count; ?> <?= l7_t("entradas"); ?>
						<?php if ($pf_tag_count > 0 && $pf_tag_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_tag_entries))); ?></code></small>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Tabela nao existe (opcional, usada com action=tag)."); ?></span>
						<?php } ?>
					</dd>
				</dl>
			</div>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Anti-bypass DNS"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= l7_t("DoT/DoQ (porta 853)"); ?></dt>
					<dd>
						<?php if ($pf_anti_dot_loaded) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= l7_t("Regras anti-DoT/DoQ activas em pfctl -sr."); ?></span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Regras anti-DoT/DoQ nao encontradas. Recarregue o filtro."); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Unbound anti-DoH"); ?></dt>
					<dd>
						<?php if ($unbound_anti_doh) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= l7_t("Overrides anti-DoH configurados no Unbound."); ?></span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Overrides nao encontrados. Execute: sh /usr/local/libexec/layer7-unbound-anti-doh"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Politica nDPI"); ?></dt>
					<dd>
						<span class="text-muted"><?= l7_t("Verifique se a politica 'anti-bypass-dns' esta ativa em Politicas (apps: DoH_DoT, iCloudPrivateRelay)."); ?></span>
					</dd>
				</dl>
			</div>
		</div>

		<?php if ($pf_rules_exists && count($pf_rules_preview) > 0) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Snippet PF gerado"); ?></h3>
			<pre class="pre-scrollable" style="max-height: 220px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $pf_rules_preview)); ?></pre>
		</div>
		<?php } ?>

		<?php if (count($pf_generated_preview) > 0) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Regra publicada pelo hook"); ?></h3>
			<pre class="pre-scrollable" style="max-height: 220px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $pf_generated_preview)); ?></pre>
		</div>
		<?php } ?>

		<?php if ($pf_rules_debug_has_layer7 && count($pf_rules_debug_hits) > 0) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Trecho de rules.debug"); ?></h3>
			<pre class="pre-scrollable" style="max-height: 220px; font-size: 12px;"><?= htmlspecialchars(implode("\n", array_slice($pf_rules_debug_hits, 0, 20))); ?></pre>
		</div>
		<?php } ?>

		<?php if ($pf_active_rules_loaded && count($pf_active_rules_hits) > 0) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Trecho de pfctl -sr"); ?></h3>
			<pre class="pre-scrollable" style="max-height: 220px; font-size: 12px;"><?= htmlspecialchars(implode("\n", array_slice($pf_active_rules_hits, 0, 20))); ?></pre>
		</div>
		<?php } ?>

		<?php if ($status_ok && $pid !== null) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Acoes"); ?></h3>
			<form method="post" class="form-inline">
				<button type="submit" name="send_sigusr1" value="1" class="btn btn-info">
					<i class="fa fa-bar-chart"></i> <?= l7_t("Obter estatisticas (SIGUSR1)"); ?>
				</button>
				<button type="submit" name="send_sighup" value="1" class="btn btn-warning">
					<i class="fa fa-refresh"></i> <?= l7_t("Recarregar config (SIGHUP)"); ?>
				</button>
			</form>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Logs recentes"); ?></h3>
			<?php if (count($recent_logs) > 0) { ?>
			<pre class="pre-scrollable" style="max-height: 350px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $recent_logs)); ?></pre>
			<?php } else { ?>
			<div class="alert alert-info"><?= l7_t("Nenhum log do layer7d encontrado em /var/log/layer7d.log."); ?></div>
			<?php } ?>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Comandos uteis"); ?></h3>
			<ul class="small">
				<li><code>service layer7d onestart</code> — <?= l7_t("arrancar o daemon"); ?></li>
				<li><code>service layer7d onestop</code> — <?= l7_t("parar o daemon"); ?></li>
				<li><code>service layer7d onerestart</code> — <?= l7_t("reiniciar o daemon"); ?></li>
				<li><code>kill -USR1 $(pgrep layer7d)</code> — <?= l7_t("estatisticas (cap_pkts, cap_classified, pf_add_ok, ...)"); ?></li>
				<li><code>tail -f /var/log/layer7d.log</code> — <?= l7_t("acompanhar classificacoes e eventos em tempo real"); ?></li>
				<li><code>pfctl -t layer7_block -T show</code> — <?= l7_t("IPs de origem bloqueados (quarentena)"); ?></li>
				<li><code>pfctl -t layer7_block_dst -T show</code> — <?= l7_t("IPs de destino bloqueados (sites/apps)"); ?></li>
				<li><code>pfctl -t layer7_block_dst -T delete IP</code> — <?= l7_t("desbloquear destino"); ?></li>
				<li><code>pfctl -t layer7_block -T delete IP</code> — <?= l7_t("desbloquear origem"); ?></li>
				<li><code>sysrc layer7d_enable=YES</code> — <?= l7_t("ativar arranque automatico no boot"); ?></li>
				<li><code>pfctl -sr | grep layer7:anti</code> — <?= l7_t("verificar regras anti-DoT/DoQ activas"); ?></li>
				<li><code>sh /usr/local/libexec/layer7-unbound-anti-doh</code> — <?= l7_t("configurar Unbound anti-DoH/Relay"); ?></li>
				<li><code>drill mask.icloud.com @127.0.0.1</code> — <?= l7_t("verificar se Private Relay retorna NXDOMAIN"); ?></li>
			</ul>
		</div>
		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
