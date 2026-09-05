<?php
##|+PRIV
##|*IDENT=page-services-layer7-allowlist
##|*NAME=Services: Layer 7 (allowlist de destinos)
##|*DESCR=Allow access to Layer 7 destination allowlist.
##|*MATCH=layer7_allowlist.php*
##|-PRIV

/*
 * Layer7 — allowlist de destinos (Bloco 3 da Fase 1).
 * Edicao simples por textarea: 1 entrada por linha (dominio, IP/CIDR IPv4 ou IPv6).
 * A lista combinada (operador + seed embutido) e honrada:
 *   - pelo daemon (`l7_allowlist_*`) — antes de adicionar IPs a layer7_block_dst.
 *   - pelo PF do pacote — marca L7ALLOW; so os blocks Layer7 a ignoram.
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

function l7_allow_normalize_input($raw)
{
	$out = array();
	$lines = preg_split('/\r\n|\n|\r/', (string)$raw);
	foreach ($lines as $ln) {
		$s = trim((string)$ln);
		if ($s === "" || $s[0] === "#") {
			continue;
		}
		/* Dominios + IPv4 + IPv6 (':' permitido) — passo 12.9. */
		if (preg_match('/^[A-Za-z0-9._\-\/:]+$/', $s)) {
			$out[] = $s;
		}
	}
	return array_values(array_unique($out));
}

function l7_allow_classify($s)
{
	if ($s === "") {
		return "invalid";
	}
	if (strpos($s, "/") !== false) {
		if (layer7_cidr_valid($s)) {
			return "cidr";
		}
		if (layer7_cidr6_valid($s)) {
			return "cidr6";
		}
		return "invalid";
	}
	if (layer7_ipv4_valid($s)) {
		return "ipv4";
	}
	if (layer7_ipv6_valid($s)) {
		return "ipv6";
	}
	if (preg_match('/^[A-Za-z0-9._\-]+$/', $s) && strpos($s, ".") !== false) {
		return "domain";
	}
	return "invalid";
}

if ($_POST["save_allowlist"] ?? false) {
	$data = layer7_load_or_default();
	$entries = l7_allow_normalize_input($_POST["entries"] ?? "");
	$valid = array();
	$invalid = array();
	foreach ($entries as $e) {
		$kind = l7_allow_classify($e);
		if ($kind === "invalid") {
			$invalid[] = $e;
		} else {
			$valid[] = $e;
		}
	}
	if (!empty($invalid)) {
		$input_errors[] = l7_t("Entradas invalidas (ignoradas): ") .
		    implode(", ", array_slice($invalid, 0, 10)) .
		    (count($invalid) > 10 ? " ..." : "");
	}
	if (count($valid) > 256) {
		$input_errors[] = l7_t("Limite de 256 entradas (alem da seed). Reduza a lista.");
	}
	if (empty($input_errors)) {
		$data["layer7"]["dst_allowlist"] = $valid;
		if (layer7_save_json($data)) {
			$applied = layer7_dst_allowlist_apply_to_pf();
			layer7_signal_reload();
			layer7_filter_configure_safe();
			$savemsg = sprintf(
			    l7_t("Allowlist gravada (%d entradas do operador). %d IPs/CIDRs aplicados em <layer7_allow_dst>; o daemon repovoa dominios via DNS."),
			    count($valid), $applied);
		}
	}
}

$data = layer7_load_or_default();
$user_entries = isset($data["layer7"]["dst_allowlist"]) &&
    is_array($data["layer7"]["dst_allowlist"])
    ? $data["layer7"]["dst_allowlist"] : array();
$seed_entries = layer7_dst_allowlist_seed_entries();

require_once("classes/Form.class.php");

$l7_allow_entries_display = implode("\n", $user_entries);
if (($_POST["save_allowlist"] ?? false) && empty($savemsg)) {
	$l7_allow_entries_display = (string)($_POST["entries"] ?? "");
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Allowlist"));
include("head.inc");
layer7_render_tabs("allowlist");
layer7_render_messages();
?>
<div class="alert alert-info">
	<?= htmlspecialchars(l7_t("A lista do operador e somada a lista-semente embutida. Aceita dominios e subdominios por sufixo, IPv4/IPv6 e CIDRs. Politicas de bloqueio explicitas e regras nativas do pfSense continuam a aplicar-se.")); ?>
</div>
<?php
$form = new Form(false);
$form->setAction("layer7_allowlist.php");
$sec = new Form_Section(l7_t("Entradas do operador"));
$hid = new Form_Input("save_allowlist", "", "hidden", "1");
$sec->addInput($hid);
$entries_in = new Form_Textarea("entries", l7_t("Entradas"), $l7_allow_entries_display);
$entries_in->setRows(14);
$entries_in->setAttribute("id", "l7-allow-entries");
$entries_in->setHelp(l7_t("1 entrada por linha. Linhas vazias e linhas que comecem por '#' sao ignoradas. Exemplos validos: bb.com.br, 8.8.8.8, 200.201.0.0/16, 2001:db8::1, 2001:db8::/32."));
$sec->addInput($entries_in);
$submit_html = '<button type="submit" class="btn btn-primary" id="l7-allow-submit">' .
	'<i class="fa fa-save"></i> ' . htmlspecialchars(l7_t("Gravar allowlist")) . '</button>';
$sec->addInput(new Form_StaticText("", $submit_html));
$form->add($sec);
print($form);
?>
<div class="panel panel-default" id="l7-allow-seed">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?= htmlspecialchars(l7_t("Lista-semente embutida")); ?>
			<span class="badge"><?= (int)count($seed_entries); ?></span>
		</h2>
	</div>
	<div class="panel-body">
		<p class="help-block"><?= htmlspecialchars(l7_t("Apenas leitura. Esta lista e fornecida pelo pacote. Para solicitar alteracoes, contacte o suporte.")); ?></p>
		<p class="help-block text-muted"><?= htmlspecialchars(l7_t("Ficheiro do pacote: /usr/local/etc/layer7/allowlist-seed.txt")); ?></p>
		<?php if (empty($seed_entries)) { ?>
		<div class="alert alert-warning"><?= htmlspecialchars(l7_t("Ficheiro de seed ausente ou ilegivel.")); ?></div>
		<?php } else { ?>
		<details>
			<summary><?= htmlspecialchars(l7_t("Mostrar/esconder seed")); ?></summary>
			<pre class="pre-scrollable"><?= htmlspecialchars(implode("\n", $seed_entries)); ?></pre>
		</details>
		<?php } ?>
	</div>
</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php include("foot.inc"); ?>
