<?php
##|+PRIV
##|*IDENT=page-services-layer7-blacklists
##|*NAME=Services: Layer 7 (blacklists)
##|*DESCR=Allow access to Layer 7 blacklists.
##|*MATCH=layer7_blacklists.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

function l7_bl_merged_categories($discovered, $custom_map)
{
	$idx = array();
	if (is_array($discovered) && isset($discovered["categories"]) && is_array($discovered["categories"])) {
		foreach ($discovered["categories"] as $cat) {
			$cid = strtolower(trim((string)($cat["id"] ?? "")));
			if ($cid === "") {
				continue;
			}
			$idx[$cid] = array(
				"id" => $cid,
				"domains_count" => (int)($cat["domains_count"] ?? 0),
				"custom_domains_count" => 0,
				"custom_only" => false
			);
		}
	}
	foreach ($custom_map as $cid => $domains) {
		$n = is_array($domains) ? count($domains) : 0;
		if (!isset($idx[$cid])) {
			$idx[$cid] = array(
				"id" => $cid,
				"domains_count" => $n,
				"custom_domains_count" => $n,
				"custom_only" => true
			);
		} else {
			$idx[$cid]["custom_domains_count"] = $n;
			$idx[$cid]["domains_count"] += $n;
		}
	}
	ksort($idx);
	return array_values($idx);
}

$input_errors = array();
$savemsg = "";

$bl_config = layer7_bl_config_load();
$discovered = layer7_bl_discovered_load();
$custom_map = layer7_bl_category_custom_get($bl_config);

/* POST: Download */
if (isset($_POST["do_download"])) {
	$bl_config["source_url"] = layer7_bl_official_manifest_url();
	if (!layer7_bl_config_save($bl_config)) {
		$input_errors[] = l7_t("Nao foi possivel guardar a configuracao de blacklists.");
	} else {
		layer7_bl_download_start();
		$savemsg = l7_t("Download iniciado. Acompanhe o progresso na secao «Log de download».");
	}
}

$bl_download_poll = isset($_POST["do_download"]) || layer7_bl_download_in_progress();

/* POST: Save rule */
if (isset($_POST["save_rule"])) {
	$ridx = $_POST["rule_index"] ?? "";
	$rname = trim($_POST["rule_name"] ?? "");
	$renabled = isset($_POST["rule_enabled"]);
	$rforce_dns = isset($_POST["rule_force_dns"]);
	$rcats = isset($_POST["rule_cats"]) && is_array($_POST["rule_cats"]) ? $_POST["rule_cats"] : array();
	$rcidrs_raw = trim($_POST["rule_cidrs"] ?? "");
	$rexcept_raw = trim($_POST["rule_except"] ?? "");

	if ($rname === "") {
		$input_errors[] = l7_t("O nome da regra e obrigatorio.");
	}

	$rcidrs = array();
	if ($rcidrs_raw !== "") {
		foreach (preg_split('/[\r\n]+/', $rcidrs_raw) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === '#') continue;
			if (layer7_ip_or_cidr_valid($line)) {
				$rcidrs[] = $line;
			} else {
				$input_errors[] = l7_t("IP/CIDR invalido: ") . htmlspecialchars($line);
			}
		}
	}

	$rexcept = array();
	if ($rexcept_raw !== "") {
		foreach (preg_split('/[\r\n]+/', $rexcept_raw) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === '#') continue;
			if (layer7_ip_or_cidr_valid($line)) {
				$rexcept[] = $line;
			} else {
				$input_errors[] = l7_t("IP/CIDR de excepcao invalido: ") . htmlspecialchars($line);
			}
		}
	}

	if (empty($rcats)) {
		$input_errors[] = l7_t("Seleccione pelo menos uma categoria.");
	}

	if ($rforce_dns && empty($rcidrs)) {
		$input_errors[] = l7_t("\"Forcar DNS local\" requer pelo menos um CIDR de origem. Defina os IPs/CIDRs no campo Origem ou desactive a opcao.");
	}

	if (empty($input_errors)) {
		$rule = array(
			"name" => $rname,
			"enabled" => $renabled,
			"force_dns" => $rforce_dns,
			"categories" => array_values($rcats),
			"src_cidrs" => array_values($rcidrs),
			"except_ips" => array_values($rexcept)
		);
		if (!isset($bl_config["rules"]) || !is_array($bl_config["rules"])) {
			$bl_config["rules"] = array();
		}
		if ($ridx !== "" && isset($bl_config["rules"][(int)$ridx])) {
			$bl_config["rules"][(int)$ridx] = $rule;
		} else {
			if (count($bl_config["rules"]) >= 8) {
				$input_errors[] = l7_t("Maximo de 8 regras atingido.");
			} else {
				$bl_config["rules"][] = $rule;
			}
		}
		if (empty($input_errors)) {
			$bl_config["rules"] = array_values($bl_config["rules"]);
			$has_any = false;
			foreach ($bl_config["rules"] as $r) {
				if (!empty($r["enabled"]) && !empty($r["categories"])) {
					$has_any = true;
					break;
				}
			}
			$bl_config["enabled"] = $has_any;
				if (!layer7_bl_config_save($bl_config)) {
					$input_errors[] = l7_t("Nao foi possivel guardar a regra de blacklist.");
				} elseif (!layer7_bl_apply()) {
					$input_errors[] = l7_t("Configuracao guardada, mas nao foi possivel aplicar as blacklists.");
				} else {
					$savemsg = l7_t("Regra guardada. Daemon e regras PF actualizados.");
				}
		}
	}
}

/* POST: Delete rule */
if (isset($_POST["delete_rule"])) {
	$ridx = (int)($_POST["rule_index"] ?? -1);
	if (isset($bl_config["rules"][$ridx])) {
		array_splice($bl_config["rules"], $ridx, 1);
		$bl_config["rules"] = array_values($bl_config["rules"]);
		$has_any = false;
		foreach ($bl_config["rules"] as $r) {
			if (!empty($r["enabled"]) && !empty($r["categories"])) {
				$has_any = true;
				break;
			}
		}
		$bl_config["enabled"] = $has_any;
			if (!layer7_bl_config_save($bl_config)) {
				$input_errors[] = l7_t("Nao foi possivel remover a regra de blacklist.");
			} elseif (!layer7_bl_apply()) {
				$input_errors[] = l7_t("Regra removida, mas nao foi possivel aplicar as blacklists.");
			} else {
				$savemsg = l7_t("Regra removida.");
			}
	}
}

/* POST: Save whitelist */
if (isset($_POST["save_whitelist"])) {
	$wl_raw = trim($_POST["whitelist"] ?? "");
	$wl = layer7_bl_domains_normalize($wl_raw);
	$bl_config["whitelist"] = $wl;
	if (!layer7_bl_config_save($bl_config)) {
		$input_errors[] = l7_t("Nao foi possivel guardar a whitelist.");
	} elseif (!layer7_bl_apply()) {
		$input_errors[] = l7_t("Whitelist guardada, mas nao foi possivel aplicar as blacklists.");
	} else {
		$savemsg = l7_t("Whitelist guardada. Daemon recarregado.");
	}
}

/* POST: Save custom category/extension */
if (isset($_POST["save_cat_sites"])) {
	$cat_id = strtolower(trim($_POST["cat_id"] ?? ""));
	$cat_sites_raw = trim($_POST["cat_sites"] ?? "");
	if (!layer7_bl_category_id_valid($cat_id)) {
		$input_errors[] = l7_t("ID de categoria invalido. Use apenas letras minusculas, numeros, underscore e hifen.");
	}
	$cat_sites = layer7_bl_domains_normalize($cat_sites_raw);
	if (empty($cat_sites)) {
		$input_errors[] = l7_t("Adicione pelo menos um dominio valido na categoria.");
	}
	if (empty($input_errors)) {
		if (!isset($bl_config["category_custom"]) || !is_array($bl_config["category_custom"])) {
			$bl_config["category_custom"] = array();
		}
			$bl_config["category_custom"][$cat_id] = $cat_sites;
			ksort($bl_config["category_custom"]);
			if (!layer7_bl_config_save($bl_config)) {
				$input_errors[] = l7_t("Nao foi possivel guardar a categoria personalizada.");
			} elseif (!layer7_bl_apply()) {
				$input_errors[] = l7_t("Categoria guardada, mas nao foi possivel sincronizar os ficheiros de dominios.");
			} else {
				$savemsg = l7_t("Categoria personalizada guardada.");
			}
	}
}

/* POST: Delete custom category/extension */
if (isset($_POST["delete_cat_sites"])) {
		$cat_id = strtolower(trim($_POST["cat_id"] ?? ""));
		if (isset($bl_config["category_custom"][$cat_id])) {
			unset($bl_config["category_custom"][$cat_id]);
			if (!layer7_bl_config_save($bl_config)) {
				$input_errors[] = l7_t("Nao foi possivel remover a categoria personalizada.");
			} elseif (!layer7_bl_apply()) {
				$input_errors[] = l7_t("Categoria removida, mas nao foi possivel aplicar as blacklists.");
			} else {
				$savemsg = l7_t("Categoria personalizada removida.");
			}
		}
}

/* POST: Save settings */
if (isset($_POST["save_settings"])) {
	$bl_config["auto_update"] = isset($_POST["auto_update"]);
	$hours = (int)($_POST["update_interval_hours"] ?? 24);
	if ($hours < 1) $hours = 1;
	if ($hours > 168) $hours = 168;
	$bl_config["update_interval_hours"] = $hours;
	$bl_config["max_entries"] = (int)($_POST["max_entries"] ?? 5000000);
	$bl_config["mem_percent"] = (int)($_POST["mem_percent"] ?? 25);
	$bl_config = layer7_bl_normalize_resource_limits($bl_config);
	if (!layer7_bl_config_save($bl_config)) {
		$input_errors[] = l7_t("Nao foi possivel guardar as definicoes.");
	} else {
		layer7_bl_setup_cron($bl_config["auto_update"], $hours);
		layer7_bl_apply();
		$savemsg = l7_t("Definicoes guardadas.");
	}
}

/* Reload after any save */
$bl_config = layer7_bl_config_load();
$discovered = layer7_bl_discovered_load();
$custom_map = layer7_bl_category_custom_get($bl_config);
$merged_categories = l7_bl_merged_categories($discovered, $custom_map);
$bl_stats = layer7_bl_get_stats();
$last_update = layer7_bl_last_update();
$runtime_state = layer7_bl_runtime_state_load();
$fallback_state = layer7_bl_fallback_state_load();
$lkg_state = layer7_bl_lkg_state_load();
$content_sub = function_exists("layer7_content_subscription_status")
    ? layer7_content_subscription_status()
    : array("status" => "missing", "ok" => false, "message" => "", "exp" => 0);
$rules = isset($bl_config["rules"]) && is_array($bl_config["rules"]) ? $bl_config["rules"] : array();
$bl_config = layer7_bl_normalize_resource_limits($bl_config);
$bl_phys = layer7_bl_physmem_bytes();
$bl_budget = layer7_bl_mem_budget_bytes((int)$bl_config["mem_percent"]);
$bl_max_entries = (int)$bl_config["max_entries"];
/* Contagem única aproximada: categorias activas em qualquer regra (sem dup). */
$bl_union_cats = array();
foreach ($rules as $_r) {
	if (empty($_r["enabled"]) || empty($_r["categories"]) || !is_array($_r["categories"])) {
		continue;
	}
	foreach ($_r["categories"] as $_cid) {
		$bl_union_cats[strtolower((string)$_cid)] = true;
	}
}
$bl_union_domains = 0;
foreach ($merged_categories as $_cat) {
	if (isset($bl_union_cats[$_cat["id"]])) {
		$bl_union_domains += (int)$_cat["domains_count"];
	}
}

$edit_idx = -1;
if (isset($_GET["edit"])) {
	$edit_idx = (int)$_GET["edit"];
}
if (isset($_GET["add"])) {
	$edit_idx = -2;
}

$cat_edit = strtolower(trim($_GET["cat_edit"] ?? ""));
if (!layer7_bl_category_id_valid($cat_edit) || !isset($custom_map[$cat_edit])) {
	$cat_edit = "";
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Blacklists"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");
?>

<?php layer7_render_tabs("blacklists"); ?>

<div id="l7-blacklists-root">

<?php layer7_render_messages(); ?>

<div class="panel panel-default" id="l7-bl-header">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Categorias Web (Blacklists)"); ?></h2>
	</div>
</div>

<div class="panel panel-default" id="l7-download">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Origem oficial e download"); ?></h2>
	</div>
	<div class="panel-body">
		<form method="post" action="layer7_blacklists.php#l7-download">
			<div class="form-group">
				<label for="l7bl-source-url"><?= l7_t("Origem oficial primaria"); ?></label>
				<input type="text" class="form-control" id="l7bl-source-url" readonly
					value="<?= htmlspecialchars(layer7_bl_official_manifest_url()); ?>">
				<p class="help-block"><?= l7_t("O auto-update confiavel consome apenas manifesto assinado em HTTPS publicado pelo canal oficial Layer7/Systemup."); ?></p>
			</div>
			<div class="form-group">
				<label for="l7bl-mirrors"><?= l7_t("Mirrors oficiais"); ?></label>
				<textarea class="form-control" id="l7bl-mirrors" rows="2" readonly><?=
htmlspecialchars(implode("\n", layer7_bl_official_mirror_urls())); ?></textarea>
				<p class="help-block"><?= l7_t("Mirror e apenas disponibilidade. A confianca continua ancorada na assinatura do manifesto e na chave publica embutida no pacote."); ?></p>
			</div>
			<button type="submit" name="do_download" class="btn btn-primary">
				<i class="fa fa-download"></i> <?= l7_t("Download snapshot assinada"); ?>
			</button>
		</form>

		<div class="form-group" id="download_progress_wrap">
			<label for="download_log"><?= l7_t("Log de download"); ?></label>
			<p id="download_progress_status" class="help-block"></p>
			<textarea id="download_log" class="form-control" rows="8" readonly
				placeholder="<?= htmlspecialchars(l7_t("Aguardando inicio do script...")); ?>"><?= htmlspecialchars(layer7_bl_download_status()); ?></textarea>
			<button type="button" class="btn btn-default btn-xs"
				onclick="pollDownloadLog();">
				<i class="fa fa-refresh"></i> <?= l7_t("Actualizar log"); ?>
			</button>
		</div>

<?php
$cs_status = (string)($content_sub["status"] ?? "missing");
if (!empty($content_sub["ok"])) {
	$cs_badge = '<span class="label label-success">' . l7_t("OK") . '</span>';
} elseif ($cs_status === "expired") {
	$cs_badge = '<span class="label label-warning">' . l7_t("Expirada") . '</span>';
} elseif ($cs_status === "missing") {
	$cs_badge = '<span class="label label-warning">' . l7_t("Ausente") . '</span>';
} else {
	$cs_badge = '<span class="label label-danger">' . l7_t("Invalida") . '</span>';
}
$cs_exp_txt = "-";
if (!empty($content_sub["exp"])) {
	$cs_exp_txt = gmdate("Y-m-d H:i:s", (int)$content_sub["exp"]) . " UTC";
}
?>
		<div class="form-group">
			<label><?= l7_t("Subscricao de conteudo"); ?></label>
			<dl class="dl-horizontal">
				<dt><?= l7_t("Estado"); ?></dt>
				<dd><?= $cs_badge; ?>
					<small class="text-muted"><?= htmlspecialchars((string)($content_sub["message"] ?? "")); ?></small>
				</dd>
				<dt><?= l7_t("Valido ate"); ?></dt>
				<dd><?= htmlspecialchars($cs_exp_txt); ?></dd>
				<dt><?= l7_t("Ficheiro"); ?></dt>
				<dd><code>/var/db/layer7/content-subscription.json</code></dd>
			</dl>
			<p class="help-block"><?= l7_t("Sem token valido o update de blacklists correntes nao corre; o conteudo local e o enforce mantem-se. Force check-in com licenca activa para renovar. Ver runbook de subscricao de conteudo."); ?></p>
		</div>

		<div class="form-group">
			<label><?= l7_t("Estado da trust chain"); ?></label>
			<dl class="dl-horizontal">
				<dt><?= l7_t("Snapshot activa"); ?></dt>
				<dd><?= htmlspecialchars($runtime_state["snapshot_id"] ?? "-"); ?></dd>
				<dt><?= l7_t("Origem aplicada"); ?></dt>
				<dd><?= htmlspecialchars($runtime_state["manifest_url"] ?? "-"); ?></dd>
				<dt><?= l7_t("Fonte activa"); ?></dt>
				<dd><?= htmlspecialchars($runtime_state["source_role"] ?? "-"); ?></dd>
				<dt><?= l7_t("Ultima versao valida"); ?></dt>
				<dd><?= htmlspecialchars($lkg_state["snapshot_id"] ?? "-"); ?></dd>
				<dt><?= l7_t("LKG guardada em"); ?></dt>
				<dd><code>/usr/local/etc/layer7/blacklists/.last-known-good</code></dd>
				<dt><?= l7_t("Cache local"); ?></dt>
				<dd><code>/usr/local/etc/layer7/blacklists/.cache</code></dd>
				<dt><?= l7_t("Estado de degradacao"); ?></dt>
				<dd><?= htmlspecialchars($fallback_state["status"] ?? "-"); ?></dd>
				<dt><?= l7_t("Modo de fallback"); ?></dt>
				<dd><?= htmlspecialchars($fallback_state["mode"] ?? "-"); ?></dd>
				<dt><?= l7_t("Estado seguro mantido"); ?></dt>
				<dd><?= htmlspecialchars($fallback_state["safe_state"] ?? "-"); ?></dd>
				<dt><?= l7_t("Motivo da degradacao"); ?></dt>
				<dd><?= htmlspecialchars($fallback_state["reason"] ?? "-"); ?></dd>
				<dt><?= l7_t("Acao do operador"); ?></dt>
				<dd><?= htmlspecialchars($fallback_state["operator_action"] ?? "-"); ?></dd>
			</dl>
			<p class="help-block"><?= l7_t("Falha nova nao vira sucesso silencioso: a pagina mostra explicitamente se a trilha ficou healthy, degraded ou fail-closed e qual estado seguro foi preservado."); ?></p>
		</div>
	</div>
</div>

<div class="panel panel-default" id="l7-rules">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Regras de Blacklist"); ?></h2>
	</div>
	<div class="panel-body">
<?php if (empty($rules)): ?>
<div class="alert alert-info">
	<i class="fa fa-info-circle"></i>
	<?=l7_t("Nenhuma regra configurada. Adicione uma regra para comecar a bloquear categorias.")?>
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-striped table-hover">
<thead>
<tr>
	<th>#</th>
	<th><?=l7_t("Nome")?></th>
	<th><?=l7_t("Categorias")?></th>
	<th><?=l7_t("Origem (CIDRs)")?></th>
	<th><?=l7_t("Excepcoes")?></th>
	<th><?=l7_t("Estado")?></th>
	<th><?=l7_t("Tabela PF")?></th>
	<th><?=l7_t("Accoes")?></th>
</tr>
</thead>
<tbody>
<?php foreach ($rules as $idx => $rule): ?>
<tr>
	<td><?=$idx?></td>
	<td><strong><?=htmlspecialchars($rule["name"] ?? "regra_{$idx}")?></strong></td>
	<td>
		<?php
		$cats = $rule["categories"] ?? array();
		$cat_display = implode(", ", array_slice($cats, 0, 5));
		if (count($cats) > 5) $cat_display .= " (+" . (count($cats) - 5) . ")";
		echo htmlspecialchars($cat_display);
		?>
		<br><small class="text-muted"><?=count($cats)?> <?=l7_t("categorias")?></small>
	</td>
	<td>
		<?php
		$cidrs = $rule["src_cidrs"] ?? array();
		if (empty($cidrs)) {
			echo '<em class="text-warning">' . l7_t("Todos (global)") . '</em>';
		} else {
			echo htmlspecialchars(implode(", ", $cidrs));
		}
		?>
	</td>
	<td>
		<?php
		$excepts = $rule["except_ips"] ?? array();
		echo empty($excepts) ? '<em class="text-muted">-</em>' : htmlspecialchars(implode(", ", $excepts));
		?>
	</td>
	<td>
		<?php if (!empty($rule["enabled"])): ?>
		<span class="label label-success"><?=l7_t("Activa")?></span>
		<?php else: ?>
		<span class="label label-default"><?=l7_t("Inactiva")?></span>
		<?php endif; ?>
	</td>
	<td><code>layer7_bld_<?=$idx?></code></td>
	<td class="text-nowrap">
		<a href="?edit=<?= $idx; ?>" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i></a>
		<form method="post" action="layer7_blacklists.php#l7-rules" class="form-inline"
			onsubmit="return confirm(<?= json_encode(l7_t("Remover esta regra")); ?>);">
			<input type="hidden" name="rule_index" value="<?= $idx; ?>">
			<button type="submit" name="delete_rule" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
		</form>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if ($edit_idx === -2 || $edit_idx === -1 && empty($rules)): ?>
	<?php /* nothing — form shown below */ ?>
<?php endif; ?>

<?php if (count($rules) < 8): ?>
<p>
	<a href="?add=1" class="btn btn-success">
		<i class="fa fa-plus"></i> <?= l7_t("Adicionar regra"); ?>
	</a>
</p>
<?php endif; ?>

<?php
/* Edit/Add form */
$show_form = ($edit_idx >= -2 && ($edit_idx === -2 || isset($rules[$edit_idx])));
if ($edit_idx === -2) $show_form = true;
if ($edit_idx >= 0 && !isset($rules[$edit_idx])) $show_form = false;

if ($show_form):
	$erule = ($edit_idx >= 0 && isset($rules[$edit_idx])) ? $rules[$edit_idx] : array(
		"name" => "", "enabled" => true, "force_dns" => true,
		"categories" => array(), "src_cidrs" => array(), "except_ips" => array()
	);
	$form_title = ($edit_idx >= 0) ? l7_t("Editar regra") : l7_t("Nova regra");
?>
<h4><?= htmlspecialchars($form_title); ?></h4>
<form method="post" action="layer7_blacklists.php#l7-rules">
<?php if ($edit_idx >= 0): ?>
<input type="hidden" name="rule_index" value="<?= $edit_idx; ?>">
<?php endif; ?>

<div class="form-group">
	<label for="l7bl-rule-name"><?= l7_t("Nome da regra"); ?></label>
	<input type="text" class="form-control" name="rule_name" id="l7bl-rule-name"
		value="<?= htmlspecialchars($erule["name"]); ?>"
		placeholder="<?= l7_t("Ex: Funcionarios, Convidados, Alunos..."); ?>"
		required>
</div>

<div class="form-group">
	<div class="checkbox">
		<label>
			<input type="checkbox" name="rule_enabled" value="1"
				<?= (!empty($erule["enabled"])) ? "checked" : ""; ?>>
			<?= l7_t("Regra activa"); ?>
		</label>
	</div>
</div>

<div class="form-group">
	<div class="checkbox">
		<label>
			<input type="checkbox" name="rule_force_dns" value="1"
				<?= (!empty($erule["force_dns"])) ? "checked" : ""; ?>>
			<?= l7_t("Forcar DNS local para estes CIDRs"); ?>
		</label>
	</div>
	<p class="help-block">
		<?= l7_t("Redireciona todo o DNS (porta 53) dos CIDRs de origem para o Unbound local, mesmo que o dispositivo tenha DNS externo (ex: 8.8.8.8) configurado. Requer CIDRs de origem definidos."); ?>
	</p>
</div>

<div class="form-group">
	<label><?= l7_t("Categorias a bloquear"); ?></label>
	<?php if (empty($merged_categories)): ?>
	<div class="alert alert-warning">
		<i class="fa fa-exclamation-triangle"></i>
		<?= l7_t("Sem categorias disponiveis. Faca o download da UT1 ou adicione uma categoria personalizada abaixo."); ?>
	</div>
	<?php else: ?>
	<input type="text" id="rule_cat_filter" class="form-control"
		placeholder="<?= l7_t("Pesquisar categorias..."); ?>" onkeyup="filterRuleCats();">
	<p>
		<button type="button" class="btn btn-xs btn-default" onclick="toggleAllRuleCats(true);"><?= l7_t("Seleccionar todas"); ?></button>
		<button type="button" class="btn btn-xs btn-default" onclick="toggleAllRuleCats(false);"><?= l7_t("Limpar todas"); ?></button>
	</p>
	<div id="rule_cats_wrap">
	<?php
	$ecats = is_array($erule["categories"]) ? $erule["categories"] : array();
	foreach ($merged_categories as $cat):
		$cid = $cat["id"];
		$cnt = isset($cat["domains_count"]) ? (int)$cat["domains_count"] : 0;
		$custom_cnt = isset($cat["custom_domains_count"]) ? (int)$cat["custom_domains_count"] : 0;
		$checked = in_array($cid, $ecats) ? "checked" : "";
		$warn = ($cnt > 1000000) ? " &#9888;" : "";
	?>
	<div class="checkbox rule-cat-item" data-cat="<?= htmlspecialchars($cid); ?>">
		<label>
			<input type="checkbox" name="rule_cats[]" value="<?= htmlspecialchars($cid); ?>" <?= $checked; ?>>
			<?= htmlspecialchars($cid); ?> <small class="text-muted">(<?= number_format($cnt, 0, ",", "."); ?><?php if ($custom_cnt > 0): ?> +<?= number_format($custom_cnt, 0, ",", "."); ?> <?= l7_t("custom"); ?><?php endif; ?>)</small><?= $warn; ?>
		</label>
	</div>
	<?php endforeach; ?>
	</div>
	<p class="help-block"><?= l7_t("Seleccione as categorias que esta regra deve bloquear."); ?></p>
	<?php endif; ?>
</div>

<div class="form-group">
	<label for="l7bl-rule-cidrs"><?= l7_t("Origem — IPs ou CIDRs (um por linha)"); ?></label>
	<textarea class="form-control" name="rule_cidrs" id="l7bl-rule-cidrs" rows="4"
		placeholder="<?= l7_t("Ex: 192.168.10.0/24\nDeixe vazio para bloquear TODOS os clientes (global)."); ?>"><?= htmlspecialchars(implode("\n", $erule["src_cidrs"] ?? array())); ?></textarea>
	<p class="help-block"><?= l7_t("IPs/CIDRs de origem sujeitos a esta regra. Se vazio, aplica-se a TODOS os clientes."); ?></p>
</div>

<div class="form-group">
	<label for="l7bl-rule-except"><?= l7_t("Excepcoes — IPs excluidos desta regra (um por linha)"); ?></label>
	<textarea class="form-control" name="rule_except" id="l7bl-rule-except" rows="3"
		placeholder="<?= l7_t("Ex: 192.168.10.1 (director)"); ?>"><?= htmlspecialchars(implode("\n", $erule["except_ips"] ?? array())); ?></textarea>
	<p class="help-block"><?= l7_t("IPs que NAO sao bloqueados por esta regra, mesmo estando no CIDR de origem."); ?></p>
</div>

<p>
	<button type="submit" name="save_rule" class="btn btn-primary">
		<i class="fa fa-save"></i> <?= l7_t("Guardar regra"); ?>
	</button>
	<a href="layer7_blacklists.php" class="btn btn-default"><?= l7_t("Cancelar"); ?></a>
</p>
</form>
<?php endif; ?>
	</div>
</div>

<div class="panel panel-default" id="l7-custom">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Categorias personalizadas e extensoes"); ?></h2>
	</div>
	<div class="panel-body">
<?php if (empty($custom_map)): ?>
<div class="alert alert-info">
	<i class="fa fa-info-circle"></i>
	<?=l7_t("Nenhuma categoria personalizada configurada ainda.")?>
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-striped table-hover">
<thead>
<tr>
	<th><?=l7_t("Categoria")?></th>
	<th><?=l7_t("Tipo")?></th>
	<th><?=l7_t("Dominios custom")?></th>
	<th><?=l7_t("Accoes")?></th>
</tr>
</thead>
<tbody>
<?php foreach ($custom_map as $cid => $domains): ?>
<tr>
	<td><code><?=htmlspecialchars($cid)?></code></td>
	<td>
		<?php
		$is_ut1 = false;
		foreach ($merged_categories as $mc) {
			if (($mc["id"] ?? "") === $cid && empty($mc["custom_only"])) {
				$is_ut1 = true;
				break;
			}
		}
		?>
		<?= $is_ut1 ? l7_t("Extensao UT1") : l7_t("Categoria local") ?>
	</td>
	<td><?=number_format(count($domains), 0, ',', '.')?></td>
	<td class="text-nowrap">
		<a href="?cat_edit=<?= urlencode($cid); ?>" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i></a>
		<form method="post" action="layer7_blacklists.php#l7-custom" class="form-inline"
			onsubmit="return confirm(<?= json_encode(l7_t("Remover categoria personalizada?")); ?>);">
			<input type="hidden" name="cat_id" value="<?= htmlspecialchars($cid); ?>">
			<button type="submit" name="delete_cat_sites" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
		</form>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php
$_cat_editing = ($cat_edit !== "");
$cat_form_id = $_cat_editing ? $cat_edit : "";
$cat_form_sites = $_cat_editing && isset($custom_map[$cat_edit]) ? implode("\n", $custom_map[$cat_edit]) : "";
?>
<?php if (!$_cat_editing) { ?>
<p>
	<a data-toggle="collapse" href="#l7-new-cat-form" class="btn btn-sm btn-success">
		<i class="fa fa-plus"></i> <?= l7_t("Nova categoria personalizada"); ?>
	</a>
</p>
<?php } ?>
<div id="l7-new-cat-form" class="<?= $_cat_editing ? "" : "collapse"; ?>">
<h4><?= $_cat_editing ? l7_t("Editar categoria personalizada") : l7_t("Nova categoria personalizada"); ?></h4>
<form method="post" action="layer7_blacklists.php#l7-custom">
<div class="form-group">
	<label for="l7bl-cat-id"><?= l7_t("ID da categoria"); ?></label>
	<input type="text" class="form-control" name="cat_id" id="l7bl-cat-id" value="<?= htmlspecialchars($cat_form_id); ?>" placeholder="<?= l7_t("Ex: bloqueios_internos, erp, cloud_apps"); ?>" <?= $_cat_editing ? "readonly" : ""; ?> required>
	<p class="help-block"><?= l7_t("Use letras minusculas, numeros, underscore (_) e hifen (-)."); ?></p>
</div>
<div class="form-group">
	<label for="l7bl-cat-sites"><?= l7_t("Dominios da categoria (um por linha)"); ?></label>
	<textarea class="form-control" name="cat_sites" id="l7bl-cat-sites" rows="6" placeholder="<?= l7_t("Ex: site1.com\nsub.site2.com"); ?>" required><?= htmlspecialchars($cat_form_sites); ?></textarea>
</div>
<p>
	<button type="submit" name="save_cat_sites" class="btn btn-primary"><i class="fa fa-save"></i> <?= l7_t("Guardar categoria"); ?></button>
	<?php if ($_cat_editing): ?>
	<a href="layer7_blacklists.php" class="btn btn-default"><?= l7_t("Cancelar"); ?></a>
	<?php endif; ?>
</p>
</form>
</div>
	</div>
</div>

<div class="panel panel-default" id="l7-whitelist">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Whitelist Global"); ?></h2>
	</div>
	<div class="panel-body">
<form method="post" action="layer7_blacklists.php#l7-whitelist">
<div class="form-group">
	<label for="l7bl-whitelist"><?= l7_t("Dominios nunca bloqueados (um por linha)"); ?></label>
	<textarea class="form-control" name="whitelist" id="l7bl-whitelist" rows="5"
		placeholder="<?= l7_t("Ex: google.com\nyoutube.com"); ?>"><?= htmlspecialchars(implode("\n", $bl_config["whitelist"] ?? array())); ?></textarea>
	<p class="help-block"><?= l7_t("Dominios nesta lista nunca sao bloqueados por NENHUMA regra, mesmo que estejam nas categorias."); ?></p>
</div>
<p>
	<button type="submit" name="save_whitelist" class="btn btn-primary">
		<i class="fa fa-save"></i> <?= l7_t("Guardar whitelist"); ?>
	</button>
</p>
</form>
	</div>
</div>

<div class="panel panel-default" id="l7-bl-settings">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Definicoes e Estado"); ?></h2>
	</div>
	<div class="panel-body">
<form method="post" action="layer7_blacklists.php#l7-bl-settings">
<div class="form-group">
	<div class="checkbox">
		<label>
			<input type="checkbox" name="auto_update" value="1"
				<?= (!empty($bl_config["auto_update"])) ? "checked" : ""; ?>>
			<?= l7_t("Actualizacao automatica"); ?>
		</label>
	</div>
</div>
<div class="form-group">
	<label for="l7bl-update-hours"><?= l7_t("Intervalo (horas)"); ?></label>
	<input type="number" class="form-control" name="update_interval_hours" id="l7bl-update-hours"
		value="<?= (int)($bl_config["update_interval_hours"] ?? 24); ?>"
		min="1" max="168">
</div>
<hr>
<h4><?= l7_t("Recursos (memoria / teto de dominios)"); ?></h4>
<?php if ($bl_union_domains > $bl_max_entries): ?>
<div class="alert alert-warning">
	<i class="fa fa-exclamation-triangle"></i>
	<?=l7_t("As categorias activas somam aproximadamente")?>
	<strong><?=number_format($bl_union_domains, 0, ',', '.')?></strong>
	<?=l7_t("dominios, acima do teto configurado")?>
	(<strong><?=number_format($bl_max_entries, 0, ',', '.')?></strong>).
	<?=l7_t("O load pode truncar; preferir so as categorias necessarias, sobretudo em appliances com pouca RAM.")?>
</div>
<?php elseif ($bl_union_domains > 3000000): ?>
<div class="alert alert-info">
	<i class="fa fa-info-circle"></i>
	<?=l7_t("Categorias activas ~")?>
	<?=number_format($bl_union_domains, 0, ',', '.')?>
	<?=l7_t("dominios. Em appliances ≤4 GB RAM, active apenas o necessario (ex.: adult sozinha ja e pesada).")?>
</div>
<?php endif; ?>
<div class="form-group">
	<label for="l7bl-max-entries"><?= l7_t("Teto maximo de dominios em memoria"); ?></label>
	<input type="number" class="form-control" name="max_entries" id="l7bl-max-entries"
		value="<?= (int)$bl_max_entries; ?>"
		min="1000000" max="5000000" step="100000">
	<p class="help-block">
		<?= l7_t("Hard-cap do produto: 5 000 000. Default 5 000 000 (cabe UT1 adult ~4,6M). O daemon trunca com WARN ao atingir o teto."); ?>
	</p>
</div>
<div class="form-group">
	<label for="l7bl-mem-percent"><?= l7_t("Limite de memoria da blacklist (% da RAM do appliance)"); ?></label>
	<input type="number" class="form-control" name="mem_percent" id="l7bl-mem-percent"
		value="<?= (int)($bl_config["mem_percent"] ?? 25); ?>"
		min="5" max="50">
	<p class="help-block">
		<?= l7_t("Percentagem de hw.physmem reservavel ao load (5–50%, default 25%). Clamp interno: minimo 128 MB, maximo 1536 MB. O load para no primeiro limite (contagem ou bytes)."); ?>
		<?php if ($bl_phys > 0): ?>
		<br><?= l7_t("RAM detectada"); ?>:
		<strong><?= number_format($bl_phys / (1024 * 1024), 0, ",", "."); ?> MB</strong>
		— <?= l7_t("orcamento estimado"); ?>:
		<strong><?= number_format($bl_budget / (1024 * 1024), 0, ",", "."); ?> MB</strong>
		<?php endif; ?>
	</p>
</div>
<p>
	<button type="submit" name="save_settings" class="btn btn-primary">
		<i class="fa fa-save"></i> <?= l7_t("Guardar definicoes"); ?>
	</button>
</p>
</form>

<dl class="dl-horizontal">
	<dt><?= l7_t("Ultima actualizacao"); ?></dt>
	<dd><?= $last_update ? htmlspecialchars($last_update) : "<em>" . l7_t("Nunca") . "</em>"; ?></dd>
<?php if ($bl_stats): ?>
	<dt><?= l7_t("Regras activas"); ?></dt>
	<dd><?= (int)$bl_stats["rules_active"]; ?></dd>
	<dt><?= l7_t("Categorias carregadas"); ?></dt>
	<dd><?= (int)$bl_stats["categories_active"]; ?><?php if (!empty($merged_categories)): ?> / <?= count($merged_categories); ?> <?= l7_t("disponiveis"); ?><?php endif; ?></dd>
	<dt><?= l7_t("Dominios carregados"); ?></dt>
	<dd><?= number_format((int)$bl_stats["domains_loaded"], 0, ",", "."); ?></dd>
	<dt><?= l7_t("Lookups totais"); ?></dt>
	<dd><?= number_format((int)$bl_stats["lookups"], 0, ",", "."); ?></dd>
	<dt><?= l7_t("Hits de blacklist"); ?></dt>
	<dd><?= number_format((int)$bl_stats["hits"], 0, ",", "."); ?></dd>
<?php endif; ?>
</dl>

<?php if ($bl_stats && is_array($bl_stats["top_categories"]) && count($bl_stats["top_categories"]) > 0): ?>
<h4><?= l7_t("Top categorias bloqueadas"); ?></h4>
<table class="table table-condensed">
<thead><tr><th><?= l7_t("Categoria"); ?></th><th class="text-right"><?= l7_t("Hits"); ?></th></tr></thead>
<tbody>
<?php foreach ($bl_stats["top_categories"] as $tc): ?>
<tr>
	<td><?= htmlspecialchars($tc["cat"] ?? ""); ?></td>
	<td class="text-right"><?= number_format((int)($tc["hits"] ?? 0), 0, ",", "."); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<p class="text-muted small">
	<?= l7_t("Listas mantidas pela"); ?> <a href="https://dsi.ut-capitole.fr/blacklists/index_en.php" target="_blank">Universit&eacute; Toulouse Capitole</a>.
	<?= l7_t("Licenca"); ?> <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank">CC-BY-SA 4.0</a>.
</p>

	</div>
</div>

</div>

<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>

<script>
var _pollTimer = null;
var L7_POLL_WAIT = <?=json_encode(l7_t("Aguardando inicio do script..."))?>;
var L7_POLL_RUNNING = <?=json_encode(l7_t("Download em curso..."))?>;
var L7_POLL_DONE = <?=json_encode(l7_t("Download concluido."))?>;
var L7_POLL_ERR = <?=json_encode(l7_t("Erro ao obter progresso (HTTP %d)."))?>;

function l7SetDownloadStatus(html) {
	var el = document.getElementById('download_progress_status');
	if (el) {
		el.innerHTML = html || '';
	}
}

function pollDownloadLog() {
	var xhr = new XMLHttpRequest();
	xhr.open('GET', '/packages/layer7/layer7_bl_ajax.php?action=progress&_=' + Date.now(), true);
	xhr.onreadystatechange = function() {
		if (xhr.readyState !== 4) {
			return;
		}
		var ta = document.getElementById('download_log');
		if (!ta) {
			return;
		}
		if (xhr.status === 200) {
			ta.value = xhr.responseText;
			ta.scrollTop = ta.scrollHeight;
			if (xhr.responseText.indexOf('INFO: update complete') !== -1) {
				l7SetDownloadStatus('<i class="fa fa-check text-success"></i> ' + L7_POLL_DONE);
			} else if (xhr.responseText.trim() === '') {
				l7SetDownloadStatus('<i class="fa fa-spinner fa-spin"></i> ' + L7_POLL_WAIT);
			} else {
				l7SetDownloadStatus('<i class="fa fa-spinner fa-spin"></i> ' + L7_POLL_RUNNING);
			}
		} else {
			l7SetDownloadStatus('<span class="text-danger">' + L7_POLL_ERR.replace('%d', xhr.status) + '</span>');
		}
	};
	xhr.send();
}

function startDownloadPolling() {
	if (_pollTimer) {
		clearInterval(_pollTimer);
	}
	pollDownloadLog();
	_pollTimer = setInterval(pollDownloadLog, 2000);
	setTimeout(function() {
		if (_pollTimer) {
			clearInterval(_pollTimer);
			_pollTimer = null;
		}
	}, 300000);
	var wrap = document.getElementById('download_progress_wrap');
	if (wrap) {
		wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}
}

function filterRuleCats() {
	var filter = document.getElementById('rule_cat_filter').value.toLowerCase();
	var items = document.querySelectorAll('#rule_cats_wrap .rule-cat-item');
	for (var i = 0; i < items.length; i++) {
		var cat = items[i].getAttribute('data-cat');
		items[i].style.display = cat.indexOf(filter) !== -1 ? '' : 'none';
	}
}

function toggleAllRuleCats(state) {
	var cbs = document.querySelectorAll('#rule_cats_wrap input[type=checkbox]');
	for (var i = 0; i < cbs.length; i++) {
		var item = cbs[i].closest('.rule-cat-item');
		if (item && item.style.display !== 'none') {
			cbs[i].checked = state;
		}
	}
}

<?php if ($bl_download_poll): ?>
startDownloadPolling();
<?php endif; ?>
</script>

<?php require_once("foot.inc"); ?>
