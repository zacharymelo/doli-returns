<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/customerreturn/class/customerreturn.class.php');
dol_include_once('/customerreturn/lib/customerreturn.lib.php');

$langs->loadLangs(array('customerreturn@customerreturn', 'companies', 'products', 'other'));

// Permissions
if (!$user->hasRight('customerreturn', 'customerreturn', 'read')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

if (empty($sortfield)) { $sortfield = 't.ref'; }
if (empty($sortorder)) { $sortorder = 'DESC'; }

// Search filters
$search_ref         = GETPOST('search_ref', 'alpha');
$search_label       = GETPOST('search_label', 'alpha');
$search_company     = GETPOST('search_company', 'alpha');
$search_note_public = GETPOST('search_note_public', 'alpha');
$search_status      = GETPOST('search_status', 'intcomma');
$search_date_start  = dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear'));
$search_date_end    = dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear'));

// Reset search
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_ref = '';
	$search_label = '';
	$search_company = '';
	$search_note_public = '';
	$search_status = '';
	$search_date_start = '';
	$search_date_end = '';
}

$form = new Form($db);

// Build SQL
$sql = "SELECT t.rowid, t.ref, t.fk_soc, t.label, t.return_date, t.date_creation";
$sql .= ", t.note_public, t.status";
$sql .= ", s.nom as company_name";
$sql .= " FROM ".MAIN_DB_PREFIX."customer_return as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " WHERE t.entity IN (".getEntity('customerreturn').")";

if (!empty($search_ref)) {
	$sql .= natural_search('t.ref', $search_ref);
}
if (!empty($search_label)) {
	$sql .= natural_search('t.label', $search_label);
}
if (!empty($search_company)) {
	$sql .= natural_search('s.nom', $search_company);
}
if (!empty($search_note_public)) {
	$sql .= natural_search('t.note_public', $search_note_public);
}
if ($search_status !== '' && $search_status !== '-1') {
	if (strpos($search_status, ',') !== false) {
		$statuses = array_map('intval', explode(',', $search_status));
		$sql .= " AND t.status IN (".implode(',', $statuses).")";
	} else {
		$sql .= " AND t.status = ".((int) $search_status);
	}
}
if (!empty($search_date_start)) {
	$sql .= " AND t.date_creation >= '".$db->idate($search_date_start)."'";
}
if (!empty($search_date_end)) {
	$sql .= " AND t.date_creation <= '".$db->idate($search_date_end)."'";
}

// Count total
$sqlcount = preg_replace('/^SELECT.*FROM/s', 'SELECT COUNT(t.rowid) as total FROM', $sql);
$resqlcount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($resqlcount) {
	$objcount = $db->fetch_object($resqlcount);
	$nbtotalofrecords = (int) $objcount->total;
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

/*
 * VIEW
 */

llxHeader('', $langs->trans('CustomerReturnList'));

$param = '';
if (!empty($search_ref))           { $param .= '&search_ref='.urlencode($search_ref); }
if (!empty($search_label))         { $param .= '&search_label='.urlencode($search_label); }
if (!empty($search_company))       { $param .= '&search_company='.urlencode($search_company); }
if (!empty($search_note_public))   { $param .= '&search_note_public='.urlencode($search_note_public); }
if ($search_status !== '')         { $param .= '&search_status='.urlencode($search_status); }

$newcardbutton = '';
if ($user->hasRight('customerreturn', 'customerreturn', 'write')) {
	$newcardbutton .= dolGetButtonTitle($langs->trans('NewCustomerReturn'), '', 'fa fa-plus-circle', dol_buildpath('/customerreturn/customerreturn_card.php', 1).'?action=create');
}

print_barre_liste(
	$langs->trans('CustomerReturnList'),
	$page,
	$_SERVER['PHP_SELF'],
	$param,
	$sortfield,
	$sortorder,
	'',
	$num,
	$nbtotalofrecords,
	'dollyrevert',
	0,
	$newcardbutton,
	'',
	$limit
);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

print '<table class="noborder centpercent">';

// Header row
print '<tr class="liste_titre">';
print_liste_field_titre('Ref',            $_SERVER['PHP_SELF'], 't.ref',           '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Label',          $_SERVER['PHP_SELF'], 't.label',         '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ReturnDate',     $_SERVER['PHP_SELF'], 't.date_creation', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('Customer',       $_SERVER['PHP_SELF'], 's.nom',           '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('NotePublic',     $_SERVER['PHP_SELF'], 't.note_public',   '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Status',         $_SERVER['PHP_SELF'], 't.status',        '', $param, '', $sortfield, $sortorder, 'center ');
print '</tr>';

// Search row
print '<tr class="liste_titre_filter">';
// Ref
print '<td><input type="text" name="search_ref" class="flat maxwidth100" value="'.dol_escape_htmltag($search_ref).'"></td>';
// Label
print '<td><input type="text" name="search_label" class="flat maxwidth150" value="'.dol_escape_htmltag($search_label).'"></td>';
// Date range
print '<td class="center">';
print '<div class="nowrap">';
print $langs->trans('From').' ';
print $form->selectDate($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel');
print '</div>';
print '<div class="nowrap">';
print $langs->trans('to').' ';
print $form->selectDate($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel');
print '</div>';
print '</td>';
// Customer
print '<td><input type="text" name="search_company" class="flat maxwidth150" value="'.dol_escape_htmltag($search_company).'"></td>';
// Note public
print '<td><input type="text" name="search_note_public" class="flat maxwidth150" value="'.dol_escape_htmltag($search_note_public).'"></td>';
// Status
$statusarray = array(
	CustomerReturn::STATUS_DRAFT => $langs->trans('Draft'),
	CustomerReturn::STATUS_VALIDATED => $langs->trans('Validated'),
	CustomerReturn::STATUS_CLOSED => $langs->trans('Closed'),
);
print '<td class="center">'.$form->selectarray('search_status', $statusarray, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100 center').'</td>';
print '</tr>';

// Data rows
$object_tmp = new CustomerReturn($db);
$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (!$obj) {
		break;
	}

	$object_tmp->id = $obj->rowid;
	$object_tmp->ref = $obj->ref;
	$object_tmp->status = $obj->status;

	print '<tr class="oddeven">';

	// Ref
	print '<td>'.$object_tmp->getNomUrl(1).'</td>';

	// Label
	print '<td>'.dol_escape_htmltag($obj->label).'</td>';

	// Return date (use return_date if set, else date_creation)
	$display_date = !empty($obj->return_date) ? $obj->return_date : $obj->date_creation;
	print '<td class="center">'.dol_print_date($db->jdate($display_date), 'day').'</td>';

	// Customer
	if ($obj->fk_soc > 0) {
		$soc = new Societe($db);
		$soc->id = $obj->fk_soc;
		$soc->name = $obj->company_name;
		print '<td>'.$soc->getNomUrl(1).'</td>';
	} else {
		print '<td></td>';
	}

	// Note public (truncated)
	$note_display = dol_trunc(dol_escape_htmltag(strip_tags($obj->note_public)), 60);
	print '<td>'.$note_display.'</td>';

	// Status
	print '<td class="center">'.$object_tmp->getLibStatut(5).'</td>';

	print '</tr>';
	$i++;
}

if ($num == 0) {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" name="button_search" class="button" value="'.$langs->trans('Search').'">';
print '<input type="submit" name="button_removefilter" class="button" value="'.$langs->trans('RemoveFilter').'">';
print '</div>';

print '</form>';

llxFooter();
$db->close();
