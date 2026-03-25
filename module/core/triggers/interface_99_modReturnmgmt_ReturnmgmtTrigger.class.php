<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceReturnmgmtTrigger extends DolibarrTriggers
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
		$this->description = 'Triggers for ReturnMgmt module';
		$this->version = '1.0.0';
		$this->picto = 'technic';
	}

	/**
	 * Return name of trigger
	 *
	 * @return string Trigger name
	 */
	public function getName()
	{
		return 'ReturnmgmtTrigger';
	}

	/**
	 * Return description of trigger
	 *
	 * @return string Trigger description
	 */
	public function getDesc()
	{
		return 'Triggers for ReturnMgmt module';
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
		if (!isModEnabled('returnmgmt')) {
			return 0;
		}

		// ReturnRequest triggers
		switch ($action) {
			case 'RETURNMGMT_RETURNREQUEST_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'RETURNMGMT_RETURNREQUEST_VALIDATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'RETURNMGMT_RETURNREQUEST_APPROVE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				// Notify customer that return is approved
				if (getDolGlobalInt('RETURNMGMT_NOTIFY_ON_APPROVE')) {
					$this->_notifyCustomer($object, $user, $langs, 'approve');
				}
				break;

			case 'RETURNMGMT_RETURNREQUEST_REJECT':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				// Notify customer that return was rejected
				if (getDolGlobalInt('RETURNMGMT_NOTIFY_ON_COMPLETE')) {
					$this->_notifyCustomer($object, $user, $langs, 'reject');
				}
				break;

			case 'RETURNMGMT_RETURNREQUEST_RECEIVE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'RETURNMGMT_RETURNREQUEST_PROCESS':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'RETURNMGMT_RETURNREQUEST_COMPLETE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				// Notify customer of resolution
				if (getDolGlobalInt('RETURNMGMT_NOTIFY_ON_COMPLETE')) {
					$this->_notifyCustomer($object, $user, $langs, 'complete');
				}
				break;

			// Native module triggers — auto-link SO when created from return request
			case 'ORDER_CREATE':
				if (!empty($object->origin)
					&& $object->origin === 'returnmgmt_returnrequest'
					&& !empty($object->origin_id)) {
					$this->_linkOrderToReturn($object, $user);
				}
				break;
		}

		return 0;
	}

	/**
	 * Link a sales order to the originating return request
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
		$sql .= " VALUES (".$return_id.", 'returnrequest',";
		$sql .= " ".$order_id.", 'commande', ".((int) $order->entity).")";
		$this->db->query($sql);

		dol_syslog("ReturnMgmt trigger: linked order ".$order_id." to return request ".$return_id);
	}

	/**
	 * Send notification email to customer
	 *
	 * @param  object    $object Return request object
	 * @param  User      $user   Current user
	 * @param  Translate $langs  Language object
	 * @param  string    $type   Notification type (approve, reject, complete)
	 * @return void
	 */
	private function _notifyCustomer($object, $user, $langs, $type)
	{
		// Placeholder for email notification logic
		// Implement using CMailFile following Dolibarr patterns
		dol_syslog("ReturnMgmt: would notify customer for return ".$object->ref." type=".$type);
	}
}
