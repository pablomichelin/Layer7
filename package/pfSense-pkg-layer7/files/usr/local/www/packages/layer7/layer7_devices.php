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
			layer7_signal_reload();
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

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Dispositivos"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - inventario de dispositivos"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("devices"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead">
				<?= l7_t("Dispositivos vistos na rede, combinando DHCP leases e a tabela ARP. Apenas leitura (so o alias e editavel). Base para politicas por dispositivo. So sao visiveis dispositivos adjacentes ao firewall (mesmo segmento L2)."); ?>
			</p>

			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header">
					<?= l7_t("Resumo"); ?>
				</div>
				<div class="layer7-admin-block__body">
					<p>
						<span class="badge"><?= (int)$n_total; ?></span> <?= l7_t("dispositivos"); ?>
						&nbsp; <span class="badge"><?= (int)$n_online; ?></span> <?= l7_t("activos/online"); ?>
						&nbsp; <span class="badge"><?= (int)$n_mac; ?></span> <?= l7_t("com MAC"); ?>
						&nbsp; <a class="btn btn-default btn-xs" href="layer7_devices.php"><i class="fa fa-refresh"></i> <?= l7_t("Actualizar"); ?></a>
					</p>
				</div>
			</div>

			<form method="post" action="layer7_devices.php" class="form-horizontal">
				<div class="table-responsive">
					<table class="table table-striped table-hover table-condensed">
						<thead>
							<tr>
								<th><input type="checkbox" onclick="var c=document.querySelectorAll('input.l7-dev-chk'); for(var i=0;i&lt;c.length;i++){c[i].checked=this.checked;}" title="<?= l7_t('Selecionar todos'); ?>" /></th>
								<th><?= l7_t("IP"); ?></th>
								<th><?= l7_t("MAC"); ?></th>
								<th><?= l7_t("Hostname"); ?></th>
								<th><?= l7_t("Fabricante"); ?></th>
								<th><?= l7_t("Interface"); ?></th>
								<th><?= l7_t("Estado"); ?></th>
								<th><?= l7_t("Fonte"); ?></th>
								<th><?= l7_t("Alias"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if (empty($devices)) { ?>
							<tr><td colspan="9" class="text-muted"><?= l7_t("Nenhum dispositivo observado (sem leases DHCP nem entradas ARP)."); ?></td></tr>
						<?php } else {
							foreach ($devices as $d) {
								$mac = (string)($d["mac"] ?? "");
								$online = (string)($d["online"] ?? "");
								$is_on = (stripos($online, "online") !== false || stripos($online, "active") !== false);
						?>
							<tr>
								<td><?php if ($mac !== "") { ?><input type="checkbox" class="l7-dev-chk" name="assign_macs[]" value="<?= htmlspecialchars($mac); ?>" /><?php } ?></td>
								<td><code><?= htmlspecialchars($d["ip"] ?? ""); ?></code></td>
								<td><code><?= htmlspecialchars($mac); ?></code></td>
								<td><?= htmlspecialchars(($d["hostname"] ?? "") !== "" ? $d["hostname"] : (($d["descr"] ?? "") !== "" ? $d["descr"] : "-")); ?></td>
								<td><?= htmlspecialchars(($d["vendor"] ?? "") !== "" ? $d["vendor"] : "-"); ?></td>
								<td><?= htmlspecialchars(($d["iface"] ?? "") !== "" ? $d["iface"] : "-"); ?></td>
								<td><?php if ($is_on) { ?><span class="text-success"><i class="fa fa-circle"></i> <?= htmlspecialchars($online !== "" ? $online : l7_t("activo")); ?></span><?php } else { ?><span class="text-muted"><?= htmlspecialchars($online !== "" ? $online : "-"); ?></span><?php } ?></td>
								<td><span class="label label-default"><?= htmlspecialchars($d["source"] ?? ""); ?></span></td>
								<td>
									<?php if ($mac !== "") { ?>
										<input type="text" name="alias[<?= htmlspecialchars($mac); ?>]" value="<?= htmlspecialchars($d["alias"] ?? ""); ?>" class="form-control input-sm" maxlength="64" placeholder="<?= l7_t("nome amigavel"); ?>" />
									<?php } else { ?>
										<span class="text-muted">-</span>
									<?php } ?>
								</td>
							</tr>
						<?php } } ?>
						</tbody>
					</table>
				</div>
				<div class="layer7-callout" style="margin-top:8px; display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
					<button type="submit" name="save_aliases" value="1" class="btn btn-primary"><i class="fa fa-save"></i> <?= l7_t("Gravar aliases"); ?></button>
					<span style="border-left:1px solid #ccc; padding-left:10px;">
						<?= l7_t("Atribuir selecionados ao grupo:"); ?>
						<select name="assign_group" class="form-control input-sm" style="display:inline-block; width:auto;">
							<option value=""><?= l7_t("-- escolher grupo --"); ?></option>
							<?php foreach ($l7_groups as $g) {
								$gid = (string)($g["id"] ?? "");
								if ($gid === "") { continue; }
								$gname = (string)($g["name"] ?? $gid);
							?>
							<option value="<?= htmlspecialchars($gid); ?>"><?= htmlspecialchars($gid . ($gname !== $gid ? " - " . $gname : "")); ?></option>
							<?php } ?>
						</select>
						<button type="submit" name="assign_to_group" value="1" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> <?= l7_t("Atribuir a grupo"); ?></button>
						<?php if (empty($l7_groups)) { ?>
							<span class="text-muted"><?= l7_t("(crie um grupo primeiro na aba Politicas > Grupos)"); ?></span>
						<?php } ?>
					</span>
				</div>
			</form>

			<p class="text-muted" style="margin-top:12px;">
				<?= l7_t("O fabricante deriva do prefixo OUI do MAC quando existe uma base OUI no sistema; caso contrario fica vazio. Aliases sao guardados em layer7.json (device_aliases) e nao afectam o enforcement nesta fase."); ?>
			</p>

			<?php layer7_render_footer(); ?>
		</div>
	</div>
</div>
<?php include("foot.inc"); ?>
