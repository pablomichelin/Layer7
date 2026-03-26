<?php
##|+PRIV
##|*IDENT=page-services-layer7-diagnostics
##|*NAME=Services: Layer 7 (diagnostics)
##|*DESCR=Diagnostics and troubleshooting for Layer 7.
##|*MATCH=layer7_diagnostics.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

function layer7_diag_pf_required_tables_ok($tables, $bl_config)
{
	if (!is_array($tables)) {
		return false;
	}
	if (empty($tables["layer7_block"]) || empty($tables["layer7_block_dst"])) {
		return false;
	}
	if (!empty($bl_config["rules"]) && is_array($bl_config["rules"])) {
		foreach ($bl_config["rules"] as $idx => $rule) {
			$tname = "layer7_bld_" . (int)$idx;
			if (empty($tables[$tname])) {
				return false;
			}
		}
	}
	return true;
}

function layer7_diag_table_referenced($table, $rules_raw)
{
	if (!is_string($table) || $table === "" || !is_array($rules_raw)) {
		return false;
	}
	$pattern = '/<' . preg_quote($table, '/') . '[:>]/';
	foreach ($rules_raw as $line) {
		if (preg_match($pattern, (string)$line)) {
			return true;
		}
	}
	return false;
}

function layer7_diag_table_ready($table, $tables, $rules_raw)
{
	if (is_array($tables) && !empty($tables[$table])) {
		return true;
	}
	return layer7_diag_table_referenced($table, $rules_raw);
}

function layer7_diag_pf_required_tables_ready($tables, $bl_config, $rules_raw)
{
	if (!layer7_diag_table_ready("layer7_block", $tables, $rules_raw) ||
	    !layer7_diag_table_ready("layer7_block_dst", $tables, $rules_raw)) {
		return false;
	}
	if (!empty($bl_config["enabled"]) &&
	    !empty($bl_config["rules"]) &&
	    is_array($bl_config["rules"])) {
		foreach ($bl_config["rules"] as $idx => $rule) {
			if (empty($rule["enabled"])) {
				continue;
			}
			$tname = "layer7_bld_" . (int)$idx;
			if (!layer7_diag_table_ready($tname, $tables, $rules_raw)) {
				return false;
			}
		}
	}
	return true;
}

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

$anti_doh_result = null;
if (isset($_POST["configure_anti_doh"])) {
	$anti_doh_result = layer7_configure_unbound_anti_doh();
}
if (isset($_POST["remove_anti_doh"])) {
	$anti_doh_result = layer7_remove_unbound_anti_doh();
}

$bl_config_diag = layer7_bl_config_load();

$pf_repair_result = null;
if (isset($_POST["repair_pf_tables"])) {
	$helper = layer7_pf_helper_path();
	if (is_executable($helper)) {
		exec(escapeshellarg($helper) . " ensure 2>/dev/null", $ensure_out, $ensure_rc);
		if (function_exists("filter_configure")) {
			filter_configure();
		}
		usleep(800000);
		$tables_raw = array();
		exec("/sbin/pfctl -s Tables 2>/dev/null", $tables_raw, $tables_rc);
		$tables_map = array();
		if ($tables_rc === 0) {
			foreach ($tables_raw as $line) {
				$line = trim((string)$line);
				if ($line !== "") {
					$tables_map[$line] = true;
				}
			}
		}
		$rules_raw = array();
		exec("/sbin/pfctl -sr 2>/dev/null", $rules_raw);
		$required_ok = layer7_diag_pf_required_tables_ready($tables_map, $bl_config_diag, $rules_raw);
		if (($ensure_rc !== 0 || !$required_ok) && file_exists("/tmp/rules.debug")) {
			if (function_exists("mwexec")) {
				mwexec("/sbin/pfctl -f /tmp/rules.debug");
			} else {
				@shell_exec("/sbin/pfctl -f /tmp/rules.debug 2>/dev/null");
			}
			usleep(300000);
		}
		layer7_signal_reload();
		$tables_after_raw = array();
		exec("/sbin/pfctl -s Tables 2>/dev/null", $tables_after_raw, $tables_after_rc);
		$tables_after = array();
		if ($tables_after_rc === 0) {
			foreach ($tables_after_raw as $line) {
				$line = trim((string)$line);
				if ($line !== "") {
					$tables_after[$line] = true;
				}
			}
		}
		$rules_after = array();
		exec("/sbin/pfctl -sr 2>/dev/null", $rules_after);
		$pf_repair_result = layer7_diag_pf_required_tables_ready($tables_after, $bl_config_diag, $rules_after);
	} else {
		$pf_repair_result = false;
	}
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

$pf_bld_tables = array();
if (!empty($bl_config_diag["rules"])) {
	foreach ($bl_config_diag["rules"] as $bidx => $brule) {
		$tname = "layer7_bld_" . (int)$bidx;
		$tent = array();
		exec("/sbin/pfctl -t " . escapeshellarg($tname) . " -T show 2>/dev/null", $tent, $tcode);
		$pf_bld_tables[$tname] = array(
			"exists" => ($tcode === 0),
			"count" => ($tcode === 0) ? count(array_filter($tent, 'strlen')) : -1,
			"name" => $brule["name"] ?? "rule{$bidx}",
			"enabled" => !empty($brule["enabled"]),
			"ready" => false
		);
	}
}
$pf_tables_raw = array();
exec("/sbin/pfctl -s Tables 2>/dev/null", $pf_tables_raw, $pf_tables_rc);
$pf_tables_map = array();
if ($pf_tables_rc === 0) {
	foreach ($pf_tables_raw as $line) {
		$line = trim((string)$line);
		if ($line !== "") {
			$pf_tables_map[$line] = true;
		}
	}
}
$pf_active_rules_raw = array();
exec("/sbin/pfctl -sr 2>/dev/null", $pf_active_rules_raw, $pf_active_rules_rc);

$pf_block_ready = layer7_diag_table_ready("layer7_block", $pf_tables_map, $pf_active_rules_raw);
$pf_block_dst_ready = layer7_diag_table_ready("layer7_block_dst", $pf_tables_map, $pf_active_rules_raw);
$pf_tag_ready = layer7_diag_table_ready("layer7_tagged", $pf_tables_map, $pf_active_rules_raw);

foreach ($pf_bld_tables as $btname => $bt) {
	$pf_bld_tables[$btname]["ready"] = layer7_diag_table_ready($btname, $pf_tables_map, $pf_active_rules_raw);
}

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
$pf_active_any_rules_hits = array();
exec("/sbin/pfctl -sr 2>/dev/null | /usr/bin/grep 'layer7:' 2>/dev/null", $pf_active_any_rules_hits, $pf_active_any_rules_code);
$pf_active_any_rules_loaded = ($pf_active_any_rules_code === 0 && count($pf_active_any_rules_hits) > 0);

$pf_active_block_rules_hits = array();
exec("/sbin/pfctl -sr 2>/dev/null | /usr/bin/grep 'layer7:block:' 2>/dev/null", $pf_active_block_rules_hits, $pf_active_block_rules_code);
$pf_active_block_rules_loaded = ($pf_active_block_rules_code === 0 && count($pf_active_block_rules_hits) > 0);

$pf_required_tables_ok = layer7_diag_pf_required_tables_ready($pf_tables_map, $bl_config_diag, $pf_active_rules_raw);
$pf_any_missing = !$pf_required_tables_ok;
$pf_enforcement_real_ok = ($pf_active_block_rules_loaded && $pf_required_tables_ok);

$pf_anti_dot_hits = array();
exec("/sbin/pfctl -sr 2>/dev/null | /usr/bin/grep 'layer7:anti-' 2>/dev/null", $pf_anti_dot_hits, $pf_anti_dot_code);
$pf_anti_dot_loaded = ($pf_anti_dot_code === 0 && count($pf_anti_dot_hits) > 0);

$unbound_anti_doh = layer7_unbound_anti_doh_configured();

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
		<h2 class="panel-title"><?= l7_t("Layer 7 - Diagnosticos"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("diagnosticos"); ?>
		<div class="layer7-content">

		<?php if ($sigusr1_sent) { ?>
		<div class="alert alert-success"><?= l7_t("SIGUSR1 enviado. As estatisticas aparecem nos logs abaixo."); ?></div>
		<?php } ?>
		<?php if ($sighup_sent) { ?>
		<div class="alert alert-success"><?= l7_t("SIGHUP enviado. O daemon recarregou a configuracao."); ?></div>
		<?php } ?>
		<?php if ($anti_doh_result !== null) { ?>
		<div class="alert alert-<?= $anti_doh_result["ok"] ? "success" : "danger"; ?>"><?= htmlspecialchars($anti_doh_result["msg"]); ?></div>
		<?php } ?>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Resumo operacional"); ?></div>
			<div class="layer7-admin-block__body">
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

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Tabelas PF (enforcement)"); ?></div>
			<div class="layer7-admin-block__body">
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
						<br><span class="text-warning"><?= l7_t("Arquivo presente, mas sem regras layer7:block no rules.debug."); ?></span>
						<?php } ?>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Arquivo nao encontrado no appliance atual."); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Filtro ativo"); ?></dt>
					<dd>
						<?php if ($pf_active_any_rules_loaded) { ?>
						<span class="text-success"><?= l7_t("Regras Layer7 encontradas em pfctl -sr."); ?></span>
						<?php } else { ?>
						<span class="text-warning"><?= l7_t("Regras Layer7 ainda nao apareceram em pfctl -sr."); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Enforcement real"); ?></dt>
					<dd>
						<?php if ($pf_enforcement_real_ok) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= l7_t("Regras layer7:block ativas e tabelas PF obrigatorias presentes."); ?></span>
						<?php } elseif ($pf_active_block_rules_loaded && !$pf_required_tables_ok) { ?>
						<span class="text-danger"><i class="fa fa-times-circle"></i> <?= l7_t("Regras layer7:block ativas, mas faltam tabelas PF obrigatorias."); ?></span>
						<?php } elseif ($pf_active_any_rules_loaded && !$pf_active_block_rules_loaded) { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Somente regras anti-bypass estao ativas; bloqueio por tabelas ainda nao esta ativo."); ?></span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Enforcement ainda nao validado no filtro ativo."); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_block</code></dt>
					<dd>
						<?php if ($pf_block_count >= 0) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i></span> <?= $pf_block_count; ?> <?= l7_t("entradas"); ?>
						<?php if ($pf_block_count > 0 && $pf_block_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_block_entries))); ?></code></small>
						<?php } ?>
						<?php } elseif ($pf_block_ready) { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Tabela referenciada no filtro activo (sem entradas no momento)."); ?></span>
						<?php } else { ?>
						<span class="text-danger"><i class="fa fa-times-circle"></i> <?= l7_t("Tabela nao existe"); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_block_dst</code></dt>
					<dd>
						<?php if ($pf_block_dst_count >= 0) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i></span> <?= $pf_block_dst_count; ?> <?= l7_t("entradas (destinos bloqueados)"); ?>
						<?php if ($pf_block_dst_count > 0 && $pf_block_dst_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_block_dst_entries))); ?></code></small>
						<?php } ?>
						<?php } elseif ($pf_block_dst_ready) { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Tabela referenciada no filtro activo (sem entradas no momento)."); ?></span>
						<?php } else { ?>
						<span class="text-danger"><i class="fa fa-times-circle"></i> <?= l7_t("Tabela nao existe"); ?></span>
						<?php } ?>
					</dd>

					<dt><code>layer7_tagged</code></dt>
					<dd>
						<?php if ($pf_tag_count >= 0) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i></span> <?= $pf_tag_count; ?> <?= l7_t("entradas"); ?>
						<?php if ($pf_tag_count > 0 && $pf_tag_count <= 20) { ?>
						<br><small><code><?= htmlspecialchars(implode(", ", array_map('trim', $pf_tag_entries))); ?></code></small>
						<?php } ?>
						<?php } elseif ($pf_tag_ready) { ?>
						<span class="text-muted"><?= l7_t("Tabela referenciada no filtro activo (sem entradas no momento)."); ?></span>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Tabela nao existe (opcional, usada com action=tag)."); ?></span>
						<?php } ?>
					</dd>

					<?php if (!empty($pf_bld_tables)) { ?>
					<?php foreach ($pf_bld_tables as $btname => $bt) { ?>
					<dt><code><?= htmlspecialchars($btname); ?></code></dt>
					<dd>
						<?php if ($bt["exists"]) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i></span>
						<?= $bt["count"]; ?> <?= l7_t("entradas"); ?>
						(<?= htmlspecialchars($bt["name"]); ?>
						<?php if ($bt["enabled"]) { ?>
						— <span class="label label-success"><?= l7_t("Activa"); ?></span>
						<?php } else { ?>
						— <span class="label label-default"><?= l7_t("Inactiva"); ?></span>
						<?php } ?>)
						<?php } elseif ($bt["ready"]) { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Tabela referenciada no filtro activo (sem entradas no momento)."); ?></span>
						(<?= htmlspecialchars($bt["name"]); ?>)
						<?php } else { ?>
						<span class="text-danger"><i class="fa fa-times-circle"></i> <?= l7_t("Tabela nao existe"); ?></span>
						(<?= htmlspecialchars($bt["name"]); ?>)
						<?php } ?>
					</dd>
					<?php } ?>
					<?php } ?>

					<?php if ($pf_any_missing) { ?>
					<dt></dt>
					<dd>
						<?php if ($pf_repair_result === true) { ?>
						<div class="alert alert-success" style="margin-top:8px; padding:8px 12px;">
							<i class="fa fa-check-circle"></i> <?= l7_t("Tabelas PF criadas e filtro recarregado com sucesso."); ?>
						</div>
						<?php } elseif ($pf_repair_result === false) { ?>
						<div class="alert alert-danger" style="margin-top:8px; padding:8px 12px;">
							<i class="fa fa-times-circle"></i> <?= l7_t("Erro: helper PF nao encontrado."); ?>
						</div>
						<?php } ?>
						<form method="post" class="layer7-inline-form" style="margin-top:8px;">
							<button type="submit" name="repair_pf_tables" value="1" class="btn btn-warning">
								<i class="fa fa-wrench"></i> <?= l7_t("Reparar tabelas PF"); ?>
							</button>
							<small class="text-muted" style="margin-left:8px;"><?= l7_t("Cria todas as tabelas em falta e recarrega o filtro."); ?></small>
						</form>
					</dd>
					<?php } ?>
				</dl>
			</div>
		</div>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Anti-bypass DNS"); ?></div>
			<div class="layer7-admin-block__body">
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
						<form method="post" class="layer7-inline-form" style="display:inline; margin-left:8px;">
							<button type="submit" name="remove_anti_doh" value="1" class="btn btn-xs btn-danger" title="<?= l7_t("Remove os overrides anti-DoH do Unbound."); ?>" onclick="return confirm(<?= json_encode(l7_t('Remover overrides anti-DoH do Unbound?')) ?>);">
								<i class="fa fa-trash"></i> <?= l7_t("Remover"); ?>
							</button>
						</form>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Overrides nao encontrados."); ?></span>
						<form method="post" class="layer7-inline-form" style="display:inline; margin-left:8px;">
							<button type="submit" name="configure_anti_doh" value="1" class="btn btn-xs btn-success" title="<?= l7_t("Adiciona NXDOMAIN para resolvers DoH conhecidos e reinicia o Unbound."); ?>">
								<i class="fa fa-magic"></i> <?= l7_t("Configurar agora"); ?>
							</button>
						</form>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Politica nDPI"); ?></dt>
					<dd>
						<span class="text-muted"><?= l7_t("Verifique se a politica 'anti-bypass-dns' esta ativa em Politicas (apps: DoH_DoT, iCloudPrivateRelay)."); ?></span>
					</dd>
				</dl>
			</div>
		</div>

		<?php
		$_diag_panels = array();
		if ($pf_rules_exists && count($pf_rules_preview) > 0) {
			$_diag_panels[] = array("id" => "l7d-snippet", "title" => l7_t("Snippet PF gerado"), "content" => htmlspecialchars(implode("\n", $pf_rules_preview)));
		}
		if (count($pf_generated_preview) > 0) {
			$_diag_panels[] = array("id" => "l7d-hook", "title" => l7_t("Regra publicada pelo hook"), "content" => htmlspecialchars(implode("\n", $pf_generated_preview)));
		}
		if ($pf_rules_debug_has_layer7 && count($pf_rules_debug_hits) > 0) {
			$_diag_panels[] = array("id" => "l7d-rulesdebug", "title" => l7_t("Trecho de rules.debug"), "content" => htmlspecialchars(implode("\n", array_slice($pf_rules_debug_hits, 0, 20))));
		}
		if ($pf_active_any_rules_loaded && count($pf_active_any_rules_hits) > 0) {
			$_diag_panels[] = array("id" => "l7d-pfctlsr", "title" => l7_t("Trecho de pfctl -sr"), "content" => htmlspecialchars(implode("\n", array_slice($pf_active_any_rules_hits, 0, 20))));
		}
		if (!empty($_diag_panels)) { ?>
		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Detalhes PF"); ?></div>
			<div class="layer7-admin-block__body">
			<?php foreach ($_diag_panels as $_dp) { ?>
				<div style="margin-bottom:8px;">
					<a data-toggle="collapse" href="#<?= $_dp["id"]; ?>" style="cursor:pointer;">
						<i class="fa fa-chevron-right"></i> <?= $_dp["title"]; ?>
					</a>
				</div>
				<div id="<?= $_dp["id"]; ?>" class="collapse">
					<pre class="pre-scrollable" style="max-height: 220px; font-size: 12px;"><?= $_dp["content"]; ?></pre>
				</div>
			<?php } ?>
			</div>
		</div>
		<?php } ?>

		<?php if ($status_ok && $pid !== null) { ?>
		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Acoes"); ?></div>
			<div class="layer7-admin-block__body">
			<div class="layer7-form-card">
				<form method="post" class="form-inline layer7-inline-form">
					<button type="submit" name="send_sigusr1" value="1" class="btn btn-info">
						<i class="fa fa-bar-chart"></i> <?= l7_t("Obter estatisticas (SIGUSR1)"); ?>
					</button>
					<button type="submit" name="send_sighup" value="1" class="btn btn-warning">
						<i class="fa fa-refresh"></i> <?= l7_t("Recarregar config (SIGHUP)"); ?>
					</button>
				</form>
			</div>
			</div>
		</div>
		<?php } ?>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Logs recentes"); ?></div>
			<div class="layer7-admin-block__body">
				<?php if (count($recent_logs) > 0) { ?>
				<pre class="pre-scrollable" style="max-height: 350px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $recent_logs)); ?></pre>
				<?php } else { ?>
				<div class="alert alert-info"><?= l7_t("Nenhum log do layer7d encontrado em /var/log/layer7d.log."); ?></div>
				<?php } ?>
			</div>
		</div>

		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
