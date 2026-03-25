<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceCustomerreturnTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'crm';
		$this->description = 'Triggers for CustomerReturn module';
		$this->version = '2.0.0';
		$this->picto = 'dollyrevert';
	}

	/**
	 * Return name of trigger
	 *
	 * @return string Trigger name
	 */
	public function getName()
	{
		return 'CustomerreturnTrigger';
	}

	/**
	 * Return description of trigger
	 *
	 * @return string Trigger description
	 */
	public function getDesc()
	{
		return 'Triggers for CustomerReturn module';
	}

	/**
	 * Execute trigger action
	 *
	 * @param  string    $action Event action code
	 * @param  object    $object Object
	 * @param  User      $user   User
	 * @param  Translate $langs  Langs
	 * @param  Conf      $conf   Conf
	 * @return int               0=OK, <0=KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('customerreturn')) {
			return 0;
		}

		switch ($action) {
			case 'CUSTOMERRETURN_CUSTOMERRETURN_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'CUSTOMERRETURN_CUSTOMERRETURN_CLOSE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'CUSTOMERRETURN_CUSTOMERRETURN_REOPEN':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			// Native module triggers -- auto-link SO when created from customer return
			case 'ORDER_CREATE':
				if (!empty($object->origin)
					&& $object->origin === 'customerreturn_customerreturn'
					&& !empty($object->origin_id)) {
					$this->_linkOrderToReturn($object, $user);
				}
				break;
		}

		return 0;
	}

	/**
	 * Link a sales order to the originating customer return
	 *
	 * @param  object $order Sales order
	 * @param  User   $user  Current user
	 * @return void
	 */
	private function _linkOrderToReturn($order, $user)
	{
		$order_id = (int) $order->id;
		$return_id = (int) $order->origin_id;

		// Insert bidirectional link in element_element
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."element_element";
		$sql .= " (fk_source, sourcetype, fk_target, targettype, entity)";
		$sql .= " VALUES (".$return_id.", 'customerreturn',";
		$sql .= " ".$order_id.", 'commande', ".((int) $order->entity).")";
		$this->db->query($sql);

		dol_syslog("CustomerReturn trigger: linked order ".$order_id." to customer return ".$return_id);
	}
}
