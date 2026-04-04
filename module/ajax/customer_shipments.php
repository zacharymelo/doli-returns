<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/customer_shipments.php
 * \ingroup customerreturn
 * \brief   Returns JSON list of validated shipments for a customer,
 *          including status labels and line item product summaries.
 *
 * GET params:
 *   socid  (int, required) -- customer (fk_soc)
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

$socid = (int) GETPOST('socid', 'int');
if ($socid <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';

// Status label mapping
$status_labels = array(
	0 => $langs->transnoentitiesnoconv('StatusSendingDraft'),
	1 => $langs->transnoentitiesnoconv('StatusSendingValidated'),
	2 => $langs->transnoentitiesnoconv('StatusSendingProcessed'),
	3 => $langs->transnoentitiesnoconv('StatusSendingCanceled'),
);

$shipments = array();

// Validated shipments for this customer
$sql  = "SELECT e.rowid, e.ref, e.date_delivery, e.date_expedition, e.fk_statut";
$sql .= " FROM ".MAIN_DB_PREFIX."expedition e";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " ORDER BY e.ref DESC";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$exp_id = (int) $obj->rowid;

		// Get line item product summaries
		$sql_lines = "SELECT p.ref as product_ref, ed.qty";
		$sql_lines .= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
		$sql_lines .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = ed.fk_product";
		$sql_lines .= " WHERE ed.fk_expedition = ".$exp_id;
		$sql_lines .= " ORDER BY ed.rowid ASC";

		$line_summaries = array();
		$resql_l = $db->query($sql_lines);
		if ($resql_l) {
			while ($obj_l = $db->fetch_object($resql_l)) {
				$pref = $obj_l->product_ref ?: '?';
				$qty  = (int) $obj_l->qty;
				$line_summaries[] = $pref.($qty > 1 ? ' x'.$qty : '');
			}
		}

		$date = $obj->date_expedition ?: $obj->date_delivery;
		$status_int = (int) $obj->fk_statut;

		$shipments[] = array(
			'id'           => $exp_id,
			'ref'          => $obj->ref,
			'url'          => DOL_URL_ROOT.'/expedition/card.php?id='.$exp_id,
			'date'         => $date ? dol_print_date($db->jdate($date), 'day') : '',
			'status_label' => isset($status_labels[$status_int]) ? $status_labels[$status_int] : $status_int,
			'nb_lines'     => count($line_summaries),
			'lines_summary'=> implode(', ', $line_summaries),
		);
	}
}

header('Content-Type: application/json');
print json_encode($shipments);
