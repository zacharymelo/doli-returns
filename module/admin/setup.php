<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
dol_include_once('/returnmgmt/lib/returnmgmt.lib.php');

$langs->loadLangs(array('admin', 'returnmgmt@returnmgmt'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// Save settings
if ($action == 'update') {
	$error = 0;

	$res = dolibarr_set_const($db, 'RETURNMGMT_RETURN_WINDOW_DAYS', GETPOST('RETURNMGMT_RETURN_WINDOW_DAYS', 'int'), 'chaine', 0, '', $conf->entity);
	if (!$res > 0) { $error++; }

	$res = dolibarr_set_const($db, 'RETURNMGMT_AUTO_APPROVE', GETPOST('RETURNMGMT_AUTO_APPROVE', 'int'), 'chaine', 0, '', $conf->entity);
	if (!$res > 0) { $error++; }

	$res = dolibarr_set_const($db, 'RETURNMGMT_REQUIRE_TRACKING', GETPOST('RETURNMGMT_REQUIRE_TRACKING', 'int'), 'chaine', 0, '', $conf->entity);
	if (!$res > 0) { $error++; }

	$res = dolibarr_set_const($db, 'RETURNMGMT_NOTIFY_ON_APPROVE', GETPOST('RETURNMGMT_NOTIFY_ON_APPROVE', 'int'), 'chaine', 0, '', $conf->entity);
	if (!$res > 0) { $error++; }

	$res = dolibarr_set_const($db, 'RETURNMGMT_NOTIFY_ON_COMPLETE', GETPOST('RETURNMGMT_NOTIFY_ON_COMPLETE', 'int'), 'chaine', 0, '', $conf->entity);
	if (!$res > 0) { $error++; }

	if (isModEnabled('stock')) {
		$res = dolibarr_set_const($db, 'RETURNMGMT_WAREHOUSE_DEFAULT', GETPOSTINT('RETURNMGMT_WAREHOUSE_DEFAULT'), 'chaine', 0, '', $conf->entity);
		if (!$res > 0) { $error++; }
	}

	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
}

// View
llxHeader('', $langs->trans('ReturnMgmtSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ReturnMgmtSetup'), $linkback, 'title_setup');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

// Return window days
print '<tr class="oddeven"><td>'.$langs->trans('ReturnWindowDays').'</td><td>';
print '<input type="number" name="RETURNMGMT_RETURN_WINDOW_DAYS" class="maxwidth100" value="'.getDolGlobalString('RETURNMGMT_RETURN_WINDOW_DAYS', '30').'">';
print ' '.$langs->trans('Days');
print '</td></tr>';

// Default warehouse
if (isModEnabled('stock')) {
	$formproduct = new FormProduct($db);
	print '<tr class="oddeven"><td>'.$langs->trans('DefaultWarehouse').'</td><td>';
	print $formproduct->selectWarehouses(getDolGlobalInt('RETURNMGMT_WAREHOUSE_DEFAULT'), 'RETURNMGMT_WAREHOUSE_DEFAULT', '', 1);
	print '</td></tr>';
}

// Auto approve
print '<tr class="oddeven"><td>'.$langs->trans('AutoApproveReturns').'</td><td>';
print ajax_constantonoff('RETURNMGMT_AUTO_APPROVE');
print '</td></tr>';

// Require tracking
print '<tr class="oddeven"><td>'.$langs->trans('RequireTrackingBeforeReceive').'</td><td>';
print ajax_constantonoff('RETURNMGMT_REQUIRE_TRACKING');
print '</td></tr>';

// Notify on approve
print '<tr class="oddeven"><td>'.$langs->trans('NotifyCustomerOnApprove').'</td><td>';
print ajax_constantonoff('RETURNMGMT_NOTIFY_ON_APPROVE');
print '</td></tr>';

// Notify on complete
print '<tr class="oddeven"><td>'.$langs->trans('NotifyCustomerOnComplete').'</td><td>';
print ajax_constantonoff('RETURNMGMT_NOTIFY_ON_COMPLETE');
print '</td></tr>';

print '</table>';

print '<br>';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';

print '</form>';

// Numbering model section
print '<br>';
print load_fiche_titre($langs->trans('NumberingModel'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Name').'</td><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('Example').'</td><td class="center">'.$langs->trans('Status').'</td></tr>';

$dir = DOL_DOCUMENT_ROOT.'/custom/returnmgmt/core/modules/returnmgmt/';
$handle = opendir($dir);
if (is_resource($handle)) {
	while (($file = readdir($handle)) !== false) {
		if (preg_match('/^mod_returnmgmt_(.*)\.php$/', $file)) {
			require_once $dir.$file;
			$classname = pathinfo($file, PATHINFO_FILENAME);
			$obj = new $classname();

			print '<tr class="oddeven">';
			print '<td>'.$obj->name.'</td>';
			print '<td>'.$obj->version.'</td>';
			print '<td>'.$obj->getExample().'</td>';

			$current = getDolGlobalString('RETURNMGMT_ADDON', 'mod_returnmgmt_standard');
			if ($classname == $current) {
				print '<td class="center"><span class="badge badge-status4">'.$langs->trans('Activated').'</span></td>';
			} else {
				print '<td class="center"><a href="'.$_SERVER['PHP_SELF'].'?action=setmod&token='.newToken().'&value='.$classname.'">'.$langs->trans('Activate').'</a></td>';
			}
			print '</tr>';
		}
	}
	closedir($handle);
}
print '</table>';

llxFooter();
$db->close();
