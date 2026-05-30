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
				<input type="hidden" name="save_aliases" value="1" />
				<div class="table-responsive">
					<table class="table table-striped table-hover table-condensed">
						<thead>
							<tr>
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
							<tr><td colspan="8" class="text-muted"><?= l7_t("Nenhum dispositivo observado (sem leases DHCP nem entradas ARP)."); ?></td></tr>
						<?php } else {
							foreach ($devices as $d) {
								$mac = (string)($d["mac"] ?? "");
								$online = (string)($d["online"] ?? "");
								$is_on = (stripos($online, "online") !== false || stripos($online, "active") !== false);
						?>
							<tr>
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
				<div style="margin-top:8px;">
					<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= l7_t("Gravar aliases"); ?></button>
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
