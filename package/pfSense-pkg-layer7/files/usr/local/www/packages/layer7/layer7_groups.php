<?php
##|+PRIV
##|*IDENT=page-services-layer7-groups
##|*NAME=Services: Layer 7 (groups)
##|*DESCR=Allow access to Layer 7 device groups.
##|*MATCH=layer7_groups.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");
require_once("classes/Form.class.php");

if ($_POST["add_group"] ?? false) {
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		$data["layer7"]["groups"] = array();
	}
	$groups = &$data["layer7"]["groups"];
	$ok = true;

	if (count($groups) >= 16) {
		$input_errors[] = l7_t("Limite de 16 grupos.");
		$ok = false;
	}

	$gid = trim($_POST["new_group_id"] ?? "");
	if ($ok && !layer7_group_id_valid($gid)) {
		$input_errors[] = l7_t("ID invalido (letras, numeros, _ e -; max. 80).");
		$ok = false;
	}
	if ($ok) {
		foreach ($groups as $existing) {
			if (isset($existing["id"]) && (string)$existing["id"] === $gid) {
				$input_errors[] = l7_t("Ja existe um grupo com esse ID.");
				$ok = false;
				break;
			}
		}
	}

	$gname = trim($_POST["new_group_name"] ?? "");
	if ($ok && strlen($gname) > 160) {
		$input_errors[] = l7_t("Nome demasiado longo (max. 160).");
		$ok = false;
	}

	$cidrs = layer7_parse_cidr_textarea($_POST["new_group_cidrs"] ?? "");
	$hosts = layer7_parse_ip_textarea($_POST["new_group_hosts"] ?? "");
	$dev_macs = layer7_normalize_macs($_POST["new_group_devices"] ?? "");

	if ($ok && empty($cidrs) && empty($hosts) && empty($dev_macs)) {
		$input_errors[] = l7_t("Indique pelo menos um CIDR, IP ou dispositivo (MAC).");
		$ok = false;
	}

	if ($ok) {
		$group = array(
			"id" => $gid,
			"name" => $gname !== "" ? $gname : $gid
		);
		if (!empty($cidrs)) {
			$group["cidrs"] = $cidrs;
		}
		if (!empty($hosts)) {
			$group["hosts"] = $hosts;
		}
		if (!empty($dev_macs)) {
			$group["device_macs"] = $dev_macs;
			$group["device_ips"] = layer7_resolve_macs_to_ips($dev_macs);
		}
		$groups[] = $group;
		if (layer7_save_json($data)) {
			layer7_pf_config_resync();
			$n_ips = isset($group["device_ips"]) ? count($group["device_ips"]) : 0;
			$savemsg = empty($dev_macs)
				? l7_t("Grupo adicionado.")
				: sprintf(l7_t("Grupo adicionado. %d dispositivos, %d IPs resolvidos agora."), count($dev_macs), $n_ips);
		}
	}
	unset($groups);
}

if ($_POST["delete_group"] ?? false) {
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		$data["layer7"]["groups"] = array();
	}
	$groups = &$data["layer7"]["groups"];
	$idx = (int)($_POST["delete_group_index"] ?? -1);
	if ($idx < 0 || $idx >= count($groups)) {
		$input_errors[] = l7_t("Indice de grupo invalido.");
	} else {
		$del_id = isset($groups[$idx]["id"]) ? (string)$groups[$idx]["id"] : "";
		$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
			? $data["layer7"]["policies"] : array();
		$in_use = false;
		foreach ($policies as $pol) {
			if (isset($pol["match"]["groups"]) && is_array($pol["match"]["groups"])) {
				if (in_array($del_id, $pol["match"]["groups"], true)) {
					$in_use = true;
					break;
				}
			}
		}
		if ($in_use) {
			$input_errors[] = sprintf(l7_t("O grupo '%s' esta em uso por uma politica. Remova a referencia antes de apagar."), $del_id);
		} else {
			array_splice($groups, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_pf_config_resync();
				$savemsg = l7_t("Grupo removido.");
			}
		}
	}
	unset($groups);
}

$layer7_group_edit_retry = null;

if ($_POST["save_group_edit"] ?? false) {
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		$data["layer7"]["groups"] = array();
	}
	$groups = &$data["layer7"]["groups"];
	$idx = (int)($_POST["edit_group_index"] ?? -1);
	if ($idx < 0 || $idx >= count($groups)) {
		$input_errors[] = l7_t("Indice de grupo invalido.");
	} else {
		$layer7_group_edit_retry = $idx;
		$ok = true;
		$orig = $groups[$idx];
		$gid = isset($orig["id"]) ? (string)$orig["id"] : "";

		$gname = trim($_POST["edit_group_name"] ?? "");
		if ($ok && strlen($gname) > 160) {
			$input_errors[] = l7_t("Nome demasiado longo (max. 160).");
			$ok = false;
		}

		$cidrs = layer7_parse_cidr_textarea($_POST["edit_group_cidrs"] ?? "");
		$hosts = layer7_parse_ip_textarea($_POST["edit_group_hosts"] ?? "");
		$dev_macs = layer7_normalize_macs($_POST["edit_group_devices"] ?? "");

		if ($ok && empty($cidrs) && empty($hosts) && empty($dev_macs)) {
			$input_errors[] = l7_t("Indique pelo menos um CIDR, IP ou dispositivo (MAC).");
			$ok = false;
		}

		if ($ok) {
			$group = array(
				"id" => $gid,
				"name" => $gname !== "" ? $gname : $gid
			);
			if (!empty($cidrs)) {
				$group["cidrs"] = $cidrs;
			}
			if (!empty($hosts)) {
				$group["hosts"] = $hosts;
			}
			if (!empty($dev_macs)) {
				$group["device_macs"] = $dev_macs;
				$group["device_ips"] = layer7_resolve_macs_to_ips($dev_macs);
			}
			$groups[$idx] = $group;
			if (layer7_save_json($data)) {
				layer7_pf_config_resync();
				header("Location: layer7_groups.php");
				exit;
			}
			$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
		}
	}
	unset($groups);
}

if ($_POST["resync_devices"] ?? false) {
	$n_ips = layer7_devices_resync();
	$savemsg = sprintf(l7_t("IPs dos dispositivos re-resolvidos (%d IPs activos nos grupos)."), $n_ips);
}

$data = layer7_load_or_default();
$groups = isset($data["layer7"]["groups"]) && is_array($data["layer7"]["groups"])
	? $data["layer7"]["groups"] : array();
$at_limit = count($groups) >= 16;

$l7_inventory = layer7_device_inventory();

$edit_idx = null;
$edit_group = null;
if ($layer7_group_edit_retry !== null && $layer7_group_edit_retry >= 0 &&
    $layer7_group_edit_retry < count($groups)) {
	$edit_idx = (int)$layer7_group_edit_retry;
	$edit_group = $groups[$edit_idx];
} elseif (isset($_GET["edit"]) && ctype_digit((string)$_GET["edit"])) {
	$ec = (int)$_GET["edit"];
	if ($ec >= 0 && $ec < count($groups)) {
		$edit_idx = $ec;
		$edit_group = $groups[$ec];
	}
}

$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
	? $data["layer7"]["policies"] : array();

function layer7_group_policy_count($gid, $policies)
{
	$count = 0;
	foreach ($policies as $pol) {
		if (isset($pol["match"]["groups"]) && is_array($pol["match"]["groups"])) {
			if (in_array($gid, $pol["match"]["groups"], true)) {
				$count++;
			}
		}
	}
	return $count;
}

$l7_add_retry = (!empty($input_errors) && ($_POST["add_group"] ?? false));
$l7_group_mode = "list";
if ($edit_idx !== null && $edit_group !== null) {
	$l7_group_mode = "edit";
} elseif ($l7_add_retry || (isset($_GET["new"]) && (string)$_GET["new"] === "1")) {
	$l7_group_mode = "new";
}

$l7_new_id = isset($_POST["new_group_id"]) ? (string)$_POST["new_group_id"] : "";
$l7_new_name = isset($_POST["new_group_name"]) ? (string)$_POST["new_group_name"] : "";
$l7_new_cidrs = isset($_POST["new_group_cidrs"]) ? (string)$_POST["new_group_cidrs"] : "";
$l7_new_hosts = isset($_POST["new_group_hosts"]) ? (string)$_POST["new_group_hosts"] : "";
$l7_new_devices = isset($_POST["new_group_devices"]) ? (string)$_POST["new_group_devices"] : "";

$eg_id = "";
$eg_name = "";
$eg_cidrs = "";
$eg_hosts = "";
$eg_devices = "";
$eg_dips = array();
if ($edit_group !== null) {
	$eg_id = isset($edit_group["id"]) ? (string)$edit_group["id"] : "";
	$eg_name = isset($edit_group["name"]) ? (string)$edit_group["name"] : "";
	$eg_cidrs = isset($edit_group["cidrs"]) && is_array($edit_group["cidrs"])
		? implode("\n", $edit_group["cidrs"]) : "";
	$eg_hosts = isset($edit_group["hosts"]) && is_array($edit_group["hosts"])
		? implode("\n", $edit_group["hosts"]) : "";
	$eg_devices = isset($edit_group["device_macs"]) && is_array($edit_group["device_macs"])
		? implode("\n", $edit_group["device_macs"]) : "";
	$eg_dips = isset($edit_group["device_ips"]) && is_array($edit_group["device_ips"])
		? $edit_group["device_ips"] : array();
}
if ($layer7_group_edit_retry !== null && ($_POST["save_group_edit"] ?? false)) {
	if (isset($_POST["edit_group_name"])) {
		$eg_name = (string)$_POST["edit_group_name"];
	}
	if (isset($_POST["edit_group_cidrs"])) {
		$eg_cidrs = (string)$_POST["edit_group_cidrs"];
	}
	if (isset($_POST["edit_group_hosts"])) {
		$eg_hosts = (string)$_POST["edit_group_hosts"];
	}
	if (isset($_POST["edit_group_devices"])) {
		$eg_devices = (string)$_POST["edit_group_devices"];
	}
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Grupos"));
include("head.inc");
layer7_render_tabs("policies");
layer7_render_messages();
layer7_render_policies_subnav("groups");
?>
<div class="alert alert-info">
	<?= htmlspecialchars(l7_t("Crie grupos nomeados (ex.: Funcionarios, Visitantes, Sala 3) por CIDR/sub-rede, por IPs individuais ou por dispositivos (MAC). Depois aplique politicas por grupo. Dispositivos por MAC sao resolvidos para o IP actual via DHCP/ARP.")); ?>
</div>
<?php if ($l7_group_mode === "edit") { ?>
<div id="l7-edit-group">
<?php
	$edit_form = new Form(false);
	$edit_form->setAction("layer7_groups.php#l7-edit-group");
	$sec_edit = new Form_Section(l7_t("Editar grupo"));
	$sec_edit->addInput(new Form_StaticText(
		"id",
		"<code>" . htmlspecialchars($eg_id) . "</code>"
	));
	$idx_hidden = new Form_Input("edit_group_index", "", "hidden", (string)(int)$edit_idx);
	$idx_hidden->setAttribute("id", "l7-edit-group-index");
	$sec_edit->addInput($idx_hidden);
	$name_in = new Form_Input("edit_group_name", l7_t("Nome"), "text", $eg_name);
	$name_in->setAttribute("maxlength", "160");
	$name_in->setAttribute("id", "l7-edit-group-name");
	$sec_edit->addInput($name_in);
	$cidrs_in = new Form_Textarea("edit_group_cidrs", l7_t("CIDRs"), $eg_cidrs);
	$cidrs_in->setRows(4);
	$cidrs_in->setAttribute("id", "l7-edit-group-cidrs");
	$cidrs_in->setAttribute("placeholder", "192.168.10.0/24");
	$cidrs_in->setHelp(l7_t("Um CIDR por linha (max. 8)."));
	$sec_edit->addInput($cidrs_in);
	$hosts_in = new Form_Textarea("edit_group_hosts", l7_t("IPs individuais"), $eg_hosts);
	$hosts_in->setRows(4);
	$hosts_in->setAttribute("id", "l7-edit-group-hosts");
	$hosts_in->setAttribute("placeholder", "10.0.85.100");
	$hosts_in->setHelp(l7_t("Um IPv4 por linha (max. 16)."));
	$sec_edit->addInput($hosts_in);
	$mac_help = l7_t("Um MAC por linha (max. 64). Resolvido para o IP actual via DHCP/ARP. Veja a aba Dispositivos para copiar MACs.");
	if (!empty($eg_dips)) {
		$safe_dips = array();
		foreach ($eg_dips as $dip) {
			$safe_dips[] = htmlspecialchars((string)$dip, ENT_QUOTES, "UTF-8");
		}
		$mac_help .= " " . l7_t("IPs resolvidos agora") . ": " . implode(", ", $safe_dips);
	} elseif ($eg_devices !== "") {
		$mac_help .= " " . l7_t("Nenhum IP resolvido (dispositivos offline ou sem lease/ARP). Recomenda-se DHCP static mapping.");
	}
	$macs_in = new Form_Textarea("edit_group_devices", l7_t("Dispositivos (MAC)"), $eg_devices);
	$macs_in->setRows(4);
	$macs_in->setAttribute("id", "l7-edit-group-devices");
	$macs_in->setAttribute("placeholder", "aa:bb:cc:dd:ee:ff");
	$macs_in->setHelp($mac_help);
	$sec_edit->addInput($macs_in);
	$edit_form->add($sec_edit);
	$save_btn = new Form_Button("save_group_edit", l7_t("Guardar alteracoes"), null, "fa fa-save");
	$save_btn->addClass("btn-primary");
	$edit_form->addGlobal($save_btn);
	print($edit_form);
?>
<p>
	<a class="btn btn-default" href="layer7_groups.php"><?= htmlspecialchars(l7_t("Cancelar edicao")); ?></a>
</p>
</div>
<?php } ?>
<?php if ($l7_group_mode === "new") { ?>
<div id="l7-add-group">
<?php if ($at_limit) { ?>
<div class="alert alert-warning"><?= htmlspecialchars(l7_t("Limite de 16 grupos atingido.")); ?></div>
<?php } else {
	$add_form = new Form(false);
	$add_form->setAction("layer7_groups.php");
	$sec_add = new Form_Section(l7_t("Adicionar grupo"));
	$id_in = new Form_Input("new_group_id", "id", "text", $l7_new_id);
	$id_in->setAttribute("maxlength", "80");
	$id_in->setAttribute("pattern", "[a-zA-Z0-9_-]+");
	$id_in->setAttribute("required", "required");
	$id_in->setAttribute("id", "l7-new-group-id");
	$id_in->setAttribute("placeholder", "funcionarios");
	$id_in->setHelp(l7_t("Identificador unico (letras, numeros, _ e -)."));
	$sec_add->addInput($id_in);
	$add_name = new Form_Input("new_group_name", l7_t("Nome"), "text", $l7_new_name);
	$add_name->setAttribute("maxlength", "160");
	$add_name->setAttribute("id", "l7-new-group-name");
	$add_name->setAttribute("placeholder", l7_t("Ex.: Funcionarios"));
	$sec_add->addInput($add_name);
	$add_cidrs = new Form_Textarea("new_group_cidrs", l7_t("CIDRs"), $l7_new_cidrs);
	$add_cidrs->setRows(4);
	$add_cidrs->setAttribute("id", "l7-new-group-cidrs");
	$add_cidrs->setAttribute("placeholder", "192.168.10.0/24");
	$add_cidrs->setHelp(l7_t("Um CIDR por linha (max. 8). Ex.: 10.0.85.0/24."));
	$sec_add->addInput($add_cidrs);
	$add_hosts = new Form_Textarea("new_group_hosts", l7_t("IPs individuais"), $l7_new_hosts);
	$add_hosts->setRows(4);
	$add_hosts->setAttribute("id", "l7-new-group-hosts");
	$add_hosts->setAttribute("placeholder", "10.0.85.100");
	$add_hosts->setHelp(l7_t("Um IPv4 por linha (max. 16). Opcional se ja tiver CIDRs."));
	$sec_add->addInput($add_hosts);
	$add_macs = new Form_Textarea("new_group_devices", l7_t("Dispositivos (MAC)"), $l7_new_devices);
	$add_macs->setRows(4);
	$add_macs->setAttribute("id", "l7-new-group-devices");
	$add_macs->setAttribute("placeholder", "aa:bb:cc:dd:ee:ff");
	$add_macs->setHelp(l7_t("Um MAC por linha (max. 64). Resolvido para o IP actual via DHCP/ARP. Veja a aba Dispositivos para copiar MACs."));
	$sec_add->addInput($add_macs);
	$add_form->add($sec_add);
	$add_btn = new Form_Button("add_group", l7_t("Adicionar grupo"), null, "fa fa-plus");
	$add_btn->addClass("btn-success");
	$add_form->addGlobal($add_btn);
	print($add_form);
} ?>
<p>
	<a class="btn btn-default" href="layer7_groups.php"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
</p>
<p class="help-block"><?= htmlspecialchars(l7_t("Defina um grupo de dispositivos com CIDRs e/ou IPs individuais. Depois associe o grupo a politicas na pagina de politicas.")); ?></p>
</div>
<?php } ?>
<?php if ($l7_group_mode === "list") { ?>
<div class="panel panel-default" id="l7-groups">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Grupos actuais")); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block"><?= htmlspecialchars(l7_t("A consulta lista os grupos existentes. Criar e editar abrem uma pagina propria.")); ?></p>
		<div id="l7-add-group">
		<?php if ($at_limit) { ?>
			<div class="alert alert-warning"><?= htmlspecialchars(l7_t("Limite de 16 grupos atingido.")); ?></div>
		<?php } else { ?>
			<p>
				<a class="btn btn-primary" href="layer7_groups.php?new=1"><?= htmlspecialchars(l7_t("Adicionar grupo")); ?></a>
			</p>
		<?php } ?>
		</div>
		<?php if (count($groups) === 0) { ?>
		<div class="alert alert-info"><?= htmlspecialchars(l7_t("Nenhum grupo criado.")); ?>
			<a href="layer7_groups.php?new=1"><?= htmlspecialchars(l7_t("Adicionar o primeiro grupo.")); ?></a>
		</div>
		<?php } else { ?>
		<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><code>id</code></th>
						<th><?= htmlspecialchars(l7_t("Nome")); ?></th>
						<th><?= htmlspecialchars(l7_t("CIDRs")); ?></th>
						<th><?= htmlspecialchars(l7_t("IPs")); ?></th>
						<th><?= htmlspecialchars(l7_t("Dispositivos (MAC -> IP)")); ?></th>
						<th><?= htmlspecialchars(l7_t("Politicas")); ?></th>
						<th><?= htmlspecialchars(l7_t("Acoes")); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($groups as $i => $grp) {
					$gid = isset($grp["id"]) ? (string)$grp["id"] : "";
					$gname = isset($grp["name"]) ? (string)$grp["name"] : "";
					$gcidrs = isset($grp["cidrs"]) && is_array($grp["cidrs"]) ? $grp["cidrs"] : array();
					$ghosts = isset($grp["hosts"]) && is_array($grp["hosts"]) ? $grp["hosts"] : array();
					$gmacs = isset($grp["device_macs"]) && is_array($grp["device_macs"]) ? $grp["device_macs"] : array();
					$gdips = isset($grp["device_ips"]) && is_array($grp["device_ips"]) ? $grp["device_ips"] : array();
					$pcount = layer7_group_policy_count($gid, $policies);
				?>
					<tr>
						<td><code><?= htmlspecialchars($gid); ?></code></td>
						<td><?= htmlspecialchars($gname); ?></td>
						<td><?= htmlspecialchars(implode(", ", $gcidrs)); ?></td>
						<td><?= htmlspecialchars(implode(", ", $ghosts)); ?></td>
						<td><?php if (empty($gmacs)) { echo "-"; } else { echo (int)count($gmacs) . " " . htmlspecialchars(l7_t("dispositivos")) . " &rarr; " . (int)count($gdips) . " IP"; } ?></td>
						<td><?= (int)$pcount; ?></td>
						<td>
							<a href="layer7_groups.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= htmlspecialchars(l7_t("Editar")); ?></a>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php } ?>
	</div>
</div>
<div class="panel panel-default" id="l7-groups-maintain">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Manutencao")); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block"><?= htmlspecialchars(l7_t("Re-resolver enderecos e remover um grupo sao accoes separadas da criacao e da edicao.")); ?></p>
<?php
	$rs_form = new Form(false);
	$rs_form->setAction("layer7_groups.php#l7-groups");
	$sec_rs = new Form_Section(l7_t("Resync IPs dos dispositivos"));
	$sec_rs->addInput(new Form_StaticText(
		"",
		htmlspecialchars(l7_t("Re-resolve MAC -> IP de todos os grupos (use apos mudancas de DHCP)."))
	));
	$rs_form->add($sec_rs);
	$rs_btn = new Form_Button("resync_devices", l7_t("Resync IPs dos dispositivos"), null, "fa fa-refresh");
	$rs_btn->addClass("btn-default");
	$rs_form->addGlobal($rs_btn);
	print($rs_form);
	if (count($groups) > 0) {
		$del_opts = array();
		foreach ($groups as $i => $grp) {
			$gid = isset($grp["id"]) ? (string)$grp["id"] : ("#" . $i);
			$gname = isset($grp["name"]) ? (string)$grp["name"] : "";
			$del_opts[(string)(int)$i] = $gid . ($gname !== "" ? " - " . $gname : "");
		}
		$del_sel_value = "0";
		if (!empty($input_errors) && ($_POST["delete_group"] ?? false) && isset($_POST["delete_group_index"])) {
			$posted_del = (string)(int)$_POST["delete_group_index"];
			if (array_key_exists($posted_del, $del_opts)) {
				$del_sel_value = $posted_del;
			}
		}
		$del_form = new Form(false);
		$del_form->setAction("layer7_groups.php#l7-groups");
		$del_form->setAttribute("onsubmit", "return confirm(" . json_encode(l7_t("Remover este grupo?")) . ");");
		$sec_del = new Form_Section(l7_t("Remover grupo"));
		$del_sel = new Form_Select("delete_group_index", l7_t("Remover grupo"), $del_sel_value, $del_opts);
		$del_sel->setAttribute("id", "delete_group_index");
		$sec_del->addInput($del_sel);
		$del_form->add($sec_del);
		$del_btn = new Form_Button("delete_group", l7_t("Remover"), null, "fa fa-trash");
		$del_btn->addClass("btn-danger");
		$del_form->addGlobal($del_btn);
		print($del_form);
	}
?>
	</div>
</div>
<?php } ?>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php include("foot.inc"); ?>
