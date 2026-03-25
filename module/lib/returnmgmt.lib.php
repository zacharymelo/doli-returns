<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare array of tabs for ReturnRequest card
 *
 * @param  ReturnRequest $object Object
 * @return array                 Array of tabs
 */
function returnrequest_prepare_head($object)
{
	global $langs, $conf;

	$langs->load('returnmgmt@returnmgmt');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/returnmgmt/returnrequest_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Card');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/returnmgmt/returnrequest_note.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	if (!empty($object->note_private) || !empty($object->note_public)) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">...</span>';
	}
	$head[$h][2] = 'note';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'returnrequest@returnmgmt');

	return $head;
}

/**
 * Return array of return reason types
 *
 * @return array Reason types
 */
function returnrequest_reason_types()
{
	global $langs;
	$langs->load('returnmgmt@returnmgmt');

	return array(
		'defective'           => $langs->trans('ReasonDefective'),
		'wrong_item'          => $langs->trans('ReasonWrongItem'),
		'not_as_described'    => $langs->trans('ReasonNotAsDescribed'),
		'buyer_remorse'       => $langs->trans('ReasonBuyerRemorse'),
		'damaged_in_shipping' => $langs->trans('ReasonDamagedInShipping'),
		'other'               => $langs->trans('ReasonOther'),
	);
}

/**
 * Return label for a return reason
 *
 * @param  string $reason Reason code
 * @return string         Translated label
 */
function returnrequest_reason_label($reason)
{
	$types = returnrequest_reason_types();
	return isset($types[$reason]) ? $types[$reason] : $reason;
}

/**
 * Return array of resolution types
 *
 * @return array Resolution types
 */
function returnrequest_resolution_types()
{
	global $langs;
	$langs->load('returnmgmt@returnmgmt');

	return array(
		'refund'   => $langs->trans('ResolutionRefund'),
		'exchange' => $langs->trans('ResolutionExchange'),
		'repair'   => $langs->trans('ResolutionRepair'),
		'reject'   => $langs->trans('ResolutionReject'),
	);
}

/**
 * Return label for a resolution type
 *
 * @param  string $type Resolution type code
 * @return string       Translated label
 */
function returnrequest_resolution_label($type)
{
	$types = returnrequest_resolution_types();
	return isset($types[$type]) ? $types[$type] : $type;
}

/**
 * Return array of condition types
 *
 * @return array Condition types
 */
function returnrequest_condition_types()
{
	global $langs;
	$langs->load('returnmgmt@returnmgmt');

	return array(
		'good' => $langs->trans('ConditionGood'),
		'fair' => $langs->trans('ConditionFair'),
		'poor' => $langs->trans('ConditionPoor'),
		'scrap' => $langs->trans('ConditionScrap'),
	);
}

/**
 * Return label for a condition
 *
 * @param  string $condition Condition code
 * @return string            Translated label
 */
function returnrequest_condition_label($condition)
{
	$types = returnrequest_condition_types();
	return isset($types[$condition]) ? $types[$condition] : $condition;
}
