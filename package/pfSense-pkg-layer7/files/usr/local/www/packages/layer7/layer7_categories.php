<?php
##|+PRIV
##|*IDENT=page-services-layer7-categories
##|*NAME=Services: Layer 7 (categories)
##|*DESCR=Allow access to Layer 7 nDPI categories.
##|*MATCH=layer7_categories.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$ndpi_list = layer7_ndpi_list();
$by_cat = isset($ndpi_list["protocols_by_category"]) && is_array($ndpi_list["protocols_by_category"])
	? $ndpi_list["protocols_by_category"] : array();
$total_protos = isset($ndpi_list["protocols"]) && is_array($ndpi_list["protocols"])
	? count($ndpi_list["protocols"]) : 0;
$total_cats = count($by_cat);

ksort($by_cat);

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Categorias"));
include("head.inc");
layer7_render_tabs("policies");
layer7_render_policies_subnav("categories");
?>
<div class="alert alert-info">
	<?= htmlspecialchars(l7_t("Consulta de referencia. Esta pagina lista as aplicacoes detectaveis pelo nDPI. Nao aplica politicas nem altera a deteccao.")); ?>
</div>
<div class="panel panel-default" id="l7-categories">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Categorias nDPI")); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block"><?= htmlspecialchars(sprintf(l7_t("Referencia de todas as aplicacoes detectaveis pelo nDPI, organizadas por categoria. Total: %d apps em %d categorias."), $total_protos, $total_cats)); ?></p>
		<?php if (empty($by_cat)) { ?>
		<div class="alert alert-warning"><?= htmlspecialchars(l7_t("Nao foi possivel obter a lista de protocolos. Verifique se o daemon (layer7d) esta instalado e funcional.")); ?></div>
		<?php } else { ?>
		<div class="form-group">
			<label for="l7-cat-search"><?= htmlspecialchars(l7_t("Pesquisar")); ?></label>
			<input type="search" class="form-control" id="l7-cat-search" placeholder="<?= htmlspecialchars(l7_t("Pesquisar app ou categoria...")); ?>" autocomplete="off" />
			<span class="help-block"><?= htmlspecialchars(l7_t("A pesquisa so filtra esta consulta. Nao altera a deteccao nDPI nem as politicas.")); ?></span>
			<p>
				<button type="button" class="btn btn-default" id="l7-cat-clear"><?= htmlspecialchars(l7_t("Limpar")); ?></button>
			</p>
		</div>
		<p id="l7-cat-empty" class="alert alert-info hidden" hidden><?= htmlspecialchars(l7_t("Nenhuma categoria ou aplicacao corresponde a pesquisa.")); ?></p>
		<?php $cat_idx = 0; foreach ($by_cat as $cat_name => $protos) {
			if (!is_array($protos)) {
				continue;
			}
			sort($protos);
			$cat_idx++;
			$cat_id = "l7cat_" . $cat_idx;
		?>
		<details class="panel panel-default" id="<?= htmlspecialchars($cat_id); ?>" data-category="<?= htmlspecialchars(strtolower((string)$cat_name), ENT_QUOTES, "UTF-8"); ?>">
			<summary class="panel-heading">
				<h3 class="panel-title">
					<?= htmlspecialchars((string)$cat_name); ?>
					<span class="badge"><?= (int)count($protos); ?></span>
				</h3>
			</summary>
			<div class="panel-body">
				<div class="table-responsive">
					<table class="table table-striped table-condensed">
						<thead>
							<tr>
								<th><?= htmlspecialchars(l7_t("Apps")); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($protos as $proto) { ?>
							<tr data-proto="<?= htmlspecialchars(strtolower((string)$proto), ENT_QUOTES, "UTF-8"); ?>">
								<td><?= htmlspecialchars((string)$proto); ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</details>
		<?php } ?>
		<?php } ?>
	</div>
</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<script>
(function () {
	function l7CatInit() {
		var root = document.getElementById("l7-categories");
		var searchInput = document.getElementById("l7-cat-search");
		var clearBtn = document.getElementById("l7-cat-clear");
		var emptyMsg = document.getElementById("l7-cat-empty");
		if (!root || !searchInput) {
			return;
		}
		var groups = root.querySelectorAll("details[data-category]");

		function setHidden(el, hide) {
			if (!el) {
				return;
			}
			if (hide) {
				el.classList.add("hidden");
				el.setAttribute("hidden", "hidden");
			} else {
				el.classList.remove("hidden");
				el.removeAttribute("hidden");
			}
		}

		function applyFilter() {
			var q = (searchInput.value || "").toLowerCase().trim();
			var anyVisible = false;
			var i;
			var j;
			for (i = 0; i < groups.length; i++) {
				var panel = groups[i];
				var rows = panel.querySelectorAll("[data-proto]");
				var catName = panel.getAttribute("data-category") || "";
				var catMatch = q !== "" && catName.indexOf(q) >= 0;
				var anyProtoMatch = false;
				for (j = 0; j < rows.length; j++) {
					var row = rows[j];
					var pName = row.getAttribute("data-proto") || "";
					var protoMatch = q !== "" && pName.indexOf(q) >= 0;
					if (protoMatch) {
						anyProtoMatch = true;
						row.classList.add("info");
					} else {
						row.classList.remove("info");
					}
					if (q === "" || catMatch || protoMatch) {
						setHidden(row, false);
					} else {
						setHidden(row, true);
					}
				}
				if (q === "" || catMatch || anyProtoMatch) {
					setHidden(panel, false);
					if (q !== "") {
						panel.open = true;
					}
					anyVisible = true;
				} else {
					setHidden(panel, true);
				}
			}
			if (emptyMsg) {
				setHidden(emptyMsg, !(q !== "" && !anyVisible));
			}
		}

		searchInput.addEventListener("input", applyFilter);
		if (clearBtn) {
			clearBtn.addEventListener("click", function () {
				searchInput.value = "";
				applyFilter();
			});
		}
	}

	if (typeof events !== "undefined" && events && typeof events.push === "function") {
		events.push(l7CatInit);
	} else if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", l7CatInit);
	} else {
		l7CatInit();
	}
})();
</script>
<?php include("foot.inc"); ?>
