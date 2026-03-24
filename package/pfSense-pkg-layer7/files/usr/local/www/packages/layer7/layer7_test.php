<?php
##|+PRIV
##|*IDENT=page-services-layer7-test
##|*NAME=Services: Layer 7 (test)
##|*DESCR=Allow access to Layer 7 policy test.
##|*MATCH=layer7_test.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$test_domain = "";
$test_src_ip = "";
$test_ndpi_app = "";
$test_ndpi_cat = "";
$test_results = null;

function l7_test_host_matches($flow_host, $rule_host)
{
	if ($flow_host === "" || $rule_host === "") {
		return false;
	}
	$flow_host = strtolower($flow_host);
	$rule_host = strtolower($rule_host);
	if ($flow_host === $rule_host) {
		return true;
	}
	$fl = strlen($flow_host);
	$rl = strlen($rule_host);
	if ($fl <= $rl) {
		return false;
	}
	if ($flow_host[$fl - $rl - 1] !== '.') {
		return false;
	}
	return substr($flow_host, $fl - $rl) === $rule_host;
}

function l7_test_ip_in_cidr($ip, $cidr)
{
	if (strpos($cidr, '/') === false) {
		return false;
	}
	list($net, $prefix) = explode('/', $cidr, 2);
	$prefix = (int)$prefix;
	$ip_long = ip2long($ip);
	$net_long = ip2long($net);
	if ($ip_long === false || $net_long === false) {
		return false;
	}
	if ($prefix === 0) {
		return true;
	}
	$mask = -1 << (32 - $prefix);
	return ($ip_long & $mask) === ($net_long & $mask);
}

function l7_test_schedule_active($schedule)
{
	if (!is_array($schedule)) {
		return true;
	}
	$days = isset($schedule["days"]) && is_array($schedule["days"]) ? $schedule["days"] : array();
	$start = isset($schedule["start"]) ? $schedule["start"] : "";
	$end = isset($schedule["end"]) ? $schedule["end"] : "";
	if (empty($days) || $start === "" || $end === "") {
		return true;
	}
	$day_map = array(0 => "sun", 1 => "mon", 2 => "tue", 3 => "wed", 4 => "thu", 5 => "fri", 6 => "sat");
	$today = $day_map[(int)date("w")];
	if (!in_array($today, $days, true)) {
		return false;
	}
	$now_min = (int)date("G") * 60 + (int)date("i");
	$parts_s = explode(":", $start);
	$parts_e = explode(":", $end);
	$s_min = (int)$parts_s[0] * 60 + (int)($parts_s[1] ?? 0);
	$e_min = (int)$parts_e[0] * 60 + (int)($parts_e[1] ?? 0);
	if ($s_min <= $e_min) {
		return $now_min >= $s_min && $now_min < $e_min;
	}
	return $now_min >= $s_min || $now_min < $e_min;
}

function l7_test_src_matches($policy, $src_ip, $groups)
{
	$src_hosts = isset($policy["match"]["src_hosts"]) && is_array($policy["match"]["src_hosts"]) ? $policy["match"]["src_hosts"] : array();
	$src_cidrs = isset($policy["match"]["src_cidrs"]) && is_array($policy["match"]["src_cidrs"]) ? $policy["match"]["src_cidrs"] : array();
	$pol_groups = isset($policy["match"]["groups"]) && is_array($policy["match"]["groups"]) ? $policy["match"]["groups"] : array();

	foreach ($pol_groups as $gid) {
		foreach ($groups as $grp) {
			if (isset($grp["id"]) && $grp["id"] === $gid) {
				if (isset($grp["cidrs"]) && is_array($grp["cidrs"])) {
					$src_cidrs = array_merge($src_cidrs, $grp["cidrs"]);
				}
				if (isset($grp["hosts"]) && is_array($grp["hosts"])) {
					$src_hosts = array_merge($src_hosts, $grp["hosts"]);
				}
			}
		}
	}

	if (empty($src_hosts) && empty($src_cidrs)) {
		return true;
	}
	if ($src_ip === "") {
		return false;
	}
	foreach ($src_hosts as $h) {
		if ($h === $src_ip) {
			return true;
		}
	}
	foreach ($src_cidrs as $c) {
		if (l7_test_ip_in_cidr($src_ip, $c)) {
			return true;
		}
	}
	return false;
}

function l7_run_policy_test($domain, $src_ip, $ndpi_app, $ndpi_cat)
{
	$data = layer7_load_or_default();
	$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
		? $data["layer7"]["policies"] : array();
	$exceptions = isset($data["layer7"]["exceptions"]) && is_array($data["layer7"]["exceptions"])
		? $data["layer7"]["exceptions"] : array();
	$groups = isset($data["layer7"]["groups"]) && is_array($data["layer7"]["groups"])
		? $data["layer7"]["groups"] : array();
	$mode = isset($data["layer7"]["mode"]) ? $data["layer7"]["mode"] : "monitor";
	$enforce = ($mode === "enforce");

	$results = array();

	$resolved_ips = array();
	if ($domain !== "" && !layer7_ipv4_valid($domain)) {
		$dns = @gethostbynamel($domain);
		if (is_array($dns) && !empty($dns)) {
			$resolved_ips = array_slice($dns, 0, 5);
		}
	}

	foreach ($exceptions as $exc) {
		if (empty($exc["enabled"])) {
			continue;
		}
		$exc_id = isset($exc["id"]) ? (string)$exc["id"] : "?";
		$matches = false;
		$reason = "";

		if ($src_ip !== "") {
			$exc_hosts = isset($exc["hosts"]) && is_array($exc["hosts"]) ? $exc["hosts"] : array();
			$exc_cidrs = isset($exc["cidrs"]) && is_array($exc["cidrs"]) ? $exc["cidrs"] : array();
			foreach ($exc_hosts as $h) {
				if ($h === $src_ip) {
					$matches = true;
					$reason = "IP origem = " . $src_ip;
					break;
				}
			}
			if (!$matches) {
				foreach ($exc_cidrs as $c) {
					if (l7_test_ip_in_cidr($src_ip, $c)) {
						$matches = true;
						$reason = "CIDR " . $c;
						break;
					}
				}
			}
		}

		if ($matches) {
			$action = isset($exc["action"]) ? $exc["action"] : "allow";
			$results[] = array(
				"type" => "exception",
				"id" => $exc_id,
				"name" => $exc_id,
				"action" => $action,
				"matched" => true,
				"reason" => $reason,
				"final" => true
			);
			$results[] = array(
				"type" => "verdict",
				"action" => $action,
				"reason" => "Excepcao '" . $exc_id . "' casou: " . $reason,
				"enforce" => $enforce
			);
			return array("results" => $results, "resolved_ips" => $resolved_ips, "mode" => $mode);
		}
	}

	$matched_policy = null;
	foreach ($policies as $pol) {
		if (empty($pol["enabled"])) {
			continue;
		}
		$pid = isset($pol["id"]) ? (string)$pol["id"] : "?";
		$pname = isset($pol["name"]) ? (string)$pol["name"] : $pid;
		$paction = isset($pol["action"]) ? (string)$pol["action"] : "monitor";
		$ppri = isset($pol["priority"]) ? (int)$pol["priority"] : 0;

		$sched = isset($pol["schedule"]) ? $pol["schedule"] : null;
		if (!l7_test_schedule_active($sched)) {
			$results[] = array(
				"type" => "policy", "id" => $pid, "name" => $pname,
				"action" => $paction, "priority" => $ppri,
				"matched" => false, "reason" => "Fora do horario"
			);
			continue;
		}

		if (!l7_test_src_matches($pol, $src_ip, $groups)) {
			$results[] = array(
				"type" => "policy", "id" => $pid, "name" => $pname,
				"action" => $paction, "priority" => $ppri,
				"matched" => false, "reason" => "IP origem nao corresponde"
			);
			continue;
		}

		$app_match = true;
		$apps = isset($pol["match"]["ndpi_app"]) && is_array($pol["match"]["ndpi_app"]) ? $pol["match"]["ndpi_app"] : array();
		if (!empty($apps)) {
			if ($ndpi_app === "" || !in_array($ndpi_app, $apps, true)) {
				$app_match = false;
			}
		}

		$cat_match = true;
		$cats = isset($pol["match"]["ndpi_category"]) && is_array($pol["match"]["ndpi_category"]) ? $pol["match"]["ndpi_category"] : array();
		if (!empty($cats)) {
			if ($ndpi_cat === "" || !in_array($ndpi_cat, $cats, true)) {
				$cat_match = false;
			}
		}

		$host_match = true;
		$hosts = isset($pol["match"]["hosts"]) && is_array($pol["match"]["hosts"]) ? $pol["match"]["hosts"] : array();
		if (!empty($hosts)) {
			$host_match = false;
			if ($domain !== "") {
				foreach ($hosts as $rh) {
					if (l7_test_host_matches($domain, $rh)) {
						$host_match = true;
						break;
					}
				}
			}
		}

		if (!$app_match) {
			$results[] = array(
				"type" => "policy", "id" => $pid, "name" => $pname,
				"action" => $paction, "priority" => $ppri,
				"matched" => false, "reason" => "App nDPI nao corresponde"
			);
			continue;
		}
		if (!$cat_match) {
			$results[] = array(
				"type" => "policy", "id" => $pid, "name" => $pname,
				"action" => $paction, "priority" => $ppri,
				"matched" => false, "reason" => "Categoria nDPI nao corresponde"
			);
			continue;
		}
		if (!$host_match) {
			$results[] = array(
				"type" => "policy", "id" => $pid, "name" => $pname,
				"action" => $paction, "priority" => $ppri,
				"matched" => false, "reason" => "Dominio nao corresponde"
			);
			continue;
		}

		$match_reasons = array();
		if (!empty($apps)) {
			$match_reasons[] = "app=" . $ndpi_app;
		}
		if (!empty($cats)) {
			$match_reasons[] = "cat=" . $ndpi_cat;
		}
		if (!empty($hosts) && $domain !== "") {
			$match_reasons[] = "host=" . $domain;
		}
		if (empty($match_reasons)) {
			$match_reasons[] = "sem filtros (match-all)";
		}

		$results[] = array(
			"type" => "policy", "id" => $pid, "name" => $pname,
			"action" => $paction, "priority" => $ppri,
			"matched" => true, "reason" => implode(", ", $match_reasons),
			"final" => true
		);
		$matched_policy = $pol;

		$results[] = array(
			"type" => "verdict",
			"action" => $paction,
			"reason" => "Politica '" . $pid . "' casou: " . implode(", ", $match_reasons),
			"enforce" => $enforce
		);
		break;
	}

	if ($matched_policy === null) {
		$default_action = $enforce ? "allow" : "monitor";
		$default_reason = $enforce ? "default_allow" : "default_monitor";
		$results[] = array(
			"type" => "verdict",
			"action" => $default_action,
			"reason" => "Nenhuma politica casou — " . $default_reason,
			"enforce" => $enforce
		);
	}

	return array("results" => $results, "resolved_ips" => $resolved_ips, "mode" => $mode);
}

if ($_POST["run_test"] ?? false) {
	$test_domain = trim($_POST["test_domain"] ?? "");
	$test_src_ip = trim($_POST["test_src_ip"] ?? "");
	$test_ndpi_app = trim($_POST["test_ndpi_app"] ?? "");
	$test_ndpi_cat = trim($_POST["test_ndpi_cat"] ?? "");
	if ($test_domain === "" && $test_ndpi_app === "") {
		$input_errors[] = gettext("Indique pelo menos um dominio/IP ou app nDPI para testar.");
	} else {
		if ($test_src_ip !== "" && !layer7_ipv4_valid($test_src_ip)) {
			$input_errors[] = gettext("IP de origem invalido.");
		}
	}
	if (empty($input_errors)) {
		$test_results = l7_run_policy_test($test_domain, $test_src_ip, $test_ndpi_app, $test_ndpi_cat);
	}
}

$ndpi_list = layer7_ndpi_list();
$ndpi_protos = isset($ndpi_list["protocols"]) ? $ndpi_list["protocols"] : array();
$ndpi_cats = isset($ndpi_list["categories"]) ? $ndpi_list["categories"] : array();
sort($ndpi_protos);
sort($ndpi_cats);

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Teste"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - teste de politica"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("test"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead"><?= gettext("Simule o que aconteceria a um fluxo de trafego com as politicas e excepcoes actuais. Util para diagnostico antes de activar o modo enforce."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Parametros do teste"); ?></h3>
			<form method="post" class="form-horizontal">

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Dominio ou IP destino"); ?></label>
					<div class="col-sm-6">
						<input type="text" name="test_domain" class="form-control" maxlength="255"
							value="<?= htmlspecialchars($test_domain); ?>"
							placeholder="youtube.com ou 142.250.185.46" />
						<p class="help-block"><?= gettext("Dominio (ex.: youtube.com) ou IPv4 de destino. Dominios sao comparados com match.hosts das politicas."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("IP de origem"); ?></label>
					<div class="col-sm-6">
						<input type="text" name="test_src_ip" class="form-control" maxlength="48"
							value="<?= htmlspecialchars($test_src_ip); ?>"
							placeholder="10.0.85.50" />
						<p class="help-block"><?= gettext("Opcional. IPv4 do cliente. Vazio = ignora filtro por origem."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("App nDPI"); ?></label>
					<div class="col-sm-6">
						<select name="test_ndpi_app" class="form-control">
							<option value=""><?= gettext("— qualquer —"); ?></option>
							<?php foreach ($ndpi_protos as $proto) { ?>
							<option value="<?= htmlspecialchars($proto); ?>" <?= $test_ndpi_app === $proto ? 'selected="selected"' : ''; ?>><?= htmlspecialchars($proto); ?></option>
							<?php } ?>
						</select>
						<p class="help-block"><?= gettext("Opcional. Selecione a app nDPI que o fluxo teria."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Categoria nDPI"); ?></label>
					<div class="col-sm-6">
						<select name="test_ndpi_cat" class="form-control">
							<option value=""><?= gettext("— qualquer —"); ?></option>
							<?php foreach ($ndpi_cats as $cat) { ?>
							<option value="<?= htmlspecialchars($cat); ?>" <?= $test_ndpi_cat === $cat ? 'selected="selected"' : ''; ?>><?= htmlspecialchars($cat); ?></option>
							<?php } ?>
						</select>
						<p class="help-block"><?= gettext("Opcional. Categoria nDPI do fluxo."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="run_test" value="1" class="btn btn-primary">
							<i class="fa fa-play"></i> <?= gettext("Testar"); ?>
						</button>
					</div>
				</div>
			</form>
		</div>

		<?php if ($test_results !== null) {
			$res = $test_results["results"];
			$resolved = $test_results["resolved_ips"];
			$cur_mode = $test_results["mode"];
		?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Resultado do teste"); ?></h3>

			<?php if (!empty($resolved)) { ?>
			<div class="alert alert-info">
				<strong><?= gettext("DNS:"); ?></strong>
				<?= htmlspecialchars($test_domain); ?> &rarr;
				<?= htmlspecialchars(implode(", ", $resolved)); ?>
			</div>
			<?php } ?>

			<div class="alert alert-info">
				<strong><?= gettext("Modo actual:"); ?></strong>
				<code><?= htmlspecialchars($cur_mode); ?></code>
			</div>

			<?php
			$verdict = null;
			foreach ($res as $r) {
				if ($r["type"] === "verdict") {
					$verdict = $r;
				}
			}
			if ($verdict !== null) {
				$vclass = "alert-warning";
				if ($verdict["action"] === "block") {
					$vclass = "alert-danger";
				} elseif ($verdict["action"] === "allow") {
					$vclass = "alert-success";
				} elseif ($verdict["action"] === "monitor") {
					$vclass = "alert-info";
				}
			?>
			<div class="alert <?= $vclass; ?>" style="font-size:15px;">
				<strong><?= gettext("Veredicto:"); ?></strong>
				<span class="label label-<?= $verdict["action"] === "block" ? "danger" : ($verdict["action"] === "allow" ? "success" : "default"); ?>">
					<?= htmlspecialchars($verdict["action"]); ?>
				</span>
				&mdash; <?= htmlspecialchars($verdict["reason"]); ?>
				<?php if ($verdict["enforce"]) { ?>
				<br /><small class="text-muted"><?= gettext("Modo enforce activo: esta accao seria aplicada em producao."); ?></small>
				<?php } else { ?>
				<br /><small class="text-muted"><?= gettext("Modo monitor: nenhuma accao de bloqueio seria aplicada."); ?></small>
				<?php } ?>
			</div>
			<?php } ?>

			<?php
			$policy_rows = array();
			foreach ($res as $r) {
				if ($r["type"] === "policy" || $r["type"] === "exception") {
					$policy_rows[] = $r;
				}
			}
			if (!empty($policy_rows)) { ?>
			<div class="table-responsive">
				<table class="table table-striped table-hover">
					<thead>
						<tr>
							<th><?= gettext("Tipo"); ?></th>
							<th><code>id</code></th>
							<th><?= gettext("Nome"); ?></th>
							<th><?= gettext("Acao"); ?></th>
							<th><?= gettext("Casou?"); ?></th>
							<th><?= gettext("Motivo"); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($policy_rows as $pr) { ?>
						<tr class="<?= $pr["matched"] ? "success" : ""; ?>">
							<td><?= htmlspecialchars($pr["type"] === "exception" ? gettext("Excepcao") : gettext("Politica")); ?></td>
							<td><code><?= htmlspecialchars($pr["id"]); ?></code></td>
							<td><?= htmlspecialchars($pr["name"]); ?></td>
							<td><span class="label label-<?= $pr["action"] === "block" ? "danger" : ($pr["action"] === "allow" ? "success" : "default"); ?>"><?= htmlspecialchars($pr["action"]); ?></span></td>
							<td><?= $pr["matched"] ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-muted"></i>'; ?></td>
							<td class="small"><?= htmlspecialchars($pr["reason"]); ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
			<?php } ?>

			<p class="layer7-muted-note small"><?= gettext("Esta simulacao usa as politicas e excepcoes do JSON actual. O daemon nDPI pode ter resultados diferentes dependendo da classificacao real do trafego."); ?></p>
		</div>
		<?php } ?>
		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
