<?php
/**
 * Generate a PDF
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */


namespace OpenTHC\POS\Controller\API\B2C;

use Edoceo\Radix\ULID;

class Receipt extends \OpenTHC\POS\Controller\API\Base
{
	/**
	 *
	 */
	function __invoke($REQ, $RES, $ARG)
	{

		$dbc = _dbc($_SESSION['dsn']);
		$b2c = new \OpenTHC\POS\B2C\Sale($dbc, $_GET['s']);
		$b2c_item_list = $S->getItems();
		foreach ($b2c_item_list as $i => $b2ci) {
			$b2c_item_list[$i]['Inventory'] = new \OpenTHC\POS\Inventory($dbc, $b2ci['inventory_id']);
		}

	}

	/**
	 * Generate a Preview Document
	 */
	function preview($REQ, $RES, $ARG)
	{

		$this->auth_parse();

		$b2c = [
			'id' => 'PREVIEW',
			'created_at' => '1969-04-20T16:20:00 America/Los_Angeles',
			'base_price' => 0,
			'full_price' => 0,
			'item_count' => 0,
			'meta' => [
				'cash_incoming' => 0,
				'cash_outgoing' => 0,
			]
		];

		$max = rand(1, 10);
		$b2c_item_list = [];
		for ($idx=0; $idx<$max; $idx++) {

			$c = rand(1, 10);
			$p = rand(200, 10000) / 100;

			$b2c['base_price'] += ($c * $p);

			$b2c_item_list[] = [
				'Inventory' => [
					'guid' => ULID::create()
				],
				'Inventory' => [],
				'Product' => [
					'name' => 'Text/Product'
				],
				'Variety' => [
					'name' => 'Text/Variety',
				],
				'unit_count' => $c,
				'unit_price' => $p,
				'base_price' => ($c * $p),
				'full_price' => ($c * $p),
			];
		}

		$dbc = _dbc($this->Company['dsn']);

		$pdf = new \OpenTHC\POS\PDF\Receipt();
		$pdf->setCompany( new \OpenTHC\Company($dbc, $this->Company ));
		$pdf->setLicense( new \OpenTHC\Company($dbc, $this->License ));
		// Options
		$pdf->head_text = json_decode($dbc->fetchOne('SELECT val FROM base_option WHERE key = :k', [ ':k' => sprintf('/%s/receipt/head', $this->License['id']) ]));
		$pdf->foot_text = json_decode($dbc->fetchOne('SELECT val FROM base_option WHERE key = :k', [ ':k' => sprintf('/%s/receipt/foot', $this->License['id']) ]));
		$pdf->foot_link = json_decode($dbc->fetchOne('SELECT val FROM base_option WHERE key = :k', [ ':k' => sprintf('/%s/receipt/link', $this->License['id']) ]));
		$pdf->tail_text = json_decode($dbc->fetchOne('SELECT val FROM base_option WHERE key = :k', [ ':k' => sprintf('/%s/receipt/tail', $this->License['id']) ]));

		$pdf->setSale($b2c);
		$pdf->setItems($b2c_item_list);
		$pdf->render();
		$name = sprintf('Receipt_%s.pdf', $b2c['id']);
		$pdf->Output($name, 'I');

		exit(0);

	}
}
