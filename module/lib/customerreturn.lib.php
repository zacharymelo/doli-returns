<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare array of tabs for CustomerReturn card
 *
 * @param  CustomerReturn $object Object
 * @return array                  Array of tabs
 */
function customerreturn_prepare_head($object)
{
	global $langs, $conf;

	$langs->load('customerreturn@customerreturn');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/customerreturn/customerreturn_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Card');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/customerreturn/customerreturn_note.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	if (!empty($object->note_private) || !empty($object->note_public)) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">...</span>';
	}
	$head[$h][2] = 'note';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'customerreturn@customerreturn');

	return $head;
}
