<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/shipment_lines.php
 * \ingroup returnmgmt
 * \brief   Returns JSON list of shipment line items for a customer.
 *          Each row represents a shipped product (with serial/lot if tracked)
 *          and the linked sales order if available.
 *
 * GET params:
 *   socid  (int, required) — customer (fk_soc) to scope results to
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('returnmgmt', 'returnrequest', 'read')) {
	http_response_code(403);
	exit;
}

$socid = (int) GETPOST('socid', 'int');
if ($socid <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

$lines = array();

// Main query: shipment lines with product info
// Left join to expeditiondet_batch + product_lot for serial/lot tracking
$sql  = "SELECT ed.rowid AS line_id, ed.qty,";
$sql .= " e.rowid AS expedition_id, e.ref AS expedition_ref,";
$sql .= " p.rowid AS fk_product, p.ref AS product_ref, p.label AS product_label,";
$sql .= " pl.batch AS serial_number";
$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_lot pl ON pl.rowid = edb.fk_lot";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " AND p.entity IN (".getEntity('product').")";
$sql .= " ORDER BY e.ref DESC, ed.rowid ASC";

$resql = $db->query($sql);
if ($resql) {
	// Collect all expedition IDs to batch-fetch linked orders
	$expedition_ids = array();
	$rows = array();
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		$expedition_ids[$obj->expedition_id] = true;
	}

	// Batch-fetch linked sales orders via element_element
	$order_map = array(); // expedition_id => {fk_commande, commande_ref}
	if (!empty($expedition_ids)) {
		$ids_str = implode(',', array_map('intval', array_keys($expedition_ids)));
		$sql_orders  = "SELECT ee.fk_source AS expedition_id, c.rowid AS fk_commande, c.ref AS commande_ref";
		$sql_orders .= " FROM ".MAIN_DB_PREFIX."element_element ee";
		$sql_orders .= " INNER JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = ee.fk_target";
		$sql_orders .= " WHERE ee.fk_source IN (".$ids_str.")";
		$sql_orders .= " AND ee.sourcetype = 'shipping'";
		$sql_orders .= " AND ee.targettype = 'commande'";

		$resql_o = $db->query($sql_orders);
		if ($resql_o) {
			while ($oo = $db->fetch_object($resql_o)) {
				$order_map[$oo->expedition_id] = array(
					'fk_commande' => (int) $oo->fk_commande,
					'commande_ref' => $oo->commande_ref,
				);
			}
		}

		// Also try reverse direction (commande → shipping)
		$sql_orders2  = "SELECT ee.fk_target AS expedition_id, c.rowid AS fk_commande, c.ref AS commande_ref";
		$sql_orders2 .= " FROM ".MAIN_DB_PREFIX."element_element ee";
		$sql_orders2 .= " INNER JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = ee.fk_source";
		$sql_orders2 .= " WHERE ee.fk_target IN (".$ids_str.")";
		$sql_orders2 .= " AND ee.sourcetype = 'commande'";
		$sql_orders2 .= " AND ee.targettype = 'shipping'";

		$resql_o2 = $db->query($sql_orders2);
		if ($resql_o2) {
			while ($oo2 = $db->fetch_object($resql_o2)) {
				if (!isset($order_map[$oo2->expedition_id])) {
					$order_map[$oo2->expedition_id] = array(
						'fk_commande' => (int) $oo2->fk_commande,
						'commande_ref' => $oo2->commande_ref,
					);
				}
			}
		}
	}

	// Build output
	foreach ($rows as $obj) {
		$order_info = isset($order_map[$obj->expedition_id]) ? $order_map[$obj->expedition_id] : null;
		$lines[] = array(
			'line_id'        => (int) $obj->line_id,
			'expedition_id'  => (int) $obj->expedition_id,
			'expedition_ref' => $obj->expedition_ref,
			'fk_product'     => (int) $obj->fk_product,
			'product_ref'    => $obj->product_ref,
			'product_label'  => $obj->product_label,
			'qty'            => (float) $obj->qty,
			'serial_number'  => $obj->serial_number,
			'fk_commande'    => $order_info ? $order_info['fk_commande'] : null,
			'commande_ref'   => $order_info ? $order_info['commande_ref'] : null,
		);
	}
}

header('Content-Type: application/json');
print json_encode($lines);
