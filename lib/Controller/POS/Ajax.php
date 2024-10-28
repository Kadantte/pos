<?php
/**
 * POS AJAX Handler
 * Has some legacy functional level stuff that should be cleaned up
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

namespace OpenTHC\POS\Controller\POS;

class Ajax extends \OpenTHC\Controller\Base
{
	/**
	 *
	 */
	function __invoke($REQ, $RES, $ARG)
	{
		session_write_close();

		switch ($_SERVER['REQUEST_METHOD']) {
		case 'DELETE':

			// Only the HOLD ID DELETE
			if (preg_match('/^01\w{24}$/', $_GET['id'])) {
				$dbc = $this->_container->DB;
				$dbc->query('DELETE FROM b2c_sale_hold WHERE id = :pk', [ ':pk' => $_GET['id'] ]);
			}

			return $RES->withJSON([
				'data' => null,
				'meta' => [],
			]);

		}

		switch ($_GET['a']) {
		case 'hold-list':

			$res = $this->_container->DB->fetchAll('SELECT * FROM b2c_sale_hold ORDER BY created_at');
			if (empty($res)) {
				_exit_html('<h4>No Holds</h4>');
			}

			ob_start();
			foreach ($res as $rec) {

				$rec['meta'] = json_decode($rec['meta'], true);

				if (empty($rec['meta']['name'])) {
					$rec['meta']['name'] = '-unknown-';
				}

				echo '<div class="sale-hold-list-item" style="display: flex; justify-content: space-between; margin-bottom: 0.50rem;">';
				printf('<h4><a href="#%s">%s</a></h4>', $rec['id'], $rec['meta']['name']);
				printf('<button class="btn btn-sm btn-danger" type="button" value="%s"><i class="fas fa-times"></i></button>', $rec['id']);
				echo '</div>';
			}

			$html = ob_get_clean();
			_exit_html($html);

		case 'hold-open':
			return $this->hold_open($RES);
		case 'ping':

			if (!empty($_SESSION['pos-terminal-id'])) {
				$k = "pos-terminal-{$_SESSION['pos-terminal-id']}";
				$this->_container->Redis->hset($k, 'ping', $_SERVER['REQUEST_TIME']);
			}

			return $RES->withJSON([
				'data' => null,
				'meta' => [],
			]);

			break;

		case 'push':

			$k = sprintf('pos-terminal-card', $_SESSION['pos-terminal-id']);
			$this->_container->Redis->del($k);
			$x = $this->_container->Redis->set($k, json_encode($_POST));

			return $RES->withJSON(array(
				'data' => null,
				'meta' => [],
			));

			break;

		case 'search':
			return $this->_search($RES);
			break;
		}

		__old_ajax_shit($RES);

	}

	/**
	 * Load the Hold Data
	 */
	function hold_open($RES)
	{
		$ret_data = [];

		$dbc = $this->_container->DB;

		$rec = $dbc->fetchRow('SELECT * FROM b2c_sale_hold WHERE id = :pk', [
			':pk' => $_GET['id']
		]);

		if (empty($rec)) {
			__exit_json($ret_data, 404);
		}
		if (empty($rec['type'])) {
			$rec['type'] = 'general';
		}

		$rec['meta'] = json_decode($rec['meta'], true);

		switch ($rec['type']) {
			case 'general':
			case 'inside':
				foreach ($rec['meta'] as $k => $v) {
					if (preg_match('/^item\-(\w+)\-unit\-count$/', $k, $m)) {

						$sql = <<<SQL
						SELECT inventory_full.*
						FROM inventory_full
						WHERE license_id = :l0
						AND stat = 200
						AND qty > 0
						AND (sell IS NOT NULL AND sell > 0)
						AND inventory_id = :pk
						SQL;

						$inv = $dbc->fetchRow($sql, [
							':l0' => $_SESSION['License']['id'],
							':pk' => $m[1]
						]);

						$ret_data[] = [
							'id' => $inv['id'],
							'qty' => $v,  // @deprecated
							'unit_count' => $v,
							'unit_price' => $inv['sell'],
							'product' => [
								'id' => $inv['product_id'],
								'name' => $inv['product_name']
							],
							'package' => [
								'id' => '',
								'name' => sprintf('%s %s', rtrim($inv['package_unit_qom'], '0'), $inv['package_unit_uom'])
							]
						];
					}
				}

				break;

			case 'online':

				$b2c = $rec['meta'];
				foreach ($b2c['item_list'] as $b2c_item) {

					$sql = <<<SQL
					SELECT inventory_full.*
					FROM inventory_full
					WHERE license_id = :l0
						AND stat = 200
						AND qty > 0
						AND (sell IS NOT NULL AND sell > 0)
						AND inventory_id = :pk
					SQL;

					$inv = $dbc->fetchRow($sql, [
						':l0' => $_SESSION['License']['id'],
						':pk' => $b2c_item['inventory_id']
					]);

					// Response Data
					$ret_data[] = [
						'id' => $b2c_item['inventory_id']
						, 'name' => sprintf('%s: %s / %s', substr($rec['guid'], -4), $b2c_item['product']['name'], $b2c_item['variety']['name'])
						, 'qty' => $b2c_item['qty']  // @deprecated
						, 'unit_count' => $b2c_item['qty']
						, 'unit_price' => $inv['sell']
						, 'product' => $b2c_item['product']
						, 'package' => [
							'id' => ''
							, 'name' => sprintf('%s %s', rtrim($inv['package_unit_qom'], '0'), $inv['package_unit_uom'])
						]
						, 'variety' => $b2c_item['variety']
					];
				}

				break;
		}

		__exit_json([
			'data' => $ret_data
			, 'meta' => [],
		]);

	}

	/**
	 *
	 */
	function _search($RES)
	{
		$q = trim($_GET['q']);

		// Remove the '~' prefix (should we add others and trim?)
		switch (substr($q, 0, 1)) {
		case '~':
			$q = substr($q, 1);
		}

		// Starts or Ends with the Code
		// $res = \OpenTHC\POS\POS::listInventory("%{$q}%");
		$sql = <<<SQL
		SELECT inventory_full.*
		FROM inventory_full
		WHERE license_id = :l0
			AND stat = 200
			AND qty > 0
			AND (sell IS NOT NULL AND sell > 0)
		SQL;
		$arg = array(
			':l0' => $_SESSION['License']['id'],
		);

		if (!empty($q)) {
			$sql.= ' AND guid LIKE :q1';
			// $arg[':q0'] = $q;
			$arg[':q1'] = sprintf('%%%s%%', $q);
		}
		$sql.= ' ORDER BY product_type_name, product_name, variety_name';
		$res = $this->_container->DB->fetchAll($sql, $arg);

		switch (count($res)) {
		case 0:
			_draw_ajax_search_error();
			return $RES->withStatus(404);
			exit(0);
		default:

			// _draw_inventory_grid($res);
			_draw_inventory_list($res);

			if (1 == count($res)) {

				$rec = $res[0];

				// Was to auto-add when a single item was found
				//echo '<script>';
				////echo 'addSaleItem(document.getElementById("inv-item-' . $rec['id'] . '"));';
				////echo '$("#barcode-auto-complete").empty();';
				////echo '$("#barcode-auto-complete").hide();';
				////echo '$("#barcode-input").val("");';
				////echo 'searchInventory("");';
				//echo '</script>';

			}
		}

	}
}


function __old_ajax_shit($RES)
{
	throw new \Exception('@deprecated [CPA-122]');

	switch ($_GET['a']) {
	case 'discount-list':

		echo '<div style="padding: 8px;">';
		echo '<div style="border: 2px inset #444; padding: 4px;">';
		echo '<h3 style="border-bottom: 1px solid #999;">Discount A</h3>';
		echo '<h3 style="border-bottom: 1px solid #999;">Discount B</h3>';
		echo '<h3 style="border-bottom: 1px solid #999;">Discount C</h3>';

		echo '</div>';
		echo '</div>';
		break;

	}

	exit(0);

}

function _draw_ajax_search_error()
{
?>
<div id="alert-lookup">
	<h4 class="alert alert-warning">Item not found</h4>
</div>
<script>
$(function() {
	setTimeout(function() {
		$('#barcode-input').val('');
	}, 750);
	setTimeout(function() {
		$('#barcode-auto-complete').empty();
		$('#barcode-auto-complete').hide();
	}, 3210);
});
</script>
<?php
}


/**
 * Draw in a Grid Layout
 * Four Columns, Picture,
 */
function _draw_inventory_grid($res)
{
	echo '<div class="pos-item-grid">';

	echo '<div class="pos-item-grid-head text-center">';
	echo '<div class="btn-group btn-group-sm">';
	echo '<button class="btn btn-outline-secondary">Flower</button>';
	echo '<button class="btn btn-outline-secondary">Concentrates</button>';
	echo '<button class="btn btn-outline-secondary">Edibles</button>';
	echo '</div>';
	echo '</div>';

	echo '<div class="pos-item-grid-body">';
	foreach ($res as $rec) {

		//$I = new Inventory($rec);
		$rec['name'] = $rec['product_name'] . '/' . $rec['variety_name'];

		// if ($pt_x != $rec['product_type_id']) {
		// 	echo '<h3>' . h($rec['product_type_name']) . '</h3>';
		// }
		// $pt_x = $rec['product_type_id'];

		echo '<div class="pos-item-grid-item"';
		echo ' data-id="' . $rec['id'] . '"';
		echo ' data-name="' . substr($rec['guid'], -4) . ': ' . __h($rec['name']) . '"';
		echo ' data-count="' . sprintf('%0.2f', $rec['unit_onhand']) . '"';
		echo ' data-weight="' . sprintf('%0.2f', $rec['unit_weight']) . '"';
		echo ' data-price="' . sprintf('%0.2f', $rec['sell']) . '"';
		echo ' id="inv-item-' . $rec['id'] . '">';

		// Header Bar
		echo '<div>';
		echo '<h4>';
		echo substr($rec['guid'], -4);
		echo ': ';
		echo __h($rec['name']);
		echo '</h4>';
		echo '</div>';

		// Image
		echo '<div>';
		if (!empty($rec['product_ulid'])) {
			echo sprintf('<img class="img-fluid" src="/img/product/%s.png">', $rec['product_ulid']);
		} else {
			echo '<div class="text-center" style="font-size:100px;"><i class="fas fa-cannabis"></i></div>';
		}

		echo '</div>';

		// Details
		echo '<div style="display:flex;">';

			echo '<div style="flex: 1 1 60%;">';
			switch (sprintf('%s/%s', $rec['product_type_mode'], $rec['product_type_unit'])) {
			case 'each/ea':
				echo '<h4 style="text-align:right;">' . sprintf('%0.2f', $rec['unit_weight']) . ' ea</h4>';
				break;
			case 'each/g':
				echo '<h4 style="text-align:right;">' . sprintf('%0.2f', $rec['unit_weight']) . ' g</h4>';
				break;
			default:
				echo '<h4 style="text-align:right;">' . sprintf('%d * %0.2f g', $rec['unit_onhand'], $rec['unit_weight']) . '/' . $rec['product_name'] . '</h4>';
				break;
			}
			echo '</div>';

			// Price
			echo '<div style="flex: 1 1 40%;">';
			echo '<h4 style="text-align:right;">$' . number_format($rec['sell'], 2) . '</h4>';
			echo '</div>';

		echo '</div>';

		echo '</div>';

	}

	echo '</div>'; // /.pos-item-grid-grid
	echo '</div>'; // /.pos-item-grid

}

function _draw_inventory_list($res)
{
	// Default Mode -- Draw All Inventory
	foreach ($res as $rec) {

		//$I = new Inventory($rec);
		$rec['name'] = sprintf('%s / %s', $rec['product_name'], $rec['variety_name']);
		$rec['name'] = trim($rec['name'], '/');

		if ($pt_x != $rec['product_type_id']) {
			echo '<h3>' . __h($rec['product_type_name']) . '</h3>';
		}
		$pt_x = $rec['product_type_id'];

		echo '<div class="inv-item row"';
		echo ' data-id="' . $rec['id'] . '"';
		echo ' data-name="' . substr($rec['guid'], -4) . ': ' . __h($rec['name']) . '"';
		echo ' data-count="' . sprintf('%d', $rec['qty']) . '"';
		echo ' data-weight="' . sprintf('%0.1f', $rec['package_unit_qom']) . '"';
		echo ' data-price="' . sprintf('%0.2f', $rec['sell']) . '"';
		echo ' id="inv-item-' . $rec['id'] . '">';

		echo '<div class="col-md-7">';
		echo '<h4>';
		printf('<code>%s</code> %s', substr($rec['guid'], -4), __h($rec['name']));
		printf(' <small>[%d]</small>', $rec['qty']);
		echo '</h4>';
		echo '</div>';

		echo '<div class="col-md-2">';
		switch ($rec['product_type_mode']) {
		case 'bulk':
			printf('<h4 style="text-align:right;">%0.2f %s</h4>', $rec['package_unit_qom'], $rec['package_unit_uom']);
			break;
		case 'each':
			printf('<h4 style="text-align:right;">%0.2f %s</h4>', $rec['package_unit_qom'], $rec['package_unit_uom']);
			break;
		}
		echo '</div>';

		echo '<div class="col-md-3">';
		echo '<h4 style="text-align:right;">$' . number_format($rec['sell'], 2) . '</h4>';
		echo '</div>';

		echo '</div>';

	}
}
