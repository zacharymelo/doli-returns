<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

// Try multi-depth includes for main.inc.php
$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
dol_include_once('/returnmgmt/class/returnrequest.class.php');
dol_include_once('/returnmgmt/lib/returnmgmt.lib.php');

$langs->loadLangs(array('returnmgmt@returnmgmt', 'companies', 'products', 'other', 'sendings', 'orders', 'bills'));

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new ReturnRequest($db);

// Permissions
$permread     = $user->hasRight('returnmgmt', 'returnrequest', 'read');
$permwrite    = $user->hasRight('returnmgmt', 'returnrequest', 'write');
$permdelete   = $user->hasRight('returnmgmt', 'returnrequest', 'delete');
$permapprove  = $user->hasRight('returnmgmt', 'returnrequest', 'approve');
$permclose    = $user->hasRight('returnmgmt', 'returnrequest', 'close');

if (!$permread) {
	accessforbidden();
}

// Fetch object
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

// Initialize hook manager
$hookmanager->initHooks(array('returnrequestcard', 'globalcard'));

$form = new Form($db);
$formcompany = new FormCompany($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);


/*
 * ACTIONS
 */

if ($cancel) {
	if (!empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
	$action = '';
}

$reshook = $hookmanager->executeHooks('doActions', array(), $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

// Create
if ($action == 'add' && $permwrite) {
	$object->fk_soc          = GETPOSTINT('fk_soc');
	$object->return_reason    = GETPOST('return_reason', 'alpha');
	$object->resolution_type  = GETPOST('resolution_type', 'alpha');
	$object->fk_warehouse     = GETPOSTINT('fk_warehouse');
	$object->fk_user_assigned = GETPOSTINT('fk_user_assigned');
	$object->note_private     = GETPOST('note_private', 'restricthtml');
	$object->note_public      = GETPOST('note_public', 'restricthtml');
	$object->fk_expedition    = GETPOSTINT('fk_expedition');
	$object->label            = GETPOST('label', 'alpha');
	$object->date_return      = dol_mktime(0, 0, 0, GETPOSTINT('date_returnmonth'), GETPOSTINT('date_returnday'), GETPOSTINT('date_returnyear'));

	$toselect = GETPOST('toselect', 'array');

	if (empty($object->fk_soc)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesaliases('Customer')), null, 'errors');
		$action = 'create';
	} elseif (empty($toselect)) {
		setEventMessages($langs->trans('SelectAtLeastOneLine'), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			// Add selected shipment lines as return request lines
			foreach ($toselect as $line_id) {
				$line_id = (int) $line_id;
				$qty = GETPOST('return_qty_'.$line_id, 'int');
				if ($qty <= 0) { $qty = 1; }
				$fk_product = GETPOSTINT('fk_product_'.$line_id);
				$serial = GETPOST('serial_number_'.$line_id, 'alpha');
				$description = GETPOST('product_label_'.$line_id, 'alpha');
				$fk_commandedet = GETPOSTINT('fk_commandedet_'.$line_id);
				$fk_entrepot = GETPOSTINT('fk_entrepot_'.$line_id);
				$comment = GETPOST('comment_'.$line_id, 'restricthtml');

				$object->addLine(
					$fk_product, $qty, $description, $serial,
					0, 0,
					$object->fk_expedition, $line_id, $fk_commandedet,
					$fk_entrepot, $comment
				);
			}

			// Optional warrantysvc integration: link to source service request
			$from_svcrequest = GETPOSTINT('from_svcrequest');
			if ($from_svcrequest > 0 && isModEnabled('warrantysvc')) {
				$object->add_object_linked('svcrequest', $from_svcrequest);
			}

			// Link to expedition
			if ($object->fk_expedition > 0) {
				$object->add_object_linked('shipping', $object->fk_expedition);
			}

			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$result);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// Update
if ($action == 'update' && $permwrite) {
	$object->fk_soc               = GETPOSTINT('fk_soc');
	$object->return_reason         = GETPOST('return_reason', 'alpha');
	$object->resolution_type       = GETPOST('resolution_type', 'alpha');
	$object->fk_warehouse          = GETPOSTINT('fk_warehouse');
	$object->fk_user_assigned      = GETPOSTINT('fk_user_assigned');
	$object->return_tracking       = GETPOST('return_tracking', 'alpha');
	$object->condition_on_receipt  = GETPOST('condition_on_receipt', 'alpha');
	$object->refund_amount         = GETPOST('refund_amount', 'alpha');
	$object->exchange_fk_product   = GETPOSTINT('exchange_fk_product');
	$object->exchange_serial_number = GETPOST('exchange_serial_number', 'alpha');
	$object->note_private          = GETPOST('note_private', 'restricthtml');
	$object->note_public           = GETPOST('note_public', 'restricthtml');
	$object->label                 = GETPOST('label', 'alpha');
	$object->date_return           = dol_mktime(0, 0, 0, GETPOSTINT('date_returnmonth'), GETPOSTINT('date_returnday'), GETPOSTINT('date_returnyear'));

	$result = $object->update($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

// Validate (submit): DRAFT -> PENDING
if ($action == 'confirm_validate' && $confirm == 'yes' && $permwrite) {
	$result = $object->validate($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Approve: PENDING -> APPROVED
if ($action == 'confirm_approve' && $confirm == 'yes' && $permapprove) {
	$result = $object->approve($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Reject: PENDING -> REJECTED
if ($action == 'confirm_reject' && $confirm == 'yes' && $permapprove) {
	$result = $object->reject($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Receive: APPROVED -> RECEIVED
if ($action == 'confirm_receive' && $confirm == 'yes' && $permwrite) {
	$object->return_tracking      = GETPOST('return_tracking', 'alpha');
	$object->condition_on_receipt = GETPOST('condition_on_receipt', 'alpha');
	if (!empty($object->return_tracking) || !empty($object->condition_on_receipt)) {
		$object->update($user, 1);
	}
	$result = $object->receive($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Process: RECEIVED -> PROCESSING
if ($action == 'confirm_process' && $confirm == 'yes' && $permwrite) {
	$object->resolution_type = GETPOST('resolution_type', 'alpha');
	if (!empty($object->resolution_type)) {
		$object->update($user, 1);
	}
	$result = $object->process($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Complete: PROCESSING -> COMPLETED
if ($action == 'confirm_complete' && $confirm == 'yes' && $permclose) {
	$object->refund_amount          = GETPOST('refund_amount', 'alpha');
	$object->exchange_fk_product    = GETPOSTINT('exchange_fk_product');
	$object->exchange_serial_number = GETPOST('exchange_serial_number', 'alpha');
	if ($object->refund_amount || $object->exchange_fk_product) {
		$object->update($user, 1);
	}
	$result = $object->complete($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Create credit note from completed refund return
if ($action == 'confirm_createcreditnote' && $confirm == 'yes' && $permclose) {
	$fk_facture = $object->getLinkedInvoice();
	if ($fk_facture > 0) {
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$facture_source = new Facture($db);
		$facture_source->fetch($fk_facture);
		$facture_source->fetch_lines();

		$creditnote = new Facture($db);
		$creditnote->socid = $object->fk_soc;
		$creditnote->type = Facture::TYPE_CREDIT_NOTE;
		$creditnote->fk_facture_source = $fk_facture;
		$creditnote->date = dol_now();
		$creditnote->note_public = $langs->trans('CreditNoteCreated', $object->ref);

		$result = $creditnote->create($user);
		if ($result > 0) {
			// Add lines from return request with original pricing from invoice
			foreach ($object->lines as $rline) {
				if ($rline->fk_product > 0) {
					// Find matching line in source invoice
					foreach ($facture_source->lines as $fline) {
						if ($fline->fk_product == $rline->fk_product) {
							$creditnote->addline(
								$fline->desc, $fline->subprice, $rline->qty,
								$fline->tva_tx, $fline->localtax1_tx, $fline->localtax2_tx,
								$fline->fk_product, $fline->remise_percent
							);
							break;
						}
					}
				}
			}
			$object->add_object_linked('facture', $creditnote->id);
			header("Location: ".DOL_URL_ROOT.'/compta/facture/card.php?facid='.$creditnote->id);
			exit;
		} else {
			setEventMessages($creditnote->error, $creditnote->errors, 'errors');
		}
	} else {
		setEventMessages($langs->trans('NoInvoiceLinked'), null, 'errors');
	}
}

// Delete
if ($action == 'confirm_delete' && $confirm == 'yes' && $permdelete) {
	$result = $object->delete($user);
	if ($result > 0) {
		header("Location: returnrequest_list.php");
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}


/*
 * VIEW
 */

$title = $langs->trans('ReturnRequest');
llxHeader('', $title);

// ---------- CREATE FORM ----------
if ($action == 'create') {
	// Optional warrantysvc integration: pre-fill from service request
	$from_svcrequest = GETPOSTINT('from_svcrequest');
	$prefill_fk_soc = GETPOSTINT('fk_soc');
	$fk_expedition = GETPOSTINT('fk_expedition');

	if ($from_svcrequest > 0 && isModEnabled('warrantysvc')) {
		dol_include_once('/warrantysvc/class/svcrequest.class.php');
		$sr = new SvcRequest($db);
		if ($sr->fetch($from_svcrequest) > 0) {
			if (empty($prefill_fk_soc)) { $prefill_fk_soc = $sr->fk_soc; }
		}
	}

	// ----- Entry A: From shipment card (fk_expedition provided) -----
	if ($fk_expedition > 0) {
		$expedition = new Expedition($db);
		$result = $expedition->fetch($fk_expedition);
		if ($result <= 0) {
			setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
			llxFooter();
			$db->close();
			exit;
		}

		$prefill_fk_soc = $expedition->socid;

		// Get linked order ref via element_element
		$order_ref = '';
		$order_url = '';
		$sql_order = "SELECT el.fk_source, c.ref as order_ref";
		$sql_order .= " FROM ".MAIN_DB_PREFIX."element_element as el";
		$sql_order .= " INNER JOIN ".MAIN_DB_PREFIX."commande as c ON c.rowid = el.fk_source";
		$sql_order .= " WHERE el.fk_target = ".((int) $fk_expedition);
		$sql_order .= " AND el.sourcetype = 'commande' AND el.targettype = 'shipping'";
		$sql_order .= " LIMIT 1";
		$resql_order = $db->query($sql_order);
		if ($resql_order && $db->num_rows($resql_order) > 0) {
			$obj_order = $db->fetch_object($resql_order);
			$order_ref = $obj_order->order_ref;
			$order_url = DOL_URL_ROOT.'/commande/card.php?id='.$obj_order->fk_source;
		}

		// Fetch company
		$soc = new Societe($db);
		$soc->fetch($prefill_fk_soc);

		print load_fiche_titre($langs->trans('NewReturnRequest'), '', 'object_technic');

		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="add">';
		print '<input type="hidden" name="fk_expedition" value="'.$fk_expedition.'">';
		print '<input type="hidden" name="fk_soc" value="'.$prefill_fk_soc.'">';
		if ($from_svcrequest > 0) {
			print '<input type="hidden" name="from_svcrequest" value="'.$from_svcrequest.'">';
		}
		if (!empty($backtopage)) {
			print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
		}

		print dol_get_fiche_head(array(), '');

		print '<table class="border centpercent tableforfieldcreate">';

		// Shipment Ref (read-only)
		print '<tr><td class="titlefieldcreate">'.$langs->trans('Shipment').'</td><td>';
		print $expedition->getNomUrl(1);
		print '</td></tr>';

		// Order Ref (read-only)
		if (!empty($order_ref)) {
			print '<tr><td>'.$langs->trans('Order').'</td><td>';
			print '<a href="'.$order_url.'">'.$order_ref.'</a>';
			print '</td></tr>';
		}

		// Company (read-only)
		print '<tr><td>'.$langs->trans('Customer').'</td><td>';
		print $soc->getNomUrl(1);
		print '</td></tr>';

		// Date Return
		print '<tr><td>'.$langs->trans('ReturnDate').'</td><td>';
		print $form->selectDate('', 'date_return', 0, 0, 1, '', 1, 1);
		print '</td></tr>';

		// Label
		print '<tr><td>'.$langs->trans('Label').'</td><td>';
		print '<input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('label', 'alpha')).'">';
		print '</td></tr>';

		// Return reason
		print '<tr><td>'.$langs->trans('ReturnReason').'</td><td>';
		$reasons = returnrequest_reason_types();
		print $form->selectarray('return_reason', $reasons, GETPOST('return_reason', 'alpha'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';

		// Resolution type
		print '<tr><td>'.$langs->trans('ResolutionType').'</td><td>';
		$resolutions = returnrequest_resolution_types();
		print $form->selectarray('resolution_type', $resolutions, GETPOST('resolution_type', 'alpha'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';

		// Notes
		print '<tr><td>'.$langs->trans('NotePublic').'</td><td>';
		$doleditor = new DolEditor('note_public', GETPOST('note_public', 'restricthtml'), '', 150, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '90%');
		$doleditor->Create();
		print '</td></tr>';

		print '<tr><td>'.$langs->trans('NotePrivate').'</td><td>';
		$doleditor = new DolEditor('note_private', GETPOST('note_private', 'restricthtml'), '', 150, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '90%');
		$doleditor->Create();
		print '</td></tr>';

		print '</table>';

		print dol_get_fiche_end();

		// ----- Line items table (server-side) -----
		$wh_default = getDolGlobalInt('RETURNMGMT_WAREHOUSE_DEFAULT');

		// Query expeditiondet lines for this shipment
		$sql_lines = "SELECT ed.rowid as line_id, ed.fk_product, ed.qty as qty_shipped,";
		$sql_lines .= " ed.fk_elementdet as fk_commandedet,";
		$sql_lines .= " p.ref as product_ref, p.label as product_label,";
		$sql_lines .= " cd.qty as qty_ordered,";
		$sql_lines .= " eb.batch as batch_number";
		$sql_lines .= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
		$sql_lines .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = ed.fk_product";
		$sql_lines .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.fk_elementdet";
		$sql_lines .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch as eb ON eb.fk_expeditiondet = ed.rowid";
		$sql_lines .= " WHERE ed.fk_expedition = ".((int) $fk_expedition);
		$sql_lines .= " ORDER BY ed.rowid ASC";

		$resql_lines = $db->query($sql_lines);
		if ($resql_lines) {
			$num_lines = $db->num_rows($resql_lines);

			// Pre-fetch already returned quantities
			$already_returned = array();
			$sql_ar = "SELECT rl.fk_expeditiondet, SUM(rl.qty) as qty_returned";
			$sql_ar .= " FROM ".MAIN_DB_PREFIX."returnmgmt_return_line as rl";
			$sql_ar .= " INNER JOIN ".MAIN_DB_PREFIX."returnmgmt_return as r ON r.rowid = rl.fk_returnmgmt_return";
			$sql_ar .= " WHERE rl.fk_expeditiondet IN (";
			$sql_ar .= "SELECT ed2.rowid FROM ".MAIN_DB_PREFIX."expeditiondet as ed2 WHERE ed2.fk_expedition = ".((int) $fk_expedition);
			$sql_ar .= ")";
			$sql_ar .= " AND r.status NOT IN (0, 9)";
			$sql_ar .= " GROUP BY rl.fk_expeditiondet";
			$resql_ar = $db->query($sql_ar);
			if ($resql_ar) {
				while ($obj_ar = $db->fetch_object($resql_ar)) {
					$already_returned[$obj_ar->fk_expeditiondet] = (float) $obj_ar->qty_returned;
				}
			}

			print '<br>';
			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans('Product').'</td>';
			print '<td class="right">'.$langs->trans('QtyOrdered').'</td>';
			print '<td class="right">'.$langs->trans('QtyShipped').'</td>';
			print '<td class="right">'.$langs->trans('QtyAlreadyReturned').'</td>';
			print '<td class="center">'.$langs->trans('ReturnQty').'</td>';
			print '<td>'.$langs->trans('Warehouse').'</td>';
			print '<td>'.$langs->trans('Comment').'</td>';
			print '<td>'.$langs->trans('LotSerial').'</td>';
			print '<td class="center">'.$langs->trans('Select').'</td>';
			print '</tr>';

			$i = 0;
			while ($i < $num_lines) {
				$obj_line = $db->fetch_object($resql_lines);
				$lid = $obj_line->line_id;
				$qty_ar = isset($already_returned[$lid]) ? $already_returned[$lid] : 0;
				$qty_returnable = max(0, $obj_line->qty_shipped - $qty_ar);

				$product_display = '';
				if ($obj_line->fk_product > 0 && !empty($obj_line->product_ref)) {
					$product_display = $obj_line->product_ref.' - '.$obj_line->product_label;
				}

				print '<tr class="oddeven">';

				// Product
				print '<td>';
				if ($obj_line->fk_product > 0) {
					$prodtmp = new Product($db);
					$prodtmp->fetch($obj_line->fk_product);
					print $prodtmp->getNomUrl(1).' - '.dol_escape_htmltag($obj_line->product_label);
				}
				print '</td>';

				// Qty Ordered
				print '<td class="right">'.($obj_line->qty_ordered > 0 ? $obj_line->qty_ordered : '').'</td>';

				// Qty Shipped
				print '<td class="right">'.$obj_line->qty_shipped.'</td>';

				// Qty Already Returned
				print '<td class="right">'.($qty_ar > 0 ? $qty_ar : '0').'</td>';

				// Return Qty
				print '<td class="center">';
				print '<input type="number" name="return_qty_'.$lid.'" class="flat width50 right return-qty" min="0" max="'.$qty_returnable.'" value="0" data-line="'.$lid.'">';
				print '</td>';

				// Warehouse
				print '<td>';
				if (isModEnabled('stock')) {
					print $formproduct->selectWarehouses($wh_default, 'fk_entrepot_'.$lid, '', 1);
				}
				print '</td>';

				// Comment
				print '<td>';
				print '<textarea name="comment_'.$lid.'" class="flat minwidth150" rows="1"></textarea>';
				print '</td>';

				// Lot/Serial
				print '<td>'.dol_escape_htmltag($obj_line->batch_number).'</td>';

				// Checkbox
				print '<td class="center">';
				print '<input type="checkbox" name="toselect[]" value="'.$lid.'" class="flat checkforselect" data-line="'.$lid.'">';
				print '</td>';

				// Hidden inputs
				print '<input type="hidden" name="fk_product_'.$lid.'" value="'.$obj_line->fk_product.'">';
				print '<input type="hidden" name="serial_number_'.$lid.'" value="'.dol_escape_htmltag($obj_line->batch_number).'">';
				print '<input type="hidden" name="product_label_'.$lid.'" value="'.dol_escape_htmltag($product_display).'">';
				print '<input type="hidden" name="fk_commandedet_'.$lid.'" value="'.$obj_line->fk_commandedet.'">';

				print '</tr>';
				$i++;
			}

			print '</table>';
			print '</div>';
		}

		print '<br>';
		print $form->buttonsSaveCancel('Create');
		print '</form>';

		// JavaScript: checkbox <-> qty sync
		print '<script>(function(){
	var container = document.querySelector(".div-table-responsive-no-min");
	if (!container) return;

	container.querySelectorAll(".checkforselect").forEach(function(cb){
		cb.addEventListener("change", function(){
			var lid = this.dataset.line;
			var qtyInput = document.querySelector("input[name=return_qty_" + lid + "]");
			if (this.checked && qtyInput && parseInt(qtyInput.value, 10) === 0) {
				qtyInput.value = qtyInput.max || 1;
			}
			if (!this.checked && qtyInput) {
				qtyInput.value = 0;
			}
			this.closest("tr").classList.toggle("highlight", this.checked);
		});
	});
	container.querySelectorAll(".return-qty").forEach(function(input){
		input.addEventListener("change", function(){
			var lid = this.dataset.line;
			var cb = document.querySelector("input.checkforselect[data-line=\"" + lid + "\"]");
			if (cb) {
				cb.checked = (parseInt(this.value, 10) > 0);
				cb.closest("tr").classList.toggle("highlight", cb.checked);
			}
		});
	});
})();</script>';

	} else {
		// ----- Entry B: Standalone (no fk_expedition) -----
		print load_fiche_titre($langs->trans('NewReturnRequest'), '', 'object_technic');

		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="add">';
		if ($from_svcrequest > 0) {
			print '<input type="hidden" name="from_svcrequest" value="'.$from_svcrequest.'">';
		}
		if (!empty($backtopage)) {
			print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
		}

		print dol_get_fiche_head(array(), '');

		print '<table class="border centpercent tableforfieldcreate">';

		// Customer
		print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Customer').'</td><td>';
		print $form->select_company($prefill_fk_soc, 'fk_soc', '(s.client:IN:1,3)', 1, 0, 0, array(), 0, 'minwidth300');
		print '</td></tr>';

		// Return reason
		print '<tr><td>'.$langs->trans('ReturnReason').'</td><td>';
		$reasons = returnrequest_reason_types();
		print $form->selectarray('return_reason', $reasons, GETPOST('return_reason', 'alpha'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';

		// Resolution type
		print '<tr><td>'.$langs->trans('ResolutionType').'</td><td>';
		$resolutions = returnrequest_resolution_types();
		print $form->selectarray('resolution_type', $resolutions, GETPOST('resolution_type', 'alpha'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';

		print '</table>';

		print dol_get_fiche_end();

		// Shipment list — loaded via AJAX when customer is selected
		print '<br>';
		print '<div id="shipment-list-container">';
		print '<p class="opacitymedium">'.$langs->trans('SelectCustomerFirst').'</p>';
		print '</div>';

		print '</form>';

		// JavaScript: load customer shipments list, clicking a row redirects to Entry A
		print '<script>(function(){
	var shipmentsAjaxUrl = "'.DOL_URL_ROOT.'/custom/returnmgmt/ajax/customer_shipments.php";
	var container = document.getElementById("shipment-list-container");
	var lblRef       = "'.dol_escape_js($langs->trans('Ref')).'";
	var lblDate      = "'.dol_escape_js($langs->trans('Date')).'";
	var lblStatus    = "'.dol_escape_js($langs->trans('Status')).'";
	var lblLines     = "'.dol_escape_js($langs->trans('Lines')).'";
	var lblNoShipments = "'.dol_escape_js($langs->trans('NoShipmentsFound')).'";
	var lblSelectCust  = "'.dol_escape_js($langs->trans('SelectCustomerFirst')).'";
	var lblSelectShip  = "'.dol_escape_js($langs->trans('SelectShipment')).'";
	var cardUrl = "'.$_SERVER['PHP_SELF'].'";

	function buildShipmentsList(data) {
		if (!data || !data.length) {
			container.innerHTML = "<p class=\"opacitymedium\">" + lblNoShipments + "</p>";
			return;
		}
		var html = "<p class=\"opacitymedium\">" + lblSelectShip + "</p>";
		html += "<div class=\"div-table-responsive-no-min\">";
		html += "<table class=\"noborder centpercent\">";
		html += "<tr class=\"liste_titre\">";
		html += "<td>" + lblRef + "</td>";
		html += "<td>" + lblDate + "</td>";
		html += "<td>" + lblStatus + "</td>";
		html += "<td class=\"right\">" + lblLines + "</td>";
		html += "</tr>";

		data.forEach(function(ship, i) {
			html += "<tr class=\"oddeven\" style=\"cursor:pointer;\" onclick=\"window.location=\'" + cardUrl + "?action=create&fk_expedition=" + ship.id + "\'\">";
			html += "<td>" + escHtml(ship.ref) + "</td>";
			html += "<td>" + escHtml(ship.date) + "</td>";
			html += "<td>" + escHtml(ship.status_label) + "</td>";
			html += "<td class=\"right\">" + ship.nb_lines + "</td>";
			html += "</tr>";
		});

		html += "</table></div>";
		container.innerHTML = html;
	}

	function escHtml(s) { var d = document.createElement("div"); d.textContent = s || ""; return d.innerHTML; }

	function loadShipments() {
		var socEl = document.querySelector("[name=fk_soc]");
		var sid = socEl ? parseInt(socEl.value, 10) || 0 : 0;
		if (!sid) {
			container.innerHTML = "<p class=\"opacitymedium\">" + lblSelectCust + "</p>";
			return;
		}
		container.innerHTML = "<p class=\"opacitymedium\">...</p>";
		fetch(shipmentsAjaxUrl + "?socid=" + sid, {credentials:"same-origin"})
			.then(function(r) { return r.json(); })
			.then(function(data) { buildShipmentsList(data); })
			.catch(function() { container.innerHTML = "<p class=\"warning\">" + lblNoShipments + "</p>"; });
	}

	if (typeof jQuery !== "undefined") {
		jQuery(document).on("select2:select select2:clear", "[name=fk_soc]", loadShipments);
	}
	document.addEventListener("change", function(e) {
		if (e.target && e.target.name === "fk_soc") { loadShipments(); }
	});

	var initSocEl = document.querySelector("[name=fk_soc]");
	if (initSocEl && parseInt(initSocEl.value, 10) > 0) { loadShipments(); }
})();</script>';
	}
}

// ---------- EDIT FORM ----------
elseif ($action == 'edit' && $object->id > 0 && $permwrite) {
	$head = returnrequest_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('ReturnRequest'), -1, 'technic');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';

	print '<table class="border centpercent">';

	// Ref
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.$object->ref.'</td></tr>';

	// Customer (read-only in edit — lines are already tied to this customer)
	if ($object->fk_soc > 0) {
		$soc = new Societe($db);
		$soc->fetch($object->fk_soc);
		print '<tr><td>'.$langs->trans('Customer').'</td><td>'.$soc->getNomUrl(1).'</td></tr>';
		print '<input type="hidden" name="fk_soc" value="'.$object->fk_soc.'">';
	}

	// Shipment (read-only)
	if ($object->fk_expedition > 0) {
		$expedition = new Expedition($db);
		$expedition->fetch($object->fk_expedition);
		print '<tr><td>'.$langs->trans('Shipment').'</td><td>'.$expedition->getNomUrl(1).'</td></tr>';
	}

	// Label
	print '<tr><td>'.$langs->trans('Label').'</td><td>';
	print '<input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($object->label).'">';
	print '</td></tr>';

	// Date Return
	print '<tr><td>'.$langs->trans('ReturnDate').'</td><td>';
	print $form->selectDate($object->date_return, 'date_return', 0, 0, 1, '', 1, 1);
	print '</td></tr>';

	// Return reason
	print '<tr><td>'.$langs->trans('ReturnReason').'</td><td>';
	print $form->selectarray('return_reason', returnrequest_reason_types(), $object->return_reason, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
	print '</td></tr>';

	// Resolution type
	print '<tr><td>'.$langs->trans('ResolutionType').'</td><td>';
	print $form->selectarray('resolution_type', returnrequest_resolution_types(), $object->resolution_type, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
	print '</td></tr>';

	// Warehouse
	if (isModEnabled('stock')) {
		print '<tr><td>'.$langs->trans('Warehouse').'</td><td>';
		print $formproduct->selectWarehouses($object->fk_warehouse, 'fk_warehouse', '', 1);
		print '</td></tr>';
	}

	// Return tracking
	print '<tr><td>'.$langs->trans('ReturnTracking').'</td><td>';
	print '<input type="text" name="return_tracking" class="minwidth300" value="'.dol_escape_htmltag($object->return_tracking).'">';
	print '</td></tr>';

	// Condition on receipt
	print '<tr><td>'.$langs->trans('ConditionOnReceipt').'</td><td>';
	print $form->selectarray('condition_on_receipt', returnrequest_condition_types(), $object->condition_on_receipt, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
	print '</td></tr>';

	// Refund amount
	print '<tr><td>'.$langs->trans('RefundAmount').'</td><td>';
	print '<input type="text" name="refund_amount" class="minwidth100" value="'.dol_escape_htmltag($object->refund_amount).'">';
	print '</td></tr>';

	// Exchange product
	print '<tr><td>'.$langs->trans('ExchangeProduct').'</td><td>';
	$form->select_produits($object->exchange_fk_product, 'exchange_fk_product', '', 0, 0, -1, 2, '', 0, array(), 0, 'all', 0, 'minwidth300');
	print '</td></tr>';

	// Exchange serial
	print '<tr><td>'.$langs->trans('ExchangeSerialNumber').'</td><td>';
	print '<input type="text" name="exchange_serial_number" class="minwidth200" value="'.dol_escape_htmltag($object->exchange_serial_number).'">';
	print '</td></tr>';

	// Assigned user
	print '<tr><td>'.$langs->trans('AssignedTo').'</td><td>';
	print $form->select_dolusers($object->fk_user_assigned, 'fk_user_assigned', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'minwidth200');
	print '</td></tr>';

	// Notes
	print '<tr><td>'.$langs->trans('NotePublic').'</td><td>';
	$doleditor = new DolEditor('note_public', $object->note_public, '', 150, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '90%');
	$doleditor->Create();
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('NotePrivate').'</td><td>';
	$doleditor = new DolEditor('note_private', $object->note_private, '', 150, 'dolibarr_notes', 'In', true, false, isModEnabled('fckeditor'), ROWS_5, '90%');
	$doleditor->Create();
	print '</td></tr>';

	print '</table>';

	// Return lines (read-only — selected at creation)
	if (!empty($object->lines)) {
		print '<br>';
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Product').'</td>';
		print '<td>'.$langs->trans('Description').'</td>';
		print '<td>'.$langs->trans('SerialNumber').'</td>';
		print '<td class="right">'.$langs->trans('Qty').'</td>';
		if (isModEnabled('stock')) {
			print '<td>'.$langs->trans('Warehouse').'</td>';
		}
		print '<td>'.$langs->trans('Comment').'</td>';
		print '</tr>';
		foreach ($object->lines as $line) {
			print '<tr class="oddeven">';
			print '<td>';
			if ($line->fk_product > 0) {
				$prod = new Product($db);
				$prod->fetch($line->fk_product);
				print $prod->getNomUrl(1);
			}
			print '</td>';
			print '<td>'.dol_escape_htmltag($line->description).'</td>';
			print '<td>'.dol_escape_htmltag($line->serial_number).'</td>';
			print '<td class="right">'.$line->qty.'</td>';
			if (isModEnabled('stock')) {
				print '<td>';
				if ($line->fk_entrepot > 0) {
					require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
					$whtmp = new Entrepot($db);
					$whtmp->fetch($line->fk_entrepot);
					print $whtmp->getNomUrl(1);
				}
				print '</td>';
			}
			print '<td>'.dol_escape_htmltag($line->comment).'</td>';
			print '</tr>';
		}
		print '</table>';
		print '</div>';
	}

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// ---------- VIEW ----------
elseif ($object->id > 0) {

	// Confirmation dialogs
	if ($action == 'validate') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ValidateReturnRequest'), $langs->trans('ConfirmValidateReturnRequest'), 'confirm_validate', '', 0, 1);
	}
	if ($action == 'approve') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ApproveReturnRequest'), $langs->trans('ConfirmApproveReturnRequest'), 'confirm_approve', '', 0, 1);
	}
	if ($action == 'reject') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('RejectReturnRequest'), $langs->trans('ConfirmRejectReturnRequest'), 'confirm_reject', '', 0, 1);
	}
	if ($action == 'receive') {
		$formquestion = array(
			array('type' => 'text', 'name' => 'return_tracking', 'label' => $langs->trans('ReturnTracking'), 'value' => $object->return_tracking),
			array('type' => 'select', 'name' => 'condition_on_receipt', 'label' => $langs->trans('ConditionOnReceipt'), 'values' => returnrequest_condition_types(), 'default' => $object->condition_on_receipt),
		);
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ReceiveReturn'), $langs->trans('ConfirmReceiveReturn'), 'confirm_receive', $formquestion, 0, 1);
	}
	if ($action == 'processreturn') {
		$formquestion = array(
			array('type' => 'select', 'name' => 'resolution_type', 'label' => $langs->trans('ResolutionType'), 'values' => returnrequest_resolution_types(), 'default' => $object->resolution_type),
		);
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ProcessReturn'), $langs->trans('ConfirmProcessReturn'), 'confirm_process', $formquestion, 0, 1);
	}
	if ($action == 'close') {
		$formquestion = array();
		if ($object->resolution_type == 'refund') {
			$formquestion[] = array('type' => 'text', 'name' => 'refund_amount', 'label' => $langs->trans('RefundAmount'), 'value' => $object->refund_amount);
		}
		if ($object->resolution_type == 'exchange') {
			$formquestion[] = array('type' => 'text', 'name' => 'exchange_serial_number', 'label' => $langs->trans('ExchangeSerialNumber'), 'value' => $object->exchange_serial_number);
		}
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('CompleteReturn'), $langs->trans('ConfirmCompleteReturn'), 'confirm_complete', $formquestion, 0, 1);
	}
	if ($action == 'delete') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('DeleteReturnRequest'), $langs->trans('ConfirmDeleteReturnRequest'), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'createcreditnote') {
		$fk_facture = $object->getLinkedInvoice();
		if ($fk_facture > 0) {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$facture_tmp = new Facture($db);
			$facture_tmp->fetch($fk_facture);
			$formquestion = array(
				array('type' => 'other', 'name' => 'invoice_info', 'label' => $langs->trans('Invoice'), 'value' => $facture_tmp->getNomUrl(1)),
			);
			print $form->formconfirm(
				$_SERVER['PHP_SELF'].'?id='.$object->id,
				$langs->trans('CreateCreditNote'),
				$langs->trans('ConfirmCreateCreditNote'),
				'confirm_createcreditnote',
				$formquestion,
				0, 1
			);
		} else {
			setEventMessages($langs->trans('NoInvoiceLinked'), null, 'errors');
			$action = '';
		}
	}

	$head = returnrequest_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('ReturnRequest'), -1, 'technic');

	$linkback = '<a href="'.dol_buildpath('/returnmgmt/returnrequest_list.php', 1).'">'.$langs->trans('BackToList').'</a>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '', '', 0, '', '', 0);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	print '<table class="border centpercent tableforfield">';

	// Status
	print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>';
	print $object->getLibStatut(5);
	print '</td></tr>';

	// Customer
	if ($object->fk_soc > 0) {
		$soc = new Societe($db);
		$soc->fetch($object->fk_soc);
		print '<tr><td>'.$langs->trans('Customer').'</td><td>'.$soc->getNomUrl(1).'</td></tr>';
	}

	// Shipment
	if ($object->fk_expedition > 0) {
		$expedition = new Expedition($db);
		$expedition->fetch($object->fk_expedition);
		print '<tr><td>'.$langs->trans('Shipment').'</td><td>'.$expedition->getNomUrl(1).'</td></tr>';

		// Linked order
		$sql_order = "SELECT el.fk_source, c.ref as order_ref";
		$sql_order .= " FROM ".MAIN_DB_PREFIX."element_element as el";
		$sql_order .= " INNER JOIN ".MAIN_DB_PREFIX."commande as c ON c.rowid = el.fk_source";
		$sql_order .= " WHERE el.fk_target = ".((int) $object->fk_expedition);
		$sql_order .= " AND el.sourcetype = 'commande' AND el.targettype = 'shipping'";
		$sql_order .= " LIMIT 1";
		$resql_order = $db->query($sql_order);
		if ($resql_order && $db->num_rows($resql_order) > 0) {
			$obj_order = $db->fetch_object($resql_order);
			print '<tr><td>'.$langs->trans('Order').'</td><td>';
			print '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.$obj_order->fk_source.'">'.$obj_order->order_ref.'</a>';
			print '</td></tr>';
		}
	}

	// Label
	if (!empty($object->label)) {
		print '<tr><td>'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
	}

	// Date Return
	if (!empty($object->date_return)) {
		print '<tr><td>'.$langs->trans('ReturnDate').'</td><td>'.dol_print_date($object->date_return, 'day').'</td></tr>';
	}

	// Return reason
	if (!empty($object->return_reason)) {
		print '<tr><td>'.$langs->trans('ReturnReason').'</td><td>'.returnrequest_reason_label($object->return_reason).'</td></tr>';
	}

	// Resolution type
	if (!empty($object->resolution_type)) {
		print '<tr><td>'.$langs->trans('ResolutionType').'</td><td>'.returnrequest_resolution_label($object->resolution_type).'</td></tr>';
	}

	// Warehouse
	if ($object->fk_warehouse > 0 && isModEnabled('stock')) {
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
		$warehouse = new Entrepot($db);
		$warehouse->fetch($object->fk_warehouse);
		print '<tr><td>'.$langs->trans('Warehouse').'</td><td>'.$warehouse->getNomUrl(1).'</td></tr>';
	}

	// Assigned to
	if ($object->fk_user_assigned > 0) {
		$usertmp = new User($db);
		$usertmp->fetch($object->fk_user_assigned);
		print '<tr><td>'.$langs->trans('AssignedTo').'</td><td>'.$usertmp->getNomUrl(1).'</td></tr>';
	}

	// Return tracking
	if (!empty($object->return_tracking)) {
		print '<tr><td>'.$langs->trans('ReturnTracking').'</td><td>'.dol_escape_htmltag($object->return_tracking).'</td></tr>';
	}

	// Date received
	if (!empty($object->date_received)) {
		print '<tr><td>'.$langs->trans('DateReceived').'</td><td>'.dol_print_date($object->date_received, 'dayhour').'</td></tr>';
	}

	// Condition on receipt
	if (!empty($object->condition_on_receipt)) {
		print '<tr><td>'.$langs->trans('ConditionOnReceipt').'</td><td>'.returnrequest_condition_label($object->condition_on_receipt).'</td></tr>';
	}

	// Refund amount (if refund resolution)
	if ($object->resolution_type == 'refund' && !empty($object->refund_amount)) {
		print '<tr><td>'.$langs->trans('RefundAmount').'</td><td>'.price($object->refund_amount).'</td></tr>';
	}

	// Exchange product (if exchange resolution)
	if ($object->resolution_type == 'exchange' && $object->exchange_fk_product > 0) {
		$exchprod = new Product($db);
		$exchprod->fetch($object->exchange_fk_product);
		print '<tr><td>'.$langs->trans('ExchangeProduct').'</td><td>'.$exchprod->getNomUrl(1).'</td></tr>';
		if (!empty($object->exchange_serial_number)) {
			print '<tr><td>'.$langs->trans('ExchangeSerialNumber').'</td><td>'.dol_escape_htmltag($object->exchange_serial_number).'</td></tr>';
		}
	}

	// Date resolved
	if (!empty($object->date_resolved)) {
		print '<tr><td>'.$langs->trans('DateResolved').'</td><td>'.dol_print_date($object->date_resolved, 'dayhour').'</td></tr>';
	}

	// Date creation
	print '<tr><td>'.$langs->trans('DateCreation').'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';

	print '</table>';

	print '</div>';

	// Return lines
	if (!empty($object->lines)) {
		print '<br>';
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Product').'</td>';
		print '<td>'.$langs->trans('Description').'</td>';
		print '<td>'.$langs->trans('SerialNumber').'</td>';
		print '<td class="right">'.$langs->trans('Qty').'</td>';
		if (isModEnabled('stock')) {
			print '<td>'.$langs->trans('Warehouse').'</td>';
		}
		print '<td>'.$langs->trans('Comment').'</td>';
		print '</tr>';
		foreach ($object->lines as $line) {
			print '<tr class="oddeven">';
			print '<td>';
			if ($line->fk_product > 0) {
				$prodtmp = new Product($db);
				$prodtmp->fetch($line->fk_product);
				print $prodtmp->getNomUrl(1);
			}
			print '</td>';
			print '<td>'.dol_escape_htmltag($line->description).'</td>';
			print '<td>'.dol_escape_htmltag($line->serial_number).'</td>';
			print '<td class="right">'.$line->qty.'</td>';
			if (isModEnabled('stock')) {
				print '<td>';
				if ($line->fk_entrepot > 0) {
					require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
					$whtmp = new Entrepot($db);
					$whtmp->fetch($line->fk_entrepot);
					print $whtmp->getNomUrl(1);
				}
				print '</td>';
			}
			print '<td>'.dol_escape_htmltag($line->comment).'</td>';
			print '</tr>';
		}
		print '</table>';
		print '</div>';
	}

	print dol_get_fiche_end();

	// Linked objects
	$object->fetchObjectLinked();
	print $object->showLinkedObjectBlock();

	// Action buttons
	print '<div class="tabsAction">';

	// Edit
	if ($object->status == ReturnRequest::STATUS_DRAFT && $permwrite) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken().'">'.$langs->trans('Modify').'</a>';
	}

	// Validate (submit)
	if ($object->status == ReturnRequest::STATUS_DRAFT && $permwrite) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=validate&token='.newToken().'">'.$langs->trans('Submit').'</a>';
	}

	// Approve
	if ($object->status == ReturnRequest::STATUS_PENDING && $permapprove) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=approve&token='.newToken().'">'.$langs->trans('Approve').'</a>';
	}

	// Reject
	if ($object->status == ReturnRequest::STATUS_PENDING && $permapprove) {
		print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reject&token='.newToken().'">'.$langs->trans('Reject').'</a>';
	}

	// Receive
	if ($object->status == ReturnRequest::STATUS_APPROVED && $permwrite) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=receive&token='.newToken().'">'.$langs->trans('Receive').'</a>';
	}

	// Process
	if ($object->status == ReturnRequest::STATUS_RECEIVED && $permwrite) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=processreturn&token='.newToken().'">'.$langs->trans('Process').'</a>';
	}

	// Complete
	if ($object->status == ReturnRequest::STATUS_PROCESSING && $permclose) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=close&token='.newToken().'">'.$langs->trans('Complete').'</a>';
	}

	// Create Credit Note
	if ($object->status == ReturnRequest::STATUS_COMPLETED && $object->resolution_type == 'refund' && $permclose) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=createcreditnote&token='.newToken().'">'.$langs->trans('CreateCreditNote').'</a>';
	}

	// Delete
	if ($object->status == ReturnRequest::STATUS_DRAFT && $permdelete) {
		print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
	}

	print '</div>';
}

llxFooter();
$db->close();
