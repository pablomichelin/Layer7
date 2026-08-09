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

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Allowlist"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - allowlist de destinos"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("allowlist"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead">
				<?= l7_t("Destinos que NUNCA devem ser bloqueados pelo Layer7, mesmo em enforce. Aceita dominios (cobrem subdominios por sufixo), IPv4/IPv6 host e CIDRs IPv4/IPv6. A lista do operador e somada a uma lista-semente embutida (bancos BR, gov, push mobile)."); ?>
			</p>

			<form method="post" action="layer7_allowlist.php" class="form-horizontal">
				<input type="hidden" name="save_allowlist" value="1" />

				<div class="layer7-admin-block">
					<div class="layer7-admin-block__header"><?= l7_t("Entradas do operador"); ?></div>
					<div class="layer7-admin-block__body">
						<p class="text-muted"><?= l7_t("1 entrada por linha. Linhas vazias e linhas que comecem por '#' sao ignoradas. Exemplos validos: bb.com.br, 8.8.8.8, 200.201.0.0/16, 2001:db8::1, 2001:db8::/32."); ?></p>
						<textarea name="entries" rows="14" class="form-control" style="font-family:monospace;"><?= htmlspecialchars(implode("\n", $user_entries)); ?></textarea>
						<div style="margin-top:12px;">
							<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= l7_t("Gravar allowlist"); ?></button>
						</div>
					</div>
				</div>

				<div class="layer7-admin-block">
					<div class="layer7-admin-block__header">
						<?= l7_t("Lista-semente embutida"); ?>
						<span class="badge"><?= count($seed_entries); ?></span>
					</div>
					<div class="layer7-admin-block__body">
						<p class="text-muted"><?= l7_t("Apenas leitura. Instalada pelo pacote em /usr/local/etc/layer7/allowlist-seed.txt. Para remover entradas da seed, adicione-as via blacklists (cobrem a allowlist) ou contacte o suporte."); ?></p>
						<?php if (empty($seed_entries)) { ?>
							<p class="text-warning"><?= l7_t("Ficheiro de seed ausente ou ilegivel."); ?></p>
						<?php } else { ?>
							<details>
								<summary style="cursor:pointer;"><?= l7_t("Mostrar/esconder seed"); ?></summary>
								<pre style="max-height: 300px; overflow:auto; margin-top:8px; background:#f7f7f7; padding:8px 12px;"><?= htmlspecialchars(implode("\n", $seed_entries)); ?></pre>
							</details>
						<?php } ?>
					</div>
				</div>
			</form>

			<?php layer7_render_footer(); ?>
		</div>
	</div>
</div>
<?php include("foot.inc"); ?>
