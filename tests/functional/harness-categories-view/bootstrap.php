<?php
/**
 * Bootstrap isolado — renderiza layer7_categories.php actual.
 * Não carrega guiconfig.inc nem /usr/local/pkg/layer7.inc.
 * Adaptação limitada: require_once / head.inc / foot.inc.
 * layer7_ndpi_list() devolve o fixture injectado; não chama o daemon.
 */

if (!defined("L7HC_ROOT")) {
	define("L7HC_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HC_CATEGORIES")) {
	define(
		"L7HC_CATEGORIES",
		L7HC_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_categories.php"
	);
}

if (!function_exists("gettext")) {
	function gettext($s)
	{
		return $s;
	}
}

if (!function_exists("l7_t")) {
	function l7_t($key)
	{
		return $key;
	}
}

if (!function_exists("layer7_ndpi_list")) {
	function layer7_ndpi_list()
	{
		return isset($GLOBALS["l7hc_ndpi"]) && is_array($GLOBALS["l7hc_ndpi"])
			? $GLOBALS["l7hc_ndpi"]
			: array("protocols" => array(), "categories" => array(), "protocols_by_category" => array());
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($active = "")
	{
		echo "<!-- L7HC_TABS " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_policies_subnav")) {
	function layer7_render_policies_subnav($active = "")
	{
		echo "<!-- L7HC_SUBNAV " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("l7hc_ndpi")) {
	function l7hc_ndpi($by_cat, $protocols = null)
	{
		if ($protocols === null) {
			$protocols = array();
			foreach ($by_cat as $list) {
				if (is_array($list)) {
					foreach ($list as $p) {
						$protocols[] = $p;
					}
				}
			}
		}
		return array(
			"protocols" => $protocols,
			"categories" => array_keys($by_cat),
			"protocols_by_category" => $by_cat,
		);
	}
}

if (!function_exists("l7hc_fixture_catalog_472")) {
	/**
	 * Fixture V3 Categories — 472 protocolos Proto001..Proto472 em 20 categorias Cat01..Cat20.
	 * Não é o catálogo nDPI do appliance; espelha harness-categories-view/run.php.
	 */
	function l7hc_fixture_catalog_472()
	{
		static $cached = null;
		if ($cached !== null) {
			return $cached;
		}
		$by_cat = array();
		$all = array();
		for ($i = 1; $i <= 472; $i++) {
			$cat = "Cat" . sprintf("%02d", (int)(($i - 1) / 24) + 1);
			$proto = "Proto" . sprintf("%03d", $i);
			if (!isset($by_cat[$cat])) {
				$by_cat[$cat] = array();
			}
			$by_cat[$cat][] = $proto;
			$all[] = $proto;
		}
		$cached = l7hc_ndpi($by_cat, $all);
		return $cached;
	}
}

if (!function_exists("l7hc_view_source")) {
	function l7hc_view_source()
	{
		static $raw = null;
		if ($raw === null) {
			$loaded = file_get_contents(L7HC_CATEGORIES);
			if (!is_string($loaded) || $loaded === "") {
				fwrite(STDERR, "FAIL nao foi possivel ler a view\n");
				exit(1);
			}
			if (strncmp($loaded, "<?php", 5) === 0) {
				$loaded = substr($loaded, 5);
			}
			$loaded = preg_replace('/require_once\s*\([^)]+\);/', "/* require stubbed */", $loaded);
			$loaded = str_replace('include("head.inc");', 'echo "<!-- L7HC_HEAD -->\n";', $loaded);
			$loaded = str_replace('include("foot.inc");', 'echo "<!-- L7HC_FOOT -->\n";', $loaded);
			$raw = $loaded;
		}
		return $raw;
	}
}

if (!function_exists("l7hc_render")) {
	function l7hc_render($ndpi)
	{
		$GLOBALS["l7hc_ndpi"] = $ndpi;
		ob_start();
		eval(l7hc_view_source());
		return ob_get_clean();
	}
}
