<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

class ActionsCustomerreturn
{
	public $db;
	public $error = '';
	public $errors = array();
	public $results = array();
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Register element properties so Dolibarr can resolve customerreturn
	 * in linked objects, "Link to..." dropdowns, etc.
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$elementType = isset($parameters['elementType']) ? $parameters['elementType'] : '';

		if ($elementType === 'customerreturn' || $elementType === 'customerreturn_customerreturn') {
			$this->results = array(
				'module'        => 'customerreturn',
				'element'       => 'customerreturn',
				'table_element' => 'customer_return',
				'subelement'    => 'customerreturn',
				'classpath'     => 'customerreturn/class',
				'classfile'     => 'customerreturn',
				'classname'     => 'CustomerReturn',
			);
		}

		return 0;
	}

	/**
	 * Inject customerreturn into "Link to..." dropdown on any native card
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $user;

		if (!isModEnabled('customerreturn')) {
			return 0;
		}

		$listofidcompanytoscan = isset($parameters['listofidcompanytoscan'])
			? $parameters['listofidcompanytoscan'] : '';
		if (empty($listofidcompanytoscan)) {
			return 0;
		}

		$sanitized = $db->sanitize($listofidcompanytoscan);
		$this->results = array();

		if ($user->hasRight('customerreturn', 'customerreturn', 'read')) {
			$this->results['customerreturn'] = array(
				'enabled' => 1,
				'perms'   => 1,
				'label'   => 'LinkToCustomerReturn',
				'sql'     => "SELECT s.rowid as socid, s.nom as name, s.client,"
					." t.rowid, t.ref"
					." FROM ".MAIN_DB_PREFIX."societe as s,"
					." ".MAIN_DB_PREFIX."customer_return as t"
					." WHERE t.fk_soc = s.rowid"
					." AND t.fk_soc IN (".$sanitized.")"
					." AND t.entity IN (".getEntity('customerreturn').")"
					." ORDER BY t.ref",
			);
		}

		return 0;
	}

	/**
	 * Inject hidden origin fields when creating a sales order from a customer return
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $db, $user, $conf;

		if (!isModEnabled('customerreturn')) {
			return 0;
		}

		// Inject origin hidden fields on sales order creation form
		if (isset($object->element) && $object->element === 'commande' && $action === 'create') {
			$source_id = GETPOSTINT('customerreturn_source_id');
			if ($source_id > 0) {
				$this->resprints  = '<input type="hidden" name="origin" value="customerreturn_customerreturn">';
				$this->resprints .= '<input type="hidden" name="originid" value="'.$source_id.'">';
			}
			return 0;
		}

		return 0;
	}

	/**
	 * Handle custom actions on hooked pages
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;

		if (!isModEnabled('customerreturn')) {
			return 0;
		}

		return 0;
	}

	/**
	 * Add "Create Return" button on shipment (expedition) card
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Expedition object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!isModEnabled('customerreturn')) {
			return 0;
		}

		// Only on expedition card, when shipment is validated or later
		if (!isset($object->element) || $object->element !== 'shipping') {
			return 0;
		}

		if ($object->statut >= 1 && $user->hasRight('customerreturn', 'customerreturn', 'write')) {
			$langs->load('customerreturn@customerreturn');
			$url = dol_buildpath('/customerreturn/customerreturn_card.php', 1);
			$url .= '?action=create&fk_expedition='.$object->id;
			print '<a class="butAction" href="'.$url.'">'.$langs->trans('CreateReturnFromShipment').'</a>';
		}

		return 0;
	}
}
