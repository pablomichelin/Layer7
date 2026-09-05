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
	return layer7_policy_host_matches($flow_host, $rule_host);
}

function l7_test_ip_in_cidr($ip, $cidr)
{
	return layer7_ip_in_cidr($ip, $cidr);
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
	$exc = layer7_policy_expand_src_excludes($policy, $groups);
	if ($src_ip !== "") {
		foreach ($exc["hosts"] as $h) {
			if ($h === $src_ip) {
				return false;
			}
		}
		foreach ($exc["cidrs"] as $c) {
			if (layer7_ip_in_cidr($src_ip, $c)) {
				return false;
			}
		}
	}

	$inc = layer7_policy_expand_src_origins($policy, $groups);
	$src_hosts = $inc["hosts"];
	$src_cidrs = $inc["cidrs"];

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

	usort($exceptions, function ($a, $b) {
		$pa = isset($a["priority"]) ? (int)$a["priority"] : 500;
		$pb = isset($b["priority"]) ? (int)$b["priority"] : 500;
		return $pb - $pa;
	});

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
			$verdict_label = l7_test_verdict_label($action);
			$verdict_reason = l7_test_verdict_reason_exception($exc_id, $reason, $action);
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
				"label" => $verdict_label,
				"reason" => $verdict_reason,
				"detail" => "Excepcao '" . $exc_id . "' casou: " . $reason,
				"enforce" => $enforce
			);
			return array("results" => $results, "resolved_ips" => $resolved_ips, "mode" => $mode);
		}
	}

	usort($policies, function($a, $b) {
		$pa = isset($a["priority"]) ? (int)$a["priority"] : 50;
		$pb = isset($b["priority"]) ? (int)$b["priority"] : 50;
		return $pb - $pa;
	});

	$matched_policy = null;
	$catchall_candidate = null;
	$catchall_reason = "";
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

		$sel = layer7_policy_selectors_evaluate($pol, $domain, $ndpi_app, $ndpi_cat);
		if (empty($sel["matched"])) {
			$results[] = array(
				"type" => "policy", "id" => $pid, "name" => $pname,
				"action" => $paction, "priority" => $ppri,
				"matched" => false, "reason" => $sel["reason"]
			);
			continue;
		}

		$is_catchall = layer7_policy_is_catch_all($pol);
		$results[] = array(
			"type" => "policy", "id" => $pid, "name" => $pname,
			"action" => $paction, "priority" => $ppri,
			"matched" => true, "reason" => $sel["reason"],
			"final" => !$is_catchall
		);
		if ($is_catchall) {
			if ($catchall_candidate === null) {
				$catchall_candidate = $pol;
				$catchall_reason = $sel["reason"];
			}
			continue;
		}

		$matched_policy = $pol;
		$results[] = array(
			"type" => "verdict",
			"action" => $paction,
			"label" => l7_test_verdict_label($paction),
			"reason" => l7_test_verdict_reason_policy($pid, $paction),
			"detail" => "Politica '" . $pid . "' casou: " . $sel["reason"],
			"enforce" => $enforce
		);
		break;
	}

	if ($matched_policy === null && $catchall_candidate !== null) {
		$matched_policy = $catchall_candidate;
		$cpid = isset($catchall_candidate["id"]) ? (string)$catchall_candidate["id"] : "?";
		$caction = isset($catchall_candidate["action"])
		    ? (string)$catchall_candidate["action"] : "monitor";
		$results[] = array(
			"type" => "verdict",
			"action" => $caction,
			"label" => l7_test_verdict_label($caction),
			"reason" => l7_test_verdict_reason_policy($cpid, $caction),
			"detail" => "Politica '" . $cpid . "' casou: " . $catchall_reason,
			"enforce" => $enforce
		);
	}

	if ($matched_policy === null) {
		$default_action = $enforce ? "allow" : "monitor";
		$default_reason = $enforce ? "default_allow" : "default_monitor";
		$results[] = array(
			"type" => "verdict",
			"action" => $default_action,
			"label" => l7_test_verdict_label($default_action),
			"reason" => l7_test_verdict_reason_default($default_action),
			"detail" => "Nenhuma politica casou — " . $default_reason,
			"enforce" => $enforce
		);
	}

	return array("results" => $results, "resolved_ips" => $resolved_ips, "mode" => $mode);
}

function l7_test_verdict_label($action)
{
	switch ($action) {
	case "block":
		return l7_t("BLOQUEADO");
	case "allow":
		return l7_t("PERMITIDO");
	case "monitor":
		return l7_t("MONITORIZADO");
	default:
		return strtoupper((string)$action);
	}
}

function l7_test_verdict_reason_exception($exc_id, $match_reason, $action = "allow")
{
	$detail = $match_reason !== "" ? $match_reason : l7_t("origem");
	if ($action === "block") {
		return sprintf(l7_t("BLOQUEADO — excepcao `%s` (%s)"), $exc_id, $detail);
	}
	if ($action === "monitor") {
		return sprintf(l7_t("MONITORIZADO — excepcao `%s` (%s)"), $exc_id, $detail);
	}
	return sprintf(l7_t("PERMITIDO — excepcao `%s` (%s)"), $exc_id, $detail);
}

function l7_test_verdict_reason_policy($pid, $action)
{
	if ($action === "block") {
		return sprintf(l7_t("BLOQUEADO — politica `%s`"), $pid);
	}
	if ($action === "allow") {
		return sprintf(l7_t("PERMITIDO — politica `%s`"), $pid);
	}
	return sprintf(l7_t("MONITORIZADO — politica `%s`"), $pid);
}

function l7_test_verdict_reason_default($action)
{
	if ($action === "allow") {
		return l7_t("PERMITIDO — nenhuma regra aplicavel (default allow)");
	}
	return l7_t("MONITORIZADO — nenhuma regra aplicavel (modo monitor)");
}

if ($_POST["run_test"] ?? false) {
	$test_domain = trim($_POST["test_domain"] ?? "");
	$test_src_ip = trim($_POST["test_src_ip"] ?? "");
	$test_ndpi_app = trim($_POST["test_ndpi_app"] ?? "");
	$test_ndpi_cat = trim($_POST["test_ndpi_cat"] ?? "");
	if ($test_domain === "" && $test_ndpi_app === "") {
		$input_errors[] = l7_t("Indique pelo menos um dominio/IP ou app nDPI para testar.");
	} else {
		if ($test_src_ip !== "" && !layer7_ip_valid($test_src_ip)) {
			$input_errors[] = l7_t("IP de origem invalido.");
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

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Teste"));
include("head.inc");
?>
<?php layer7_render_tabs("policies"); ?>

<?php layer7_render_messages(); ?>

<?php layer7_render_policies_subnav("test"); ?>

<div class="alert alert-info" role="status">
	<?= htmlspecialchars(l7_t("Simule o que aconteceria a um fluxo de trafego com as politicas e excepcoes actuais. Util para diagnostico antes de activar o modo enforce.")); ?>
</div>

<div id="l7-test-root">

<div class="panel panel-default" id="l7-test">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - teste de politica"); ?></h2>
	</div>
	<div class="panel-body">
<?php
require_once("classes/Form.class.php");
$ndpi_app_opts = array("" => l7_t("— qualquer —"));
foreach ($ndpi_protos as $proto) {
	$ndpi_app_opts[$proto] = $proto;
}
$ndpi_cat_opts = array("" => l7_t("— qualquer —"));
foreach ($ndpi_cats as $cat) {
	$ndpi_cat_opts[$cat] = $cat;
}

$form = new Form(false);
$form->setAction("layer7_test.php#l7-test");
$sec = new Form_Section(l7_t("Parametros do teste"));

$domain_in = new Form_Input("test_domain", l7_t("Dominio ou IP destino"), "text", $test_domain);
$domain_in->setAttribute("maxlength", "255");
$domain_in->setAttribute("placeholder", "youtube.com ou 142.250.185.46");
$domain_in->setHelp(l7_t("Dominio (ex.: youtube.com) ou IPv4 de destino. Dominios sao comparados com match.hosts das politicas."));
$sec->addInput($domain_in);

$src_in = new Form_Input("test_src_ip", l7_t("IP de origem"), "text", $test_src_ip);
$src_in->setAttribute("maxlength", "48");
$src_in->setAttribute("placeholder", "10.0.85.50");
$src_in->setHelp(l7_t("Opcional. IPv4 do cliente. Vazio = ignora filtro por origem."));
$sec->addInput($src_in);

$app_sel = new Form_Select("test_ndpi_app", l7_t("App nDPI"), $test_ndpi_app, $ndpi_app_opts);
$app_sel->setHelp(l7_t("Opcional. Selecione a app nDPI que o fluxo teria."));
$sec->addInput($app_sel);

$cat_sel = new Form_Select("test_ndpi_cat", l7_t("Categoria nDPI"), $test_ndpi_cat, $ndpi_cat_opts);
$cat_sel->setHelp(l7_t("Opcional. Categoria nDPI do fluxo."));
$sec->addInput($cat_sel);

$submit_html = '<button type="submit" name="run_test" value="1" class="btn btn-primary">' .
	'<i class="fa fa-play"></i> ' . htmlspecialchars(l7_t("Testar")) . '</button>';
$sec->addInput(new Form_StaticText("", $submit_html));

$form->add($sec);
print($form);
?>
	</div>
</div>

		<?php if ($test_results !== null) {
			$res = $test_results["results"];
			$resolved = $test_results["resolved_ips"];
			$cur_mode = $test_results["mode"];
		?>
<div class="panel panel-default" id="l7-test-results">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Resultado do teste"); ?></h2>
	</div>
	<div class="panel-body">

			<?php if (!empty($resolved)) { ?>
			<div class="alert alert-info" role="status">
				<strong><?= l7_t("DNS:"); ?></strong>
				<?= htmlspecialchars($test_domain); ?> &rarr;
				<?= htmlspecialchars(implode(", ", $resolved)); ?>
			</div>
			<?php } ?>

			<div class="alert alert-info" role="status">
				<strong><?= l7_t("Modo actual:"); ?></strong>
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
			<div class="alert <?= $vclass; ?>" id="l7-test-verdict" role="status">
				<strong class="lead"><?= htmlspecialchars($verdict["label"] ?? l7_test_verdict_label($verdict["action"])); ?></strong>
				<p><?= htmlspecialchars($verdict["reason"]); ?></p>
				<?php if (!empty($verdict["detail"]) && ($verdict["detail"] ?? "") !== ($verdict["reason"] ?? "")) { ?>
				<p class="text-muted"><small><?= htmlspecialchars($verdict["detail"]); ?></small></p>
				<?php } ?>
				<?php if ($verdict["enforce"]) { ?>
				<p class="text-muted"><small><?= l7_t("Modo enforce activo: esta accao seria aplicada em producao."); ?></small></p>
				<?php } else { ?>
				<p class="text-muted"><small><?= l7_t("Modo monitor: nenhuma accao de bloqueio seria aplicada."); ?></small></p>
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
							<th><?= l7_t("Tipo"); ?></th>
							<th><code>id</code></th>
							<th><?= l7_t("Nome"); ?></th>
							<th><?= l7_t("Acao"); ?></th>
							<th><?= l7_t("Casou?"); ?></th>
							<th><?= l7_t("Motivo"); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($policy_rows as $pr) { ?>
						<tr class="<?= $pr["matched"] ? "success" : ""; ?>">
							<td><?= htmlspecialchars($pr["type"] === "exception" ? l7_t("Excepcao") : l7_t("Politica")); ?></td>
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

			<p class="help-block text-muted"><?= l7_t("Esta simulacao usa as politicas e excepcoes do JSON actual. O daemon nDPI pode ter resultados diferentes dependendo da classificacao real do trafego."); ?></p>
	</div>
</div>
		<?php } ?>

</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php require_once("foot.inc"); ?>
