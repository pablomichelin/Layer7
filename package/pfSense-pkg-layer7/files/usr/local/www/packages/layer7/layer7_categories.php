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
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - Categorias nDPI"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("categories"); ?>
		<div class="layer7-content">

		<div class="layer7-section">
			<p class="layer7-lead"><?= sprintf(l7_t("Referencia de todas as aplicacoes detectaveis pelo nDPI, organizadas por categoria. Total: %d apps em %d categorias."), $total_protos, $total_cats); ?></p>

			<?php if (empty($by_cat)) { ?>
			<div class="alert alert-warning"><?= l7_t("Nao foi possivel obter a lista de protocolos. Verifique se o daemon (layer7d) esta instalado e funcional."); ?></div>
			<?php } else { ?>

			<div class="form-group" style="max-width:400px;">
				<input type="text" id="l7CatSearch" class="form-control" placeholder="<?= l7_t("Pesquisar app ou categoria..."); ?>" />
			</div>

			<div id="l7CatAccordion">
			<?php $cat_idx = 0; foreach ($by_cat as $cat_name => $protos) {
				if (!is_array($protos)) continue;
				sort($protos);
				$cat_idx++;
				$cat_id = "l7cat_" . $cat_idx;
			?>
				<div class="panel panel-default l7-cat-panel" data-category="<?= htmlspecialchars(strtolower($cat_name)); ?>">
					<div class="panel-heading l7-cat-heading" data-toggle="collapse" data-target="#<?= $cat_id; ?>" style="cursor:pointer;">
						<h4 class="panel-title">
							<i class="fa fa-caret-right l7-cat-caret"></i>
							<?= htmlspecialchars($cat_name); ?>
							<span class="badge"><?= count($protos); ?></span>
						</h4>
					</div>
					<div id="<?= $cat_id; ?>" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="l7-cat-protos">
							<?php foreach ($protos as $proto) { ?>
								<span class="label label-default l7-proto-tag" data-proto="<?= htmlspecialchars(strtolower($proto)); ?>"><?= htmlspecialchars($proto); ?></span>
							<?php } ?>
							</div>
						</div>
					</div>
				</div>
			<?php } ?>
			</div>

			<?php } ?>
		</div>

		</div>
	</div>
</div>
<style>
.l7-cat-heading { transition: background 0.15s; }
.l7-cat-heading:hover { background: #f5f8fc; }
.l7-cat-heading .panel-title { margin: 0; font-size: 15px; font-weight: 600; }
.l7-cat-heading .badge { margin-left: 8px; background: #337ab7; }
.l7-cat-caret { margin-right: 8px; transition: transform 0.2s; width: 12px; display: inline-block; text-align: center; }
.l7-cat-panel.open .l7-cat-caret { transform: rotate(90deg); }
.l7-cat-protos { display: flex; flex-wrap: wrap; gap: 6px; }
.l7-proto-tag { font-size: 13px; font-weight: normal; padding: 5px 10px; }
.l7-proto-tag.l7-highlight { background: #337ab7; }
.l7-cat-panel.l7-hidden { display: none; }
</style>
<script>
(function() {
	var panels = document.querySelectorAll('.l7-cat-panel');

	panels.forEach(function(panel) {
		var collapseEl = panel.querySelector('.panel-collapse');
		if (collapseEl) {
			collapseEl.addEventListener('show.bs.collapse', function() {
				panel.classList.add('open');
			});
			collapseEl.addEventListener('hide.bs.collapse', function() {
				panel.classList.remove('open');
			});
			/* Bootstrap 3 fallback */
			$(collapseEl).on('show.bs.collapse', function() {
				panel.classList.add('open');
			});
			$(collapseEl).on('hide.bs.collapse', function() {
				panel.classList.remove('open');
			});
		}
	});

	var searchInput = document.getElementById('l7CatSearch');
	if (searchInput) {
		searchInput.addEventListener('input', function() {
			var q = this.value.toLowerCase().trim();
			panels.forEach(function(panel) {
				if (q === '') {
					panel.classList.remove('l7-hidden');
					var tags = panel.querySelectorAll('.l7-proto-tag');
					tags.forEach(function(t) { t.classList.remove('l7-highlight'); t.style.display = ''; });
					return;
				}
				var catName = panel.getAttribute('data-category') || '';
				var catMatch = catName.indexOf(q) >= 0;
				var tags = panel.querySelectorAll('.l7-proto-tag');
				var anyProtoMatch = false;
				tags.forEach(function(t) {
					var pName = t.getAttribute('data-proto') || '';
					var match = pName.indexOf(q) >= 0;
					if (match) {
						anyProtoMatch = true;
						t.classList.add('l7-highlight');
						t.style.display = '';
					} else {
						t.classList.remove('l7-highlight');
						t.style.display = catMatch ? '' : 'none';
					}
				});
				if (catMatch || anyProtoMatch) {
					panel.classList.remove('l7-hidden');
					var collapseEl = panel.querySelector('.panel-collapse');
					if (collapseEl && !collapseEl.classList.contains('in')) {
						$(collapseEl).collapse('show');
					}
				} else {
					panel.classList.add('l7-hidden');
				}
			});
		});
	}
})();
</script>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
