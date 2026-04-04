<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/shipment_lines.php
 * \ingroup customerreturn
 * \brief   Returns JSON list of shipment line items for a specific shipment.
 *          Includes qty ordered, qty shipped, qty already returned, batch/serial info.
 *
 * GET params:
 *   expedition_id  (int, required) -- shipment ID to load lines for
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('customerreturn', 'customerreturn', 'read')) {
	http_response_code(403);
	exit;
}

$expedition_id = (int) GETPOST('expedition_id', 'int');
if ($expedition_id <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

$lines = array();

// Shipment lines with product info, order qty, and qty already returned
$sql  = "SELECT ed.rowid AS line_id, ed.qty AS qty_shipped, ed.fk_elementdet,";
$sql .= " p.rowid AS fk_product, p.ref AS product_ref, p.label AS product_label,";
$sql .= " cd.qty AS qty_ordered,";
$sql .= " edb.batch AS serial_number,";
// Qty already returned: sum from return lines linked to this expeditiondet,
// excluding returns in DRAFT (0) status
$sql .= " COALESCE((SELECT SUM(rl.qty) FROM ".MAIN_DB_PREFIX."customer_return_line rl";
$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."customer_return rr ON rr.rowid = rl.fk_customer_return";
$sql .= "   WHERE rl.fk_expeditiondet = ed.rowid";
$sql .= "   AND rr.status NOT IN (0)), 0) AS qty_already_returned";
$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.rowid = ed.fk_elementdet";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
$sql .= " WHERE ed.fk_expedition = ".((int) $expedition_id);
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " ORDER BY ed.rowid ASC";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$qty_returnable = max(0, (float) $obj->qty_shipped - (float) $obj->qty_already_returned);
		$lines[] = array(
			'line_id'              => (int) $obj->line_id,
			'fk_product'           => (int) $obj->fk_product,
			'product_ref'          => $obj->product_ref ? $obj->product_ref : '',
			'product_label'        => $obj->product_label ? $obj->product_label : '',
			'qty_ordered'          => $obj->qty_ordered !== null ? (float) $obj->qty_ordered : null,
			'qty_shipped'          => (float) $obj->qty_shipped,
			'qty_already_returned' => (float) $obj->qty_already_returned,
			'qty_returnable'       => $qty_returnable,
			'serial_number'        => $obj->serial_number ? $obj->serial_number : '',
			'fk_commandedet'       => $obj->fk_elementdet ? (int) $obj->fk_elementdet : null,
		);
	}
}

header('Content-Type: application/json');
print json_encode($lines);
