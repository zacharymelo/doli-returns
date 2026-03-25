<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/shipment_lines.php
 * \ingroup returnmgmt
 * \brief   Returns JSON list of shipment line items for a customer.
 *          Each row represents a shipped product (with batch/serial if tracked)
 *          and the linked sales order.
 *
 * GET params:
 *   socid  (int, required) — customer (fk_soc) to scope results to
 *
 * Schema reference (verified against Dolibarr core):
 *   llx_expeditiondet:
 *     fk_expedition, fk_product (nullable), fk_element (order id),
 *     fk_elementdet (order line id), element_type (default 'commande'), qty
 *   llx_expeditiondet_batch:
 *     fk_expeditiondet, batch (varchar — the serial/lot string), qty
 *   llx_expedition:
 *     rowid, ref, fk_soc, fk_statut, entity
 *   Order link: element_element where sourcetype='commande', targettype='shipping'
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

// Shipment lines with product info and linked order via element_element.
// batch/serial comes from expeditiondet_batch.batch (varchar), NOT from product_lot.
$sql  = "SELECT ed.rowid AS line_id, ed.qty,";
$sql .= " e.rowid AS expedition_id, e.ref AS expedition_ref,";
$sql .= " p.rowid AS fk_product, p.ref AS product_ref, p.label AS product_label,";
$sql .= " edb.batch AS serial_number,";
$sql .= " c.rowid AS fk_commande, c.ref AS commande_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
// Order link: commande → shipping via element_element
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element el ON el.fk_target = e.rowid AND el.targettype = 'shipping' AND el.sourcetype = 'commande'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = el.fk_source";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " ORDER BY e.ref DESC, ed.rowid ASC";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$lines[] = array(
			'line_id'        => (int) $obj->line_id,
			'expedition_id'  => (int) $obj->expedition_id,
			'expedition_ref' => $obj->expedition_ref,
			'fk_product'     => (int) $obj->fk_product,
			'product_ref'    => $obj->product_ref ? $obj->product_ref : '',
			'product_label'  => $obj->product_label ? $obj->product_label : '',
			'qty'            => (float) $obj->qty,
			'serial_number'  => $obj->serial_number ? $obj->serial_number : '',
			'fk_commande'    => $obj->fk_commande ? (int) $obj->fk_commande : null,
			'commande_ref'   => $obj->commande_ref ? $obj->commande_ref : '',
		);
	}
}

header('Content-Type: application/json');
print json_encode($lines);
