<?php
##|+PRIV
##|*IDENT=page-services-layer7-devices
##|*NAME=Services: Layer 7 (inventario de dispositivos)
##|*DESCR=Allow access to Layer 7 device inventory.
##|*MATCH=layer7_devices.php*
##|-PRIV

/*
 * Layer7 — inventario de dispositivos (Caminho A / A1).
 *
 * Pagina READ-ONLY (excepto alias do operador). Combina DHCP leases
 * (`system_get_dhcpleases()`) com a tabela ARP (`arp -an`), enriquece com
 * vendor (OUI) e alias persistente por MAC. NAO altera enforcement.
 * Base para A2 (politicas por dispositivo).
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");
require_once("classes/Form.class.php");

if ($_POST["save_aliases"] ?? false) {
	$aliases = (isset($_POST["alias"]) && is_array($_POST["alias"])) ? $_POST["alias"] : array();
	$saved = 0;
	$bad = 0;
	foreach ($aliases as $mac => $alias) {
		$mac = strtolower(trim((string)$mac));
		if (!layer7_device_mac_valid($mac)) {
			$bad++;
			continue;
		}
		if (layer7_device_alias_save($mac, (string)$alias)) {
			$saved++;
		}
	}
	if ($bad > 0) {
		$input_errors[] = sprintf(l7_t("%d entradas de alias com MAC invalido foram ignoradas."), $bad);
	}
	$savemsg = sprintf(l7_t("Aliases gravados (%d dispositivos)."), $saved);
}

if ($_POST["assign_to_group"] ?? false) {
	$sel_macs = layer7_normalize_macs($_POST["assign_macs"] ?? array());
	$gid = trim($_POST["assign_group"] ?? "");
	$data = layer7_load_or_default();
	$groups = (isset($data["layer7"]["groups"]) && is_array($data["layer7"]["groups"]))
		? $data["layer7"]["groups"] : array();
	if (empty($sel_macs)) {
		$input_errors[] = l7_t("Selecione pelo menos um dispositivo (com MAC).");
	} elseif ($gid === "") {
		$input_errors[] = l7_t("Selecione um grupo de destino.");
	} else {
		$found = false;
		foreach ($groups as $i => $g) {
			if ((string)($g["id"] ?? "") !== $gid) {
				continue;
			}
			$found = true;
			$cur = (isset($g["device_macs"]) && is_array($g["device_macs"])) ? $g["device_macs"] : array();
			$merged = layer7_normalize_macs(array_merge($cur, $sel_macs));
			$data["layer7"]["groups"][$i]["device_macs"] = $merged;
			$data["layer7"]["groups"][$i]["device_ips"] = layer7_resolve_macs_to_ips($merged);
			$n_ips = count($data["layer7"]["groups"][$i]["device_ips"]);
			break;
		}
		if (!$found) {
			$input_errors[] = l7_t("Grupo de destino inexistente.");
		} elseif (layer7_save_json($data)) {
			layer7_pf_config_resync();
			$savemsg = sprintf(l7_t("%d dispositivos atribuidos ao grupo '%s' (%d IPs resolvidos)."), count($sel_macs), $gid, $n_ips);
		}
	}
}

$l7_groups = layer7_load_groups();

$devices = layer7_device_inventory();
$n_total = count($devices);
$n_online = 0;
$n_mac = 0;
foreach ($devices as $d) {
	if (stripos((string)($d["online"] ?? ""), "online") !== false || stripos((string)($d["online"] ?? ""), "active") !== false) {
		$n_online++;
	}
	if (($d["mac"] ?? "") !== "") {
		$n_mac++;
	}
}

$l7_dev_q = isset($_GET["q"]) ? trim((string)$_GET["q"]) : "";
$l7_dev_q = preg_replace('/[\r\n\t]+/', " ", $l7_dev_q);
if (!is_string($l7_dev_q)) {
	$l7_dev_q = "";
}
if (function_exists("mb_substr")) {
	$l7_dev_q = mb_substr($l7_dev_q, 0, 128);
} else {
	$l7_dev_q = substr($l7_dev_q, 0, 128);
}

$l7_dev_online = (isset($_GET["online"]) && (string)$_GET["online"] === "1");
$l7_dev_page = 1;
if (isset($_GET["page"]) && ctype_digit((string)$_GET["page"])) {
	$l7_dev_page = max(1, (int)$_GET["page"]);
}

$l7_dev_mode_in = isset($_GET["mode"]) ? strtolower(trim((string)$_GET["mode"])) : "";
$l7_dev_batch = ($l7_dev_mode_in === "batch");

$l7_dev_edit_mac = "";
if (isset($_GET["edit"])) {
	$cand = strtolower(trim((string)$_GET["edit"]));
	if (layer7_device_mac_valid($cand)) {
		$l7_dev_edit_mac = $cand;
	}
}

$l7_device_mode = "list";
if ($l7_dev_edit_mac !== "") {
	$l7_device_mode = "edit";
} elseif ($l7_dev_batch) {
	$l7_device_mode = "batch";
}

$l7_dev_filtered = array();
foreach ($devices as $d) {
	$online = (string)($d["online"] ?? "");
	$is_on = (stripos($online, "online") !== false || stripos($online, "active") !== false);
	if ($l7_dev_online && !$is_on) {
		continue;
	}
	if ($l7_dev_q !== "") {
		$hay = strtolower(implode(" ", array(
			(string)($d["ip"] ?? ""),
			(string)($d["mac"] ?? ""),
			(string)($d["hostname"] ?? ""),
			(string)($d["descr"] ?? ""),
			(string)($d["vendor"] ?? ""),
			(string)($d["iface"] ?? ""),
			(string)($d["alias"] ?? ""),
			(string)($d["source"] ?? "")
		)));
		if (strpos($hay, strtolower($l7_dev_q)) === false) {
			continue;
		}
	}
	$l7_dev_filtered[] = $d;
}

$l7_dev_shown = count($l7_dev_filtered);
$l7_dev_per_page = 50;
$l7_dev_pages = max(1, (int)ceil($l7_dev_shown / $l7_dev_per_page));
if ($l7_dev_page > $l7_dev_pages) {
	$l7_dev_page = $l7_dev_pages;
}
$l7_dev_offset = ($l7_dev_page - 1) * $l7_dev_per_page;
$l7_dev_slice = array_slice($l7_dev_filtered, $l7_dev_offset, $l7_dev_per_page);
$l7_dev_from = ($l7_dev_shown === 0) ? 0 : ($l7_dev_offset + 1);
$l7_dev_to = min($l7_dev_offset + $l7_dev_per_page, $l7_dev_shown);

$l7_edit_alias = "";
$l7_edit_ip = "";
$l7_edit_host = "";
if ($l7_device_mode === "edit") {
	$stored = layer7_device_aliases_load();
	if (isset($stored[$l7_dev_edit_mac])) {
		$l7_edit_alias = (string)$stored[$l7_dev_edit_mac];
	}
	foreach ($devices as $d) {
		if (strtolower((string)($d["mac"] ?? "")) !== $l7_dev_edit_mac) {
			continue;
		}
		$l7_edit_alias = (string)($d["alias"] ?? $l7_edit_alias);
		$l7_edit_ip = (string)($d["ip"] ?? "");
		$host = (string)($d["hostname"] ?? "");
		if ($host === "") {
			$host = (string)($d["descr"] ?? "");
		}
		$l7_edit_host = $host;
		break;
	}
}

$l7_dev_posted_aliases = array();
if (isset($_POST["alias"]) && is_array($_POST["alias"])) {
	foreach ($_POST["alias"] as $pmac => $pval) {
		$l7_dev_posted_aliases[strtolower(trim((string)$pmac))] = (string)$pval;
	}
}
if ($l7_device_mode === "edit" && isset($l7_dev_posted_aliases[$l7_dev_edit_mac])) {
	$l7_edit_alias = $l7_dev_posted_aliases[$l7_dev_edit_mac];
}

$l7_dev_posted_macs = array();
if (isset($_POST["assign_macs"]) && is_array($_POST["assign_macs"])) {
	foreach ($_POST["assign_macs"] as $pm) {
		$pm = strtolower(trim((string)$pm));
		if ($pm !== "") {
			$l7_dev_posted_macs[] = $pm;
		}
	}
}
$l7_dev_posted_group = isset($_POST["assign_group"]) ? trim((string)$_POST["assign_group"]) : "";

if (!function_exists("layer7_devices_view_href")) {
	function layer7_devices_view_href($q, $online, $page = 0, $mode = "list", $edit_mac = "", $fragment = "")
	{
		$parts = array();
		if ($edit_mac !== "") {
			$parts["edit"] = $edit_mac;
		}
		if ($mode === "batch") {
			$parts["mode"] = "batch";
		}
		if ($q !== "") {
			$parts["q"] = $q;
		}
		if ($online) {
			$parts["online"] = "1";
		}
		if ((int)$page > 1) {
			$parts["page"] = (int)$page;
		}
		$url = "layer7_devices.php";
		if (!empty($parts)) {
			$url .= "?" . http_build_query($parts);
		}
		if ($fragment !== "") {
			$url .= "#" . $fragment;
		}
		return $url;
	}
}

$l7_dev_origin_batch = ($l7_dev_mode_in === "batch");
$l7_dev_origin_mode = $l7_dev_origin_batch ? "batch" : "list";
$l7_dev_list_href = layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, "list");
$l7_dev_batch_href = layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, "batch");
$l7_dev_voltar_href = $l7_dev_origin_batch ? $l7_dev_batch_href : $l7_dev_list_href;
$l7_dev_refresh_href = ($l7_device_mode === "batch")
	? $l7_dev_batch_href
	: (($l7_device_mode === "edit")
		? layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, $l7_dev_origin_mode, $l7_dev_edit_mac)
		: $l7_dev_list_href);
$l7_dev_edit_action = layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, $l7_dev_origin_mode, $l7_dev_edit_mac);
$l7_dev_batch_action = layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, "batch", "", "l7-devices");
$l7_dev_clear_list_href = "layer7_devices.php";
$l7_dev_clear_batch_href = "layer7_devices.php?mode=batch";

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Dispositivos"));
include("head.inc");

layer7_render_tabs("devices");
layer7_render_messages();
?>
<div class="alert alert-info">
	<?= htmlspecialchars(l7_t("Dispositivos vistos na rede, combinando DHCP leases e a tabela ARP. Apenas leitura (so o alias e editavel). Base para politicas por dispositivo. So sao visiveis dispositivos adjacentes ao firewall (mesmo segmento L2).")); ?>
</div>
<?php if ($l7_device_mode === "edit") { ?>
<div id="l7-edit-alias">
<?php
	$edit_form = new Form(false);
	$edit_form->setAction($l7_dev_edit_action);
	$sec_edit = new Form_Section(l7_t("Editar alias"));
	$sec_edit->addInput(new Form_StaticText(
		l7_t("MAC"),
		'<code>' . htmlspecialchars($l7_dev_edit_mac) . '</code>'
	));
	$sec_edit->addInput(new Form_StaticText(
		l7_t("IP"),
		($l7_edit_ip !== "")
			? '<code>' . htmlspecialchars($l7_edit_ip) . '</code>'
			: '<span class="text-muted">-</span>'
	));
	$sec_edit->addInput(new Form_StaticText(
		l7_t("Hostname"),
		($l7_edit_host !== "")
			? htmlspecialchars($l7_edit_host)
			: '<span class="text-muted">-</span>'
	));
	$alias_input = new Form_Input(
		"alias[" . $l7_dev_edit_mac . "]",
		l7_t("Alias"),
		"text",
		$l7_edit_alias
	);
	$alias_input->setAttribute("maxlength", "64");
	$alias_input->setAttribute("id", "l7-alias-edit");
	$alias_input->setAttribute("placeholder", l7_t("nome amigavel"));
	$alias_input->setHelp(l7_t("O fabricante deriva do prefixo OUI do MAC quando existe uma base OUI no sistema; caso contrario fica vazio. Aliases sao guardados em layer7.json (device_aliases) e nao afectam o enforcement nesta fase."));
	$sec_edit->addInput($alias_input);
	$edit_form->add($sec_edit);
	$l7_edit_save = new Form_Button("save_aliases", l7_t("Gravar aliases"), null, "fa fa-save");
	$l7_edit_save->addClass("btn-primary");
	$edit_form->addGlobal($l7_edit_save);
	print($edit_form);
?>
<p>
	<a class="btn btn-default" href="<?= htmlspecialchars($l7_dev_voltar_href); ?>"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
	<a class="btn btn-default" href="<?= htmlspecialchars($l7_dev_batch_href); ?>"><?= htmlspecialchars(l7_t("Editar e seleccionar em lote")); ?></a>
</p>
</div>
<?php } ?>
<?php if ($l7_device_mode === "batch") { ?>
<?php
	$form = new Form(false);
	$sec_resumo = new Form_Section(l7_t("Resumo"));
	$sec_resumo->addInput(new Form_StaticText(l7_t("dispositivos"), (string)(int)$n_total));
	$sec_resumo->addInput(new Form_StaticText(l7_t("activos/online"), (string)(int)$n_online));
	$sec_resumo->addInput(new Form_StaticText(l7_t("com MAC"), (string)(int)$n_mac));
	$sec_resumo->addInput(new Form_StaticText(
		l7_t("Actualizar"),
		'<a class="btn btn-default btn-xs" href="' . htmlspecialchars($l7_dev_refresh_href) . '"><i class="fa fa-refresh"></i> ' .
		htmlspecialchars(l7_t("Actualizar")) . '</a>'
	));
	$form->add($sec_resumo);
	print($form);
?>
<div class="alert alert-warning">
	<?= htmlspecialchars(sprintf(l7_t("Este modo mostra todos os %d dispositivos correspondentes ao filtro, sem paginacao. Gravar aliases e atribuir a um grupo sao accoes independentes sobre esse conjunto."), (int)$l7_dev_shown)); ?>
</div>
<div class="panel panel-default" id="l7-devices-filter">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Filtrar")); ?></h2>
	</div>
	<div class="panel-body">
		<form method="get" action="layer7_devices.php" class="form-inline">
			<input type="hidden" name="mode" value="batch" />
			<div class="form-group">
				<label class="sr-only" for="l7-dev-q"><?= htmlspecialchars(l7_t("Pesquisar")); ?></label>
				<input id="l7-dev-q" type="text" name="q" class="form-control" value="<?= htmlspecialchars($l7_dev_q); ?>" placeholder="<?= htmlspecialchars(l7_t("Pesquisar dispositivo...")); ?>" />
			</div>
			<div class="form-group">
				<label for="l7-dev-online">
					<input id="l7-dev-online" type="checkbox" name="online" value="1"<?= $l7_dev_online ? ' checked="checked"' : ''; ?> />
					<?= htmlspecialchars(l7_t("So ligados")); ?>
				</label>
			</div>
			<?php if ($l7_dev_page > 1) { ?>
			<input type="hidden" name="page" value="<?= (int)$l7_dev_page; ?>" />
			<?php } ?>
			<button type="submit" class="btn btn-primary"><?= htmlspecialchars(l7_t("Filtrar")); ?></button>
			<a class="btn btn-default" href="<?= htmlspecialchars($l7_dev_clear_batch_href); ?>"><?= htmlspecialchars(l7_t("Limpar filtros")); ?></a>
			<a class="btn btn-default" href="<?= htmlspecialchars($l7_dev_list_href); ?>"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
		</form>
	</div>
</div>
<form id="l7-form-aliases" method="post" action="<?= htmlspecialchars($l7_dev_batch_action); ?>">
	<div class="panel panel-default" id="l7-devices">
		<div class="panel-heading">
			<h2 class="panel-title"><?= htmlspecialchars(l7_t("Editar e seleccionar em lote")); ?></h2>
		</div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?= htmlspecialchars(l7_t("IP")); ?></th>
							<th><?= htmlspecialchars(l7_t("MAC")); ?></th>
							<th><?= htmlspecialchars(l7_t("Hostname")); ?></th>
							<th><?= htmlspecialchars(l7_t("Fabricante")); ?></th>
							<th><?= htmlspecialchars(l7_t("Interface")); ?></th>
							<th><?= htmlspecialchars(l7_t("Estado")); ?></th>
							<th><?= htmlspecialchars(l7_t("Fonte")); ?></th>
							<th><?= htmlspecialchars(l7_t("Alias")); ?></th>
							<th><?= htmlspecialchars(l7_t("Acao")); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($devices)) { ?>
						<tr><td colspan="9" class="text-muted"><?= htmlspecialchars(l7_t("Nenhum dispositivo observado (sem leases DHCP nem entradas ARP).")); ?></td></tr>
					<?php } elseif (empty($l7_dev_filtered)) { ?>
						<tr><td colspan="9" class="text-muted"><?= htmlspecialchars(l7_t("Nenhum dispositivo corresponde ao filtro.")); ?></td></tr>
					<?php } else {
						foreach ($l7_dev_filtered as $d) {
							$mac = (string)($d["mac"] ?? "");
							$online = (string)($d["online"] ?? "");
							$is_on = (stripos($online, "online") !== false || stripos($online, "active") !== false);
							$alias_txt = (string)($d["alias"] ?? "");
							$host_txt = (string)($d["hostname"] ?? "");
							if ($host_txt === "") {
								$host_txt = (string)($d["descr"] ?? "");
							}
							$mac_id = preg_replace('/[^0-9a-f]/', '', strtolower($mac));
							if ($mac !== "" && isset($l7_dev_posted_aliases[$mac])) {
								$alias_txt = $l7_dev_posted_aliases[$mac];
							}
							$edit_href = ($mac !== "")
								? layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, "batch", $mac)
								: "";
					?>
						<tr>
							<td><code><?= htmlspecialchars($d["ip"] ?? ""); ?></code></td>
							<td><code><?= htmlspecialchars($mac); ?></code></td>
							<td><?= htmlspecialchars($host_txt !== "" ? $host_txt : "-"); ?></td>
							<td><?= htmlspecialchars(($d["vendor"] ?? "") !== "" ? $d["vendor"] : "-"); ?></td>
							<td><?= htmlspecialchars(($d["iface"] ?? "") !== "" ? $d["iface"] : "-"); ?></td>
							<td><?php if ($is_on) { ?><span class="text-success"><i class="fa fa-circle"></i> <?= htmlspecialchars($online !== "" ? $online : l7_t("activo")); ?></span><?php } else { ?><span class="text-muted"><?= htmlspecialchars($online !== "" ? $online : "-"); ?></span><?php } ?></td>
							<td><span class="label label-default"><?= htmlspecialchars($d["source"] ?? ""); ?></span></td>
							<td>
								<?php if ($mac !== "") { ?>
									<label class="sr-only" for="l7-alias-<?= htmlspecialchars($mac_id); ?>"><?= htmlspecialchars(l7_t("Alias")); ?></label>
									<input id="l7-alias-<?= htmlspecialchars($mac_id); ?>" type="text" name="alias[<?= htmlspecialchars($mac); ?>]" value="<?= htmlspecialchars($alias_txt); ?>" class="form-control input-sm" maxlength="64" placeholder="<?= htmlspecialchars(l7_t("nome amigavel")); ?>" />
								<?php } else { ?>
									<span class="text-muted">-</span>
								<?php } ?>
							</td>
							<td>
								<?php if ($edit_href !== "") { ?>
									<a class="btn btn-default btn-xs" href="<?= htmlspecialchars($edit_href); ?>"><?= htmlspecialchars(l7_t("Editar alias")); ?></a>
								<?php } else { ?>
									<span class="text-muted">-</span>
								<?php } ?>
							</td>
						</tr>
					<?php } } ?>
					</tbody>
				</table>
			</div>
			<button type="submit" name="save_aliases" value="1" class="btn btn-primary">
				<i class="fa fa-save"></i> <?= htmlspecialchars(l7_t("Gravar aliases")); ?>
			</button>
		</div>
	</div>
</form>
<form id="l7-form-assign" method="post" action="<?= htmlspecialchars($l7_dev_batch_action); ?>">
	<div class="panel panel-default" id="l7-assign">
		<div class="panel-heading">
			<h2 class="panel-title"><?= htmlspecialchars(l7_t("Atribuir a grupo")); ?></h2>
		</div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th>
								<label class="sr-only" for="l7-dev-select-all"><?= htmlspecialchars(l7_t("Selecionar todos")); ?></label>
								<input id="l7-dev-select-all" type="checkbox" onclick="var c=document.querySelectorAll('input.l7-dev-chk'); for(var i=0;i&lt;c.length;i++){c[i].checked=this.checked;}" title="<?= l7_t('Selecionar todos'); ?>" />
							</th>
							<th><?= htmlspecialchars(l7_t("IP")); ?></th>
							<th><?= htmlspecialchars(l7_t("MAC")); ?></th>
							<th><?= htmlspecialchars(l7_t("Hostname")); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($devices)) { ?>
						<tr><td colspan="4" class="text-muted"><?= htmlspecialchars(l7_t("Nenhum dispositivo observado (sem leases DHCP nem entradas ARP).")); ?></td></tr>
					<?php } elseif (empty($l7_dev_filtered)) { ?>
						<tr><td colspan="4" class="text-muted"><?= htmlspecialchars(l7_t("Nenhum dispositivo corresponde ao filtro.")); ?></td></tr>
					<?php } else {
						foreach ($l7_dev_filtered as $d) {
							$mac = (string)($d["mac"] ?? "");
							$host_txt = (string)($d["hostname"] ?? "");
							if ($host_txt === "") {
								$host_txt = (string)($d["descr"] ?? "");
							}
							$mac_id = preg_replace('/[^0-9a-f]/', '', strtolower($mac));
							$mac_checked = ($mac !== "" && in_array($mac, $l7_dev_posted_macs, true));
					?>
						<tr>
							<td>
								<?php if ($mac !== "") { ?>
									<label class="sr-only" for="l7-dev-chk-<?= htmlspecialchars($mac_id); ?>"><?= htmlspecialchars(sprintf(l7_t("Seleccionar dispositivo %s"), $mac)); ?></label>
									<input id="l7-dev-chk-<?= htmlspecialchars($mac_id); ?>" type="checkbox" class="l7-dev-chk" name="assign_macs[]" value="<?= htmlspecialchars($mac); ?>"<?= $mac_checked ? ' checked="checked"' : ''; ?> />
								<?php } ?>
							</td>
							<td><code><?= htmlspecialchars($d["ip"] ?? ""); ?></code></td>
							<td><code><?= htmlspecialchars($mac); ?></code></td>
							<td><?= htmlspecialchars($host_txt !== "" ? $host_txt : "-"); ?></td>
						</tr>
					<?php } } ?>
					</tbody>
				</table>
			</div>
			<div class="form-inline">
				<label for="l7-assign-group"><?= htmlspecialchars(l7_t("Atribuir selecionados ao grupo:")); ?></label>
				<select id="l7-assign-group" name="assign_group" class="form-control">
					<option value=""><?= htmlspecialchars(l7_t("-- escolher grupo --")); ?></option>
					<?php foreach ($l7_groups as $g) {
						$gid = (string)($g["id"] ?? "");
						if ($gid === "") { continue; }
						$gname = (string)($g["name"] ?? $gid);
					?>
					<option value="<?= htmlspecialchars($gid); ?>"<?= ($gid === $l7_dev_posted_group) ? ' selected="selected"' : ''; ?>><?= htmlspecialchars($gid . ($gname !== $gid ? " - " . $gname : "")); ?></option>
					<?php } ?>
				</select>
				<button type="submit" name="assign_to_group" value="1" class="btn btn-success">
					<i class="fa fa-plus"></i> <?= htmlspecialchars(l7_t("Atribuir a grupo")); ?>
				</button>
			</div>
			<?php if (empty($l7_groups)) { ?>
				<p class="text-muted"><?= htmlspecialchars(l7_t("(crie um grupo primeiro na aba Politicas > Grupos)")); ?></p>
			<?php } ?>
		</div>
	</div>
</form>
<p class="text-muted">
	<?= htmlspecialchars(l7_t("O fabricante deriva do prefixo OUI do MAC quando existe uma base OUI no sistema; caso contrario fica vazio. Aliases sao guardados em layer7.json (device_aliases) e nao afectam o enforcement nesta fase.")); ?>
</p>
<?php } ?>
<?php if ($l7_device_mode === "list") { ?>
<?php
	$form = new Form(false);
	$sec_resumo = new Form_Section(l7_t("Resumo"));
	$sec_resumo->addInput(new Form_StaticText(l7_t("dispositivos"), (string)(int)$n_total));
	$sec_resumo->addInput(new Form_StaticText(l7_t("activos/online"), (string)(int)$n_online));
	$sec_resumo->addInput(new Form_StaticText(l7_t("com MAC"), (string)(int)$n_mac));
	$sec_resumo->addInput(new Form_StaticText(
		l7_t("Actualizar"),
		'<a class="btn btn-default btn-xs" href="' . htmlspecialchars($l7_dev_refresh_href) . '"><i class="fa fa-refresh"></i> ' .
		htmlspecialchars(l7_t("Actualizar")) . '</a>'
	));
	$form->add($sec_resumo);
	print($form);
?>
<div class="panel panel-default" id="l7-devices-filter">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Filtrar")); ?></h2>
	</div>
	<div class="panel-body">
		<form method="get" action="layer7_devices.php" class="form-inline">
			<div class="form-group">
				<label class="sr-only" for="l7-dev-q"><?= htmlspecialchars(l7_t("Pesquisar")); ?></label>
				<input id="l7-dev-q" type="text" name="q" class="form-control" value="<?= htmlspecialchars($l7_dev_q); ?>" placeholder="<?= htmlspecialchars(l7_t("Pesquisar dispositivo...")); ?>" />
			</div>
			<div class="form-group">
				<label for="l7-dev-online">
					<input id="l7-dev-online" type="checkbox" name="online" value="1"<?= $l7_dev_online ? ' checked="checked"' : ''; ?> />
					<?= htmlspecialchars(l7_t("So ligados")); ?>
				</label>
			</div>
			<button type="submit" class="btn btn-primary"><?= htmlspecialchars(l7_t("Filtrar")); ?></button>
			<a class="btn btn-default" href="<?= htmlspecialchars($l7_dev_clear_list_href); ?>"><?= htmlspecialchars(l7_t("Limpar filtros")); ?></a>
		</form>
	</div>
</div>
<div class="panel panel-default" id="l7-devices">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Dispositivos")); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block"><?= htmlspecialchars(l7_t("A consulta e paginada. A edicao em lote e a atribuicao a grupos usam o conjunto completo do filtro, sem limite de 50.")); ?></p>
		<p>
			<a class="btn btn-primary" href="<?= htmlspecialchars($l7_dev_batch_href); ?>"><?= htmlspecialchars(l7_t("Editar e seleccionar em lote")); ?></a>
		</p>
		<p class="text-muted">
			<?= htmlspecialchars((string)(int)$l7_dev_from); ?>–<?= htmlspecialchars((string)(int)$l7_dev_to); ?>
			/
			<?= htmlspecialchars((string)(int)$l7_dev_shown); ?>
			<?= htmlspecialchars(l7_t("dispositivos")); ?>
			<?php if ($l7_dev_q !== "" || $l7_dev_online) { ?>
				(<?= htmlspecialchars((string)(int)$n_total); ?> <?= htmlspecialchars(l7_t("Dispositivos observados")); ?>)
			<?php } ?>
		</p>
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?= htmlspecialchars(l7_t("IP")); ?></th>
						<th><?= htmlspecialchars(l7_t("MAC")); ?></th>
						<th><?= htmlspecialchars(l7_t("Hostname")); ?></th>
						<th><?= htmlspecialchars(l7_t("Fabricante")); ?></th>
						<th><?= htmlspecialchars(l7_t("Interface")); ?></th>
						<th><?= htmlspecialchars(l7_t("Estado")); ?></th>
						<th><?= htmlspecialchars(l7_t("Fonte")); ?></th>
						<th><?= htmlspecialchars(l7_t("Alias")); ?></th>
						<th><?= htmlspecialchars(l7_t("Acao")); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($devices)) { ?>
					<tr><td colspan="9" class="text-muted"><?= htmlspecialchars(l7_t("Nenhum dispositivo observado (sem leases DHCP nem entradas ARP).")); ?></td></tr>
				<?php } elseif (empty($l7_dev_slice)) { ?>
					<tr><td colspan="9" class="text-muted"><?= htmlspecialchars(l7_t("Nenhum dispositivo corresponde ao filtro.")); ?></td></tr>
				<?php } else {
					foreach ($l7_dev_slice as $d) {
						$mac = (string)($d["mac"] ?? "");
						$online = (string)($d["online"] ?? "");
						$is_on = (stripos($online, "online") !== false || stripos($online, "active") !== false);
						$alias_txt = (string)($d["alias"] ?? "");
						$host_txt = (string)($d["hostname"] ?? "");
						if ($host_txt === "") {
							$host_txt = (string)($d["descr"] ?? "");
						}
						$edit_href = ($mac !== "")
							? layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page, "list", $mac)
							: "";
				?>
					<tr>
						<td><code><?= htmlspecialchars($d["ip"] ?? ""); ?></code></td>
						<td><code><?= htmlspecialchars($mac); ?></code></td>
						<td><?= htmlspecialchars($host_txt !== "" ? $host_txt : "-"); ?></td>
						<td><?= htmlspecialchars(($d["vendor"] ?? "") !== "" ? $d["vendor"] : "-"); ?></td>
						<td><?= htmlspecialchars(($d["iface"] ?? "") !== "" ? $d["iface"] : "-"); ?></td>
						<td><?php if ($is_on) { ?><span class="text-success"><i class="fa fa-circle"></i> <?= htmlspecialchars($online !== "" ? $online : l7_t("activo")); ?></span><?php } else { ?><span class="text-muted"><?= htmlspecialchars($online !== "" ? $online : "-"); ?></span><?php } ?></td>
						<td><span class="label label-default"><?= htmlspecialchars($d["source"] ?? ""); ?></span></td>
						<td><?= $alias_txt !== "" ? htmlspecialchars($alias_txt) : '<span class="text-muted">-</span>'; ?></td>
						<td>
							<?php if ($edit_href !== "") { ?>
								<a class="btn btn-default btn-xs" href="<?= htmlspecialchars($edit_href); ?>"><?= htmlspecialchars(l7_t("Editar alias")); ?></a>
							<?php } else { ?>
								<span class="text-muted">-</span>
							<?php } ?>
						</td>
					</tr>
				<?php } } ?>
				</tbody>
			</table>
		</div>
		<?php if ($l7_dev_pages > 1) { ?>
		<p>
			<?= htmlspecialchars(sprintf(l7_t("Pagina %d de %d"), $l7_dev_page, $l7_dev_pages)); ?>
			<?php if ($l7_dev_page > 1) { ?>
				<a class="btn btn-default btn-xs" href="<?= htmlspecialchars(layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page - 1, "list")); ?>"><?= htmlspecialchars(l7_t("Anterior")); ?></a>
			<?php } ?>
			<?php if ($l7_dev_page < $l7_dev_pages) { ?>
				<a class="btn btn-default btn-xs" href="<?= htmlspecialchars(layer7_devices_view_href($l7_dev_q, $l7_dev_online, $l7_dev_page + 1, "list")); ?>"><?= htmlspecialchars(l7_t("Seguinte")); ?></a>
			<?php } ?>
		</p>
		<?php } ?>
	</div>
</div>
<p class="text-muted">
	<?= htmlspecialchars(l7_t("O fabricante deriva do prefixo OUI do MAC quando existe uma base OUI no sistema; caso contrario fica vazio. Aliases sao guardados em layer7.json (device_aliases) e nao afectam o enforcement nesta fase.")); ?>
</p>
<?php } ?>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php include("foot.inc"); ?>
