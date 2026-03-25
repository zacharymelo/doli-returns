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

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
dol_include_once('/customerreturn/class/customerreturn.class.php');
dol_include_once('/customerreturn/lib/customerreturn.lib.php');

$langs->loadLangs(array('customerreturn@customerreturn', 'companies'));

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new CustomerReturn($db);

$permread  = $user->hasRight('customerreturn', 'customerreturn', 'read');
$permwrite = $user->hasRight('customerreturn', 'customerreturn', 'write');

if (!$permread) {
	accessforbidden();
}

if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

// Actions
if ($action == 'update_note_public' && $permwrite) {
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->update($user, 1);
}
if ($action == 'update_note_private' && $permwrite) {
	$object->note_private = GETPOST('note_private', 'restricthtml');
	$object->update($user, 1);
}

// View
llxHeader('', $langs->trans('CustomerReturn'));

if ($object->id > 0) {
	$head = customerreturn_prepare_head($object);
	print dol_get_fiche_head($head, 'note', $langs->trans('CustomerReturn'), -1, 'dollyrevert');

	$linkback = '<a href="'.dol_buildpath('/customerreturn/customerreturn_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

	print '<div class="fichecenter">';

	$cssclass = 'titlefield';
	include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';

	print '</div>';

	print dol_get_fiche_end();
}

llxFooter();
$db->close();
