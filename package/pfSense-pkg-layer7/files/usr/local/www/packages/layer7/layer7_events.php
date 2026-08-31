<?php
##|+PRIV
##|*IDENT=page-services-layer7-events
##|*NAME=Services: Layer 7 (events)
##|*DESCR=View Layer 7 daemon events.
##|*MATCH=layer7_events.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

function layer7_events_human_bytes($bytes)
{
	$bytes = max(0, (int)$bytes);
	if ($bytes >= 1073741824) {
		return number_format($bytes / 1073741824, 1) . " GiB";
	}
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 1) . " MiB";
	}
	if ($bytes >= 1024) {
		return number_format($bytes / 1024, 1) . " KiB";
	}
	return $bytes . " B";
}

function layer7_events_render_row($ex)
{
	$tone = preg_replace('/[^a-z]/', '', (string)($ex["tone"] ?? "info"));
	if ($tone === "") {
		$tone = "info";
	}
	echo '<div class="l7-event-row l7-event-row--' . htmlspecialchars($tone) . '">';
	echo '<span class="l7-event-meta">' . htmlspecialchars((string)($ex["when"] ?? "")) . '</span>';
	echo '<span class="l7-event-title">' . htmlspecialchars((string)($ex["title"] ?? "")) . '</span>';
	echo '<p class="l7-event-summary">' . htmlspecialchars((string)($ex["summary"] ?? "")) . '</p>';
	echo '<div class="l7-event-raw">' . htmlspecialchars((string)($ex["raw"] ?? "")) . '</div>';
	echo '</div>';
}

$filter = isset($_GET["filter"]) ? trim($_GET["filter"]) : "";
$source = isset($_GET["source"]) ? trim((string)$_GET["source"]) : "events";
if (!in_array($source, array("events", "operational"), true)) {
	$source = "events";
}
$max_lines = 300;
$log_path = $source === "operational"
	? "/var/log/layer7d.log" : "/var/log/layer7-events.log";
$live_lines = 60;
$storage = layer7_log_storage_status();

$all_logs = array();
if (file_exists($log_path)) {
	exec("/usr/bin/tail -n " . (int)$max_lines . " " . escapeshellarg($log_path) . " 2>/dev/null", $all_logs);
} elseif ($source === "operational" && file_exists("/var/log/system.log")) {
	exec("grep 'layer7d' /var/log/system.log | tail -" . $max_lines . " 2>/dev/null", $all_logs);
}

if (isset($_GET["ajax"]) && $_GET["ajax"] === "1") {
	$live_logs = array();
	foreach ($all_logs as $line) {
		if (layer7_events_line_matches($line, $filter)) {
			$live_logs[] = $line;
		}
	}
	$live_logs = array_slice($live_logs, -$live_lines);
	$explained = array();
	foreach ($live_logs as $line) {
		$explained[] = layer7_event_explain_line($line);
	}
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($explained, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

$filtered_logs = array();
foreach ($all_logs as $line) {
	if (layer7_events_line_matches($line, $filter)) {
		$filtered_logs[] = $line;
	}
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Events"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - Eventos"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("eventos"); ?>
		<div class="layer7-content">

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Armazenamento de logs"); ?></div>
			<div class="layer7-admin-block__body">
				<div class="row">
					<div class="col-sm-4">
						<strong><?= l7_t("Operacional"); ?>:</strong>
						<?= htmlspecialchars(layer7_events_human_bytes($storage["operational"]["bytes"])); ?>
						<span class="text-muted">(<?= (int)$storage["operational"]["files"]; ?> <?= l7_t("arquivos"); ?>)</span>
					</div>
					<div class="col-sm-4">
						<strong><?= l7_t("Eventos"); ?>:</strong>
						<?= htmlspecialchars(layer7_events_human_bytes($storage["events"]["bytes"])); ?>
						<span class="text-muted">(<?= (int)$storage["events"]["files"]; ?> <?= l7_t("arquivos"); ?>)</span>
					</div>
					<div class="col-sm-4">
						<strong>SQLite:</strong>
						<?= htmlspecialchars(layer7_events_human_bytes($storage["db_bytes"])); ?>
						<span class="text-muted">/ <?= (int)$storage["db_max_mb"]; ?> MiB</span>
					</div>
				</div>
				<p class="small text-muted" style="margin-top:8px; margin-bottom:0;">
					<?= sprintf(l7_t("Rotacao automatica: %d MiB por arquivo, %d copias antigas."),
					    (int)$storage["max_mb_per_file"], (int)$storage["keep_files"]); ?>
					<?= !empty($storage["event_log_enabled"])
					    ? l7_t("Log detalhado activo.")
					    : l7_t("Log detalhado desactivado; bloqueios continuam auditados."); ?>
				</p>
			</div>
		</div>

		<div class="layer7-toolbar" style="margin-bottom:12px;">
			<a class="btn btn-sm <?= $source === "events" ? "btn-primary" : "btn-default"; ?>"
				href="layer7_events.php?source=events"><?= l7_t("Eventos de trafego"); ?></a>
			<a class="btn btn-sm <?= $source === "operational" ? "btn-primary" : "btn-default"; ?>"
				href="layer7_events.php?source=operational"><?= l7_t("Log operacional"); ?></a>
			<label class="checkbox-inline" style="margin-left:12px;">
				<input type="checkbox" id="l7-show-tech" />
				<?= l7_t("Mostrar detalhe tecnico"); ?>
			</label>
		</div>

		<div class="l7-event-legend">
			<p><?= l7_t("O que significa cada linha"); ?></p>
			<ul>
				<li><?= l7_t("Trafego observado: o Layer7 viu um acesso e nao bloqueou."); ?></li>
				<li><?= l7_t("Pedido de nome (DNS): o aparelho perguntou o endereco de um site."); ?></li>
				<li><?= l7_t("Nome encontrado (DNS): o sistema descobriu o numero (IP) desse site."); ?></li>
			</ul>
		</div>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Monitor ao vivo"); ?></div>
			<div class="layer7-admin-block__body">
				<p class="small text-muted"><?= l7_t("Atualiza automaticamente os ultimos eventos do daemon. Use o filtro abaixo para restringir o fluxo exibido."); ?></p>
			<div class="layer7-toolbar">
				<button type="button" class="btn btn-success btn-sm" id="l7-live-toggle"><?= l7_t("Pausar"); ?></button>
				<button type="button" class="btn btn-default btn-sm" id="l7-live-refresh"><?= l7_t("Atualizar agora"); ?></button>
				<button type="button" class="btn btn-default btn-sm" id="l7-live-clear"><?= l7_t("Limpar visualizacao"); ?></button>
				<span id="l7-live-count" class="text-muted" style="font-size:12px; margin-left:8px;"></span>
			</div>
				<div id="l7-live-view" class="l7-event-list l7-event-list--live">
					<div class="l7-event-empty"><?= l7_t("Aguardando eventos..."); ?></div>
				</div>
			</div>
		</div>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Filtrar logs"); ?></div>
			<div class="layer7-admin-block__body">
				<form method="get" class="form-inline">
					<input type="hidden" name="source" value="<?= htmlspecialchars($source); ?>">
					<div class="form-group">
						<input type="text" name="filter" class="form-control" style="width: 320px;" maxlength="100"
							value="<?= htmlspecialchars($filter); ?>" placeholder="<?= l7_t("Ex: TikTok, bloqueado, 172.16..."); ?>" />
					</div>
					<button type="submit" class="btn btn-primary"><?= l7_t("Filtrar"); ?></button>
					<?php if ($filter !== "") { ?>
					<a href="layer7_events.php?source=<?= urlencode($source); ?>" class="btn btn-default"><?= l7_t("Limpar filtro"); ?></a>
					<?php } ?>
				</form>
			</div>
		</div>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header">
				<?= l7_t("Todos os logs"); ?>
				<span class="badge"><?= count($filtered_logs); ?></span>
			</div>
			<div class="layer7-admin-block__body">
				<?php if ($filter !== "") { ?>
				<p class="small text-muted"><?= l7_t("filtro"); ?>: <?= htmlspecialchars($filter); ?></p>
				<?php } ?>
				<?php if (count($filtered_logs) > 0) { ?>
				<div class="l7-event-list">
					<?php foreach ($filtered_logs as $line) {
						layer7_events_render_row(layer7_event_explain_line($line));
					} ?>
				</div>
				<?php } else { ?>
				<div class="alert alert-info">
					<?php if ($filter !== "") { ?>
					<?= l7_t("Nenhum log correspondente ao filtro."); ?>
					<?php } else { ?>
					<?= sprintf(l7_t("Nenhum evento encontrado em %s."), htmlspecialchars($log_path)); ?>
					<?php } ?>
				</div>
				<?php } ?>
			</div>
		</div>

		</div>
	</div>
</div>
<script>
(function() {
	var liveView  = document.getElementById('l7-live-view');
	var toggleBtn = document.getElementById('l7-live-toggle');
	var refreshBtn = document.getElementById('l7-live-refresh');
	var clearBtn  = document.getElementById('l7-live-clear');
	var countEl   = document.getElementById('l7-live-count');
	var techBox   = document.getElementById('l7-show-tech');
	var pageEl    = document.querySelector('.layer7-page');
	var paused    = false;
	var timer     = null;
	var refreshMs = 2000;
	var ajaxUrl   = 'layer7_events.php?ajax=1&source=<?= rawurlencode($source); ?>&filter=<?= rawurlencode($filter); ?>';
	var l7WaitingEvents = <?= json_encode(l7_t("Aguardando eventos...")); ?>;
	var l7LineSuffix = <?= json_encode(l7_t("linha(s)")); ?>;
	var techKey = 'l7-events-show-tech';

	/* Buffer acumulado — novas linhas sao sempre adicionadas, nunca removidas */
	var seenLines = [];
	var maxLines  = 500;

	function eventRaw(item) {
		if (!item) return '';
		if (typeof item === 'string') return item;
		return item.raw || '';
	}

	function renderRow(ex) {
		var tone = String((ex && ex.tone) || 'info').replace(/[^a-z]/g, '');
		if (!tone) tone = 'info';
		var row = document.createElement('div');
		row.className = 'l7-event-row l7-event-row--' + tone;
		var meta = document.createElement('span');
		meta.className = 'l7-event-meta';
		meta.textContent = (ex && ex.when) ? ex.when : '';
		var title = document.createElement('span');
		title.className = 'l7-event-title';
		title.textContent = (ex && ex.title) ? ex.title : '';
		var summary = document.createElement('p');
		summary.className = 'l7-event-summary';
		summary.textContent = (ex && ex.summary) ? ex.summary : '';
		var raw = document.createElement('div');
		raw.className = 'l7-event-raw';
		raw.textContent = eventRaw(ex);
		row.appendChild(meta);
		row.appendChild(title);
		row.appendChild(summary);
		row.appendChild(raw);
		return row;
	}

	function updateView() {
		if (!liveView) return;
		liveView.textContent = '';
		if (seenLines.length === 0) {
			var empty = document.createElement('div');
			empty.className = 'l7-event-empty';
			empty.textContent = l7WaitingEvents;
			liveView.appendChild(empty);
		} else {
			for (var i = 0; i < seenLines.length; i++) {
				liveView.appendChild(renderRow(seenLines[i]));
			}
		}
		liveView.scrollTop = liveView.scrollHeight;
		if (countEl) {
			countEl.textContent = seenLines.length > 0
				? seenLines.length + ' ' + l7LineSuffix : '';
		}
	}

	function mergeIncoming(incoming) {
		if (!incoming || incoming.length === 0) return;

		var newLines = [];

		if (seenLines.length === 0) {
			newLines = incoming;
		} else {
			var lastSeen = eventRaw(seenLines[seenLines.length - 1]);
			var overlapIdx = -1;
			for (var i = incoming.length - 1; i >= 0; i--) {
				if (eventRaw(incoming[i]) === lastSeen) {
					overlapIdx = i;
					break;
				}
			}

			if (overlapIdx >= 0) {
				newLines = incoming.slice(overlapIdx + 1);
			} else {
				var recentSet = {};
				var window100 = seenLines.slice(-100);
				for (var j = 0; j < window100.length; j++) {
					recentSet[eventRaw(window100[j])] = true;
				}
				newLines = incoming.filter(function(l) {
					return !recentSet[eventRaw(l)];
				});
			}
		}

		if (newLines.length === 0) return;

		for (var k = 0; k < newLines.length; k++) {
			seenLines.push(newLines[k]);
		}
		if (seenLines.length > maxLines) {
			seenLines = seenLines.slice(seenLines.length - maxLines);
		}
		updateView();
	}

	function fetchLive() {
		if (paused || !liveView) return;
		var xhr = new XMLHttpRequest();
		xhr.open('GET', ajaxUrl, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState !== 4 || xhr.status !== 200) return;
			var incoming = [];
			try {
				incoming = JSON.parse(xhr.responseText);
			} catch (e) {
				return;
			}
			if (!incoming || !incoming.length) return;
			mergeIncoming(incoming);
		};
		xhr.send(null);
	}

	function schedule() {
		if (timer) clearInterval(timer);
		timer = setInterval(fetchLive, refreshMs);
	}

	function applyTech() {
		if (!pageEl || !techBox) return;
		if (techBox.checked) {
			pageEl.classList.add('l7-show-tech');
		} else {
			pageEl.classList.remove('l7-show-tech');
		}
		try {
			localStorage.setItem(techKey, techBox.checked ? '1' : '0');
		} catch (e) {}
	}

	if (techBox && pageEl) {
		try {
			techBox.checked = localStorage.getItem(techKey) === '1';
		} catch (e) {}
		techBox.addEventListener('change', applyTech);
		applyTech();
	}

	if (toggleBtn) {
		toggleBtn.addEventListener('click', function() {
			paused = !paused;
			toggleBtn.textContent   = paused ? <?= json_encode(l7_t("Retomar")); ?> : <?= json_encode(l7_t("Pausar")); ?>;
			toggleBtn.className     = paused
				? 'btn btn-warning btn-sm'
				: 'btn btn-success btn-sm';
			if (!paused) fetchLive();
		});
	}

	if (refreshBtn) {
		refreshBtn.addEventListener('click', function() { fetchLive(); });
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', function() {
			seenLines = [];
			updateView();
		});
	}

	updateView();
	fetchLive();
	schedule();
})();
</script>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
