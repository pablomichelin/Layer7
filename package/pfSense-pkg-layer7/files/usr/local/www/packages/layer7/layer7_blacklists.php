<?php
##|+PRIV
##|*IDENT=page-services-layer7-blacklists
##|*NAME=Services: Layer 7 (blacklists)
##|*DESCR=Allow access to Layer 7 blacklists.
##|*MATCH=layer7_blacklists.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$input_errors = array();
$savemsg = "";

$bl_config = layer7_bl_config_load();
$discovered = layer7_bl_discovered_load();

/* POST: Download */
if (isset($_POST["do_download"])) {
	$url = trim($_POST["source_url"] ?? "");
	if ($url !== "") {
		$bl_config["source_url"] = $url;
		layer7_bl_config_save($bl_config);
	}
	layer7_bl_download_start();
	$savemsg = l7_t("Download iniciado. Acompanhe o progresso abaixo.");
}

/* POST: Save rule */
if (isset($_POST["save_rule"])) {
	$ridx = $_POST["rule_index"] ?? "";
	$rname = trim($_POST["rule_name"] ?? "");
	$renabled = isset($_POST["rule_enabled"]);
	$rcats = isset($_POST["rule_cats"]) && is_array($_POST["rule_cats"]) ? $_POST["rule_cats"] : array();
	$rcidrs_raw = trim($_POST["rule_cidrs"] ?? "");
	$rexcept_raw = trim($_POST["rule_except"] ?? "");

	if ($rname === "") {
		$input_errors[] = l7_t("O nome da regra e obrigatorio.");
	}
	if (empty($rcats)) {
		$input_errors[] = l7_t("Seleccione pelo menos uma categoria.");
	}

	$rcidrs = array();
	if ($rcidrs_raw !== "") {
		foreach (preg_split('/[\r\n]+/', $rcidrs_raw) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === '#') continue;
			if (layer7_ipv4_valid($line) || layer7_cidr_valid($line)) {
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
			if (layer7_ipv4_valid($line) || layer7_cidr_valid($line)) {
				$rexcept[] = $line;
			} else {
				$input_errors[] = l7_t("IP/CIDR de excepcao invalido: ") . htmlspecialchars($line);
			}
		}
	}

	if (empty($input_errors)) {
		$rule = array(
			"name" => $rname,
			"enabled" => $renabled,
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
			layer7_bl_config_save($bl_config);
			layer7_bl_apply();
			$savemsg = l7_t("Regra guardada. Daemon e regras PF actualizados.");
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
		layer7_bl_config_save($bl_config);
		layer7_bl_apply();
		$savemsg = l7_t("Regra removida.");
	}
}

/* POST: Save whitelist */
if (isset($_POST["save_whitelist"])) {
	$wl_raw = trim($_POST["whitelist"] ?? "");
	$wl = array_values(array_filter(array_map("trim", preg_split('/[\r\n]+/', $wl_raw))));
	$bl_config["whitelist"] = $wl;
	layer7_bl_config_save($bl_config);
	layer7_bl_apply();
	$savemsg = l7_t("Whitelist guardada. Daemon recarregado.");
}

/* POST: Save settings */
if (isset($_POST["save_settings"])) {
	$bl_config["auto_update"] = isset($_POST["auto_update"]);
	$hours = (int)($_POST["update_interval_hours"] ?? 24);
	if ($hours < 1) $hours = 1;
	if ($hours > 168) $hours = 168;
	$bl_config["update_interval_hours"] = $hours;
	layer7_bl_config_save($bl_config);
	$cron_hour = $hours <= 24 ? "3" : "*/" . (int)($hours / 24);
	layer7_bl_setup_cron($bl_config["auto_update"], $cron_hour);
	$savemsg = l7_t("Definicoes guardadas.");
}

/* Reload after any save */
$bl_config = layer7_bl_config_load();
$discovered = layer7_bl_discovered_load();
$bl_stats = layer7_bl_get_stats();
$last_update = layer7_bl_last_update();
$rules = isset($bl_config["rules"]) && is_array($bl_config["rules"]) ? $bl_config["rules"] : array();

$edit_idx = -1;
if (isset($_GET["edit"])) {
	$edit_idx = (int)$_GET["edit"];
}
if (isset($_GET["add"])) {
	$edit_idx = -2;
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Blacklists"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");

layer7_render_styles();
?>

<div class="layer7-page">
<div class="panel panel-default">
<div class="panel-heading"><h2 class="panel-title"><?=l7_t("Categorias Web (Blacklists)")?></h2></div>
<div class="panel-body">

<?php layer7_render_tabs("blacklists"); ?>

<div class="layer7-content">

<?php layer7_render_messages(); ?>

<!-- SECTION 1: URL & Download -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=l7_t("URL e Download")?></h3>
<div class="layer7-form-card">
<form method="post">
<div class="form-group">
	<label><?=l7_t("URL da blacklist")?></label>
	<input type="text" class="form-control" name="source_url"
		value="<?=htmlspecialchars($bl_config["source_url"] ?? "")?>"
		placeholder="http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz">
	<p class="help-block"><?=l7_t("Endereco do arquivo blacklists.tar.gz (formato UT1 Toulouse).")?></p>
</div>
<div class="layer7-form-card__actions">
<button type="submit" name="do_download" class="btn btn-primary">
	<i class="fa fa-download"></i> <?=l7_t("Download")?>
</button>
</div>
</form>
</div>

<div class="layer7-readonly-block" style="margin-top:14px;">
	<label><?=l7_t("Log de download")?></label>
	<textarea id="download_log" class="form-control" rows="6" readonly
		style="font-family:monospace; font-size:12px; background:#f8f8f8;"><?=htmlspecialchars(layer7_bl_download_status())?></textarea>
	<button type="button" class="btn btn-default btn-xs" style="margin-top:6px;"
		onclick="pollDownloadLog();">
		<i class="fa fa-refresh"></i> <?=l7_t("Actualizar log")?>
	</button>
</div>
</div>

<!-- SECTION 2: Blacklist Rules -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=l7_t("Regras de Blacklist")?></h3>
<p class="layer7-lead"><?=l7_t("Cada regra define quais categorias bloquear e para quais IPs/CIDRs de origem. Permite bloqueio granular: ex. bloquear gambling para 192.168.10.0/24 mas nao para o director (192.168.10.1).")?></p>

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
	<td class="layer7-table-actions">
		<a href="?edit=<?=$idx?>" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i></a>
		<form method="post" style="display:inline;"
			onsubmit="return confirm('<?=l7_t("Remover esta regra?")?>');">
			<input type="hidden" name="rule_index" value="<?=$idx?>">
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
<div class="layer7-toolbar">
	<a href="?add=1" class="btn btn-success">
		<i class="fa fa-plus"></i> <?=l7_t("Adicionar regra")?>
	</a>
</div>
<?php endif; ?>

<?php
/* Edit/Add form */
$show_form = ($edit_idx >= -2 && ($edit_idx === -2 || isset($rules[$edit_idx])));
if ($edit_idx === -2) $show_form = true;
if ($edit_idx >= 0 && !isset($rules[$edit_idx])) $show_form = false;

if ($show_form):
	$erule = ($edit_idx >= 0 && isset($rules[$edit_idx])) ? $rules[$edit_idx] : array(
		"name" => "", "enabled" => true, "categories" => array(),
		"src_cidrs" => array(), "except_ips" => array()
	);
	$form_title = ($edit_idx >= 0) ? l7_t("Editar regra") : l7_t("Nova regra");
?>
<div class="layer7-form-card">
<h4 class="layer7-form-card__title"><?=$form_title?></h4>
<form method="post">
<?php if ($edit_idx >= 0): ?>
<input type="hidden" name="rule_index" value="<?=$edit_idx?>">
<?php endif; ?>

<div class="form-group">
	<label><?=l7_t("Nome da regra")?></label>
	<input type="text" class="form-control" name="rule_name"
		value="<?=htmlspecialchars($erule["name"])?>"
		placeholder="<?=l7_t("Ex: Funcionarios, Convidados, Alunos...")?>"
		style="max-width:400px;" required>
</div>

<div class="form-group">
	<label class="checkbox-inline">
		<input type="checkbox" name="rule_enabled" value="1"
			<?=(!empty($erule["enabled"])) ? "checked" : ""?>>
		<?=l7_t("Regra activa")?>
	</label>
</div>

<div class="form-group">
	<label><?=l7_t("Categorias a bloquear")?></label>
	<?php if ($discovered === null): ?>
	<div class="alert alert-warning" style="margin:0;">
		<i class="fa fa-exclamation-triangle"></i>
		<?=l7_t("Faca o download da lista primeiro para ver as categorias disponiveis.")?>
	</div>
	<?php else: ?>
	<input type="text" id="rule_cat_filter" class="form-control l7-filter" style="max-width:300px;"
		placeholder="<?=l7_t("Pesquisar categorias...")?>" onkeyup="filterRuleCats();">
	<div class="l7-bulk-tools">
		<button type="button" class="btn btn-xs btn-default" onclick="toggleAllRuleCats(true);"><?=l7_t("Seleccionar todas")?></button>
		<button type="button" class="btn btn-xs btn-default" onclick="toggleAllRuleCats(false);"><?=l7_t("Limpar todas")?></button>
	</div>
	<div class="l7-multiselect-wrap" id="rule_cats_wrap">
	<?php
	$ecats = is_array($erule["categories"]) ? $erule["categories"] : array();
	usort($discovered["categories"], function($a, $b) { return strcmp($a["id"], $b["id"]); });
	foreach ($discovered["categories"] as $cat):
		$cid = $cat["id"];
		$cnt = isset($cat["domains_count"]) ? (int)$cat["domains_count"] : 0;
		$checked = in_array($cid, $ecats) ? "checked" : "";
		$warn = ($cnt > 1000000) ? ' &#9888;' : '';
	?>
	<label class="rule-cat-item" data-cat="<?=htmlspecialchars($cid)?>">
		<input type="checkbox" name="rule_cats[]" value="<?=htmlspecialchars($cid)?>" <?=$checked?>>
		<?=htmlspecialchars($cid)?> <small class="text-muted">(<?=number_format($cnt, 0, ',', '.')?>)</small><?=$warn?>
	</label>
	<?php endforeach; ?>
	</div>
	<p class="help-block"><?=l7_t("Seleccione as categorias que esta regra deve bloquear.")?></p>
	<?php endif; ?>
</div>

<div class="form-group">
	<label><?=l7_t("Origem — IPs ou CIDRs (um por linha)")?></label>
	<textarea class="form-control" name="rule_cidrs" rows="4"
		placeholder="<?=l7_t("Ex: 192.168.10.0/24\nDeixe vazio para bloquear TODOS os clientes (global).")?>"
		style="font-family:monospace; max-width:400px;"><?=htmlspecialchars(implode("\n", $erule["src_cidrs"] ?? array()))?></textarea>
	<p class="help-block"><?=l7_t("IPs/CIDRs de origem sujeitos a esta regra. Se vazio, aplica-se a TODOS os clientes.")?></p>
</div>

<div class="form-group">
	<label><?=l7_t("Excepcoes — IPs excluidos desta regra (um por linha)")?></label>
	<textarea class="form-control" name="rule_except" rows="3"
		placeholder="<?=l7_t("Ex: 192.168.10.1 (director)")?>"
		style="font-family:monospace; max-width:400px;"><?=htmlspecialchars(implode("\n", $erule["except_ips"] ?? array()))?></textarea>
	<p class="help-block"><?=l7_t("IPs que NAO sao bloqueados por esta regra, mesmo estando no CIDR de origem.")?></p>
</div>

<div class="layer7-form-card__actions">
	<button type="submit" name="save_rule" class="btn btn-primary">
		<i class="fa fa-save"></i> <?=l7_t("Guardar regra")?>
	</button>
	<a href="layer7_blacklists.php" class="btn btn-default"><?=l7_t("Cancelar")?></a>
</div>
</form>
</div>
<?php endif; ?>
</div>

<!-- SECTION 3: Global Whitelist -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=l7_t("Whitelist Global")?></h3>
<div class="layer7-form-card">
<form method="post">
<div class="form-group">
	<label><?=l7_t("Dominios nunca bloqueados (um por linha)")?></label>
	<textarea class="form-control" name="whitelist" rows="5"
		placeholder="<?=l7_t("Ex: google.com\nyoutube.com")?>"
		style="font-family:monospace; max-width:500px;"><?=htmlspecialchars(implode("\n", $bl_config["whitelist"] ?? array()))?></textarea>
	<p class="help-block"><?=l7_t("Dominios nesta lista nunca sao bloqueados por NENHUMA regra, mesmo que estejam nas categorias.")?></p>
</div>
<div class="layer7-form-card__actions">
	<button type="submit" name="save_whitelist" class="btn btn-primary">
		<i class="fa fa-save"></i> <?=l7_t("Guardar whitelist")?>
	</button>
</div>
</form>
</div>
</div>

<!-- SECTION 4: Settings & State -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=l7_t("Definicoes e Estado")?></h3>
<div class="layer7-form-card">
<form method="post">
<div class="form-group">
	<label class="checkbox-inline">
		<input type="checkbox" name="auto_update" value="1"
			<?=(!empty($bl_config["auto_update"])) ? "checked" : ""?>>
		<?=l7_t("Actualizacao automatica")?>
	</label>
</div>
<div class="form-group">
	<label><?=l7_t("Intervalo (horas)")?></label>
	<input type="number" class="form-control" name="update_interval_hours"
		value="<?=(int)($bl_config["update_interval_hours"] ?? 24)?>"
		min="1" max="168" style="width:100px;">
</div>
<div class="layer7-form-card__actions">
	<button type="submit" name="save_settings" class="btn btn-primary">
		<i class="fa fa-save"></i> <?=l7_t("Guardar definicoes")?>
	</button>
</div>
</form>
</div>

<div style="margin-top:18px;">
<dl class="dl-horizontal layer7-summary">
	<dt><?=l7_t("Ultima actualizacao")?></dt>
	<dd><?=$last_update ? htmlspecialchars($last_update) : '<em>' . l7_t("Nunca") . '</em>'?></dd>
<?php if ($bl_stats): ?>
	<dt><?=l7_t("Regras activas")?></dt>
	<dd><?=(int)$bl_stats["rules_active"]?></dd>
	<dt><?=l7_t("Categorias carregadas")?></dt>
	<dd><?=(int)$bl_stats["categories_active"]?><?php if ($discovered): ?> / <?=count($discovered["categories"])?> <?=l7_t("disponiveis")?><?php endif; ?></dd>
	<dt><?=l7_t("Dominios carregados")?></dt>
	<dd><?=number_format((int)$bl_stats["domains_loaded"], 0, ',', '.')?></dd>
	<dt><?=l7_t("Lookups totais")?></dt>
	<dd><?=number_format((int)$bl_stats["lookups"], 0, ',', '.')?></dd>
	<dt><?=l7_t("Hits de blacklist")?></dt>
	<dd><?=number_format((int)$bl_stats["hits"], 0, ',', '.')?></dd>
<?php endif; ?>
</dl>

<?php if ($bl_stats && is_array($bl_stats["top_categories"]) && count($bl_stats["top_categories"]) > 0): ?>
<h4><?=l7_t("Top categorias bloqueadas")?></h4>
<table class="table table-condensed" style="max-width:400px;">
<thead><tr><th><?=l7_t("Categoria")?></th><th style="text-align:right;"><?=l7_t("Hits")?></th></tr></thead>
<tbody>
<?php foreach ($bl_stats["top_categories"] as $tc): ?>
<tr>
	<td><?=htmlspecialchars($tc["cat"] ?? "")?></td>
	<td style="text-align:right;"><?=number_format((int)($tc["hits"] ?? 0), 0, ',', '.')?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<div class="layer7-muted-note" style="margin-top:24px; font-size:11px;">
	<?=l7_t("Listas mantidas pela")?> <a href="https://dsi.ut-capitole.fr/blacklists/index_en.php" target="_blank">Universit&eacute; Toulouse Capitole</a>.
	<?=l7_t("Licenca")?> <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank">CC-BY-SA 4.0</a>.
</div>

</div>

</div><!-- layer7-content -->
</div><!-- panel-body -->
</div><!-- panel -->

<?php layer7_render_footer(); ?>

</div><!-- layer7-page -->

<script>
function pollDownloadLog() {
	var xhr = new XMLHttpRequest();
	xhr.open('GET', '/packages/layer7/layer7_bl_ajax.php?action=progress&_=' + Date.now(), true);
	xhr.onreadystatechange = function() {
		if (xhr.readyState == 4 && xhr.status == 200) {
			var ta = document.getElementById('download_log');
			ta.value = xhr.responseText;
			ta.scrollTop = ta.scrollHeight;
		}
	};
	xhr.send();
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

<?php if (isset($_POST["do_download"])): ?>
var _pollTimer = setInterval(function() { pollDownloadLog(); }, 2000);
setTimeout(function() { clearInterval(_pollTimer); }, 300000);
<?php endif; ?>
</script>

<?php include("foot.inc"); ?>
