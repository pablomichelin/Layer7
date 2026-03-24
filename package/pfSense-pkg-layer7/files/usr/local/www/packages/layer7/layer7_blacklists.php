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
	$savemsg = gettext("Download iniciado. Acompanhe o progresso abaixo.");
}

/* POST: Save categories */
if (isset($_POST["save_categories"])) {
	$cats = array();
	if ($discovered && is_array($discovered["categories"])) {
		foreach ($discovered["categories"] as $cat) {
			$key = "cat_" . $cat["id"];
			$val = $_POST[$key] ?? "---";
			if ($val === "deny") {
				$cats[] = $cat["id"];
			}
		}
	}
	$bl_config["categories"] = $cats;
	$bl_config["enabled"] = (count($cats) > 0);
	layer7_bl_config_save($bl_config);
	layer7_bl_apply();
	$savemsg = gettext("Categorias guardadas. Daemon recarregado.");
}

/* POST: Save exceptions */
if (isset($_POST["save_exceptions"])) {
	$wl_raw = trim($_POST["whitelist"] ?? "");
	$except_raw = trim($_POST["except_ips"] ?? "");
	$wl = array_values(array_filter(array_map("trim", explode("\n", $wl_raw))));
	$except = array_values(array_filter(array_map("trim", explode("\n", $except_raw))));
	$bl_config["whitelist"] = $wl;
	$bl_config["except_ips"] = $except;
	layer7_bl_config_save($bl_config);
	layer7_bl_pf_sync_except($except);
	layer7_bl_apply();
	$savemsg = gettext("Excepcoes guardadas. Tabela PF actualizada.");
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

	$savemsg = gettext("Definicoes guardadas.");
}

/* Reload config after any save */
$bl_config = layer7_bl_config_load();
$discovered = layer7_bl_discovered_load();
$bl_stats = layer7_bl_get_stats();
$last_update = layer7_bl_last_update();

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Blacklists"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");

layer7_render_styles();
?>

<div class="layer7-page">
<div class="panel panel-default">
<div class="panel-heading"><h2 class="panel-title"><?=gettext("Categorias Web (Blacklists)")?></h2></div>
<div class="panel-body">

<?php layer7_render_tabs("blacklists"); ?>

<div class="layer7-content">

<?php layer7_render_messages(); ?>

<!-- SECTION 1: URL & Download -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=gettext("URL e Download")?></h3>
<form method="post">
<div class="form-group">
	<label><?=gettext("URL da blacklist")?></label>
	<input type="text" class="form-control" name="source_url"
		value="<?=htmlspecialchars($bl_config["source_url"])?>"
		placeholder="http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz">
	<p class="help-block"><?=gettext("Endereco do arquivo blacklists.tar.gz (formato UT1 Toulouse).")?></p>
</div>
<button type="submit" name="do_download" class="btn btn-primary">
	<i class="fa fa-download"></i> <?=gettext("Download")?>
</button>
</form>

<div style="margin-top:14px;">
	<label><?=gettext("Log de download")?></label>
	<textarea id="download_log" class="form-control" rows="6" readonly
		style="font-family:monospace; font-size:12px; background:#f8f8f8;"><?=htmlspecialchars(layer7_bl_download_status())?></textarea>
	<button type="button" class="btn btn-default btn-xs" style="margin-top:6px;"
		onclick="pollDownloadLog();">
		<i class="fa fa-refresh"></i> <?=gettext("Actualizar log")?>
	</button>
</div>
</div>

<!-- SECTION 2: Categories -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=gettext("Categorias")?></h3>
<?php if ($discovered === null): ?>
	<div class="alert alert-warning">
		<i class="fa fa-info-circle"></i>
		<?=gettext("Faca o download da lista primeiro.")?>
	</div>
<?php else: ?>
<form method="post">
<div style="margin-bottom:10px;">
	<input type="text" id="cat_filter" class="form-control" style="max-width:300px;"
		placeholder="<?=gettext("Pesquisar categorias...")?>"
		onkeyup="filterCategories();">
</div>
<div class="table-responsive">
<table class="table table-striped table-hover" id="cat_table">
<thead>
<tr>
	<th><?=gettext("Categoria")?></th>
	<th style="text-align:right;"><?=gettext("Dominios")?></th>
	<th><?=gettext("Accao")?></th>
</tr>
</thead>
<tbody>
<?php
$active_cats = is_array($bl_config["categories"]) ? $bl_config["categories"] : array();
usort($discovered["categories"], function($a, $b) { return strcmp($a["id"], $b["id"]); });
foreach ($discovered["categories"] as $cat):
	$cat_id = $cat["id"];
	$count = isset($cat["domains_count"]) ? (int)$cat["domains_count"] : 0;
	$selected = in_array($cat_id, $active_cats) ? "deny" : "---";
	$warning = ($count > 1000000) ? ' <span class="text-warning" title="' . gettext("Impacto significativo em RAM") . '">&#9888;</span>' : '';
?>
<tr class="cat-row" data-cat="<?=htmlspecialchars($cat_id)?>">
	<td><strong><?=htmlspecialchars($cat_id)?></strong></td>
	<td style="text-align:right;"><?=number_format($count, 0, ',', '.')?><?=$warning?></td>
	<td>
		<select name="cat_<?=htmlspecialchars($cat_id)?>" class="form-control" style="width:100px;">
			<option value="---" <?=($selected === "---") ? "selected" : ""?>>---</option>
			<option value="deny" <?=($selected === "deny") ? "selected" : ""?>>deny</option>
		</select>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p class="text-muted"><small>&#9888; = <?=gettext("categoria com mais de 1M dominios — impacto significativo em RAM.")?></small></p>
<button type="submit" name="save_categories" class="btn btn-primary">
	<i class="fa fa-save"></i> <?=gettext("Guardar categorias")?>
</button>
</form>
<?php endif; ?>
</div>

<!-- SECTION 3: Exceptions -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=gettext("Excepcoes")?></h3>
<form method="post">
<div class="form-group">
	<label><?=gettext("Whitelist de dominios (nunca bloqueados pela blacklist)")?></label>
	<textarea class="form-control" name="whitelist" rows="5"
		placeholder="<?=gettext("Um dominio por linha")?>"
		style="font-family:monospace;"><?=htmlspecialchars(implode("\n", $bl_config["whitelist"] ?? array()))?></textarea>
	<p class="help-block"><?=gettext("Dominios nesta lista nunca sao bloqueados pelas categorias, mesmo que estejam nas listas.")?></p>
</div>
<div class="form-group">
	<label><?=gettext("IPs excepcionados (acedem a destinos bloqueados)")?></label>
	<textarea class="form-control" name="except_ips" rows="4"
		placeholder="<?=gettext("Um IP ou CIDR por linha")?>"
		style="font-family:monospace;"><?=htmlspecialchars(implode("\n", $bl_config["except_ips"] ?? array()))?></textarea>
	<p class="help-block"><?=gettext("IPs nesta lista podem aceder a destinos bloqueados pela blacklist (tabela PF layer7_bl_except).")?></p>
</div>
<button type="submit" name="save_exceptions" class="btn btn-primary">
	<i class="fa fa-save"></i> <?=gettext("Guardar excepcoes")?>
</button>
</form>
</div>

<!-- SECTION 4: Settings & State -->
<div class="layer7-section">
<h3 class="layer7-section-title"><?=gettext("Definicoes e Estado")?></h3>
<form method="post">
<div class="form-group">
	<label class="checkbox-inline">
		<input type="checkbox" name="auto_update" value="1"
			<?=(!empty($bl_config["auto_update"])) ? "checked" : ""?>>
		<?=gettext("Actualizacao automatica")?>
	</label>
</div>
<div class="form-group">
	<label><?=gettext("Intervalo (horas)")?></label>
	<input type="number" class="form-control" name="update_interval_hours"
		value="<?=(int)($bl_config["update_interval_hours"] ?? 24)?>"
		min="1" max="168" style="width:100px;">
</div>
<button type="submit" name="save_settings" class="btn btn-primary">
	<i class="fa fa-save"></i> <?=gettext("Guardar definicoes")?>
</button>
</form>

<div style="margin-top:18px;">
<dl class="dl-horizontal layer7-summary">
	<dt><?=gettext("Ultima actualizacao")?></dt>
	<dd><?=$last_update ? htmlspecialchars($last_update) : '<em>' . gettext("Nunca") . '</em>'?></dd>
<?php if ($bl_stats): ?>
	<dt><?=gettext("Categorias activas")?></dt>
	<dd><?=(int)$bl_stats["categories_active"]?><?php if ($discovered): ?> / <?=count($discovered["categories"])?> <?=gettext("disponiveis")?><?php endif; ?></dd>
	<dt><?=gettext("Dominios carregados")?></dt>
	<dd><?=number_format((int)$bl_stats["domains_loaded"], 0, ',', '.')?></dd>
	<dt><?=gettext("Lookups totais")?></dt>
	<dd><?=number_format((int)$bl_stats["lookups"], 0, ',', '.')?></dd>
	<dt><?=gettext("Hits de blacklist")?></dt>
	<dd><?=number_format((int)$bl_stats["hits"], 0, ',', '.')?></dd>
<?php endif; ?>
</dl>

<?php if ($bl_stats && is_array($bl_stats["top_categories"]) && count($bl_stats["top_categories"]) > 0): ?>
<h4><?=gettext("Top categorias bloqueadas")?></h4>
<table class="table table-condensed" style="max-width:400px;">
<thead><tr><th><?=gettext("Categoria")?></th><th style="text-align:right;"><?=gettext("Hits")?></th></tr></thead>
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
	<?=gettext("Listas mantidas pela")?> <a href="https://dsi.ut-capitole.fr/blacklists/index_en.php" target="_blank">Universit&eacute; Toulouse Capitole</a>.
	<?=gettext("Licenca")?> <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank">CC-BY-SA 4.0</a>.
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

function filterCategories() {
	var filter = document.getElementById('cat_filter').value.toLowerCase();
	var rows = document.querySelectorAll('#cat_table tbody .cat-row');
	for (var i = 0; i < rows.length; i++) {
		var cat = rows[i].getAttribute('data-cat');
		rows[i].style.display = cat.indexOf(filter) !== -1 ? '' : 'none';
	}
}

<?php if (isset($_POST["do_download"])): ?>
var _pollTimer = setInterval(function() { pollDownloadLog(); }, 2000);
setTimeout(function() { clearInterval(_pollTimer); }, 300000);
<?php endif; ?>
</script>

<?php include("foot.inc"); ?>
