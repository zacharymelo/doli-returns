<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/customer_shipments.php
 * \ingroup returnmgmt
 * \brief   Returns JSON list of validated shipments for a customer.
 *          Used by standalone create form to let user pick a shipment.
 *
 * GET params:
 *   socid  (int, required) — customer (fk_soc)
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

$shipments = array();

// Validated shipments for this customer, with line count and linked order ref
$sql  = "SELECT e.rowid, e.ref, e.date_delivery, e.fk_statut,";
$sql .= " (SELECT COUNT(ed.rowid) FROM ".MAIN_DB_PREFIX."expeditiondet ed WHERE ed.fk_expedition = e.rowid) AS line_count,";
$sql .= " c.ref AS commande_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."expedition e";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element el ON el.fk_target = e.rowid AND el.targettype = 'shipping' AND el.sourcetype = 'commande'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = el.fk_source";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " ORDER BY e.ref DESC";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$shipments[] = array(
			'id'            => (int) $obj->rowid,
			'ref'           => $obj->ref,
			'date_delivery' => $obj->date_delivery ? dol_print_date($db->jdate($obj->date_delivery), 'day') : '',
			'line_count'    => (int) $obj->line_count,
			'commande_ref'  => $obj->commande_ref ? $obj->commande_ref : '',
		);
	}
}

header('Content-Type: application/json');
print json_encode($shipments);
