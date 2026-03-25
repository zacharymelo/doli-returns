<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/returnmgmt/class/returnrequestline.class.php';

class ReturnRequest extends CommonObject
{
	public $TRIGGER_PREFIX = 'RETURNMGMT';
	public $element = 'returnrequest';
	public $table_element = 'returnmgmt_return';
	public $picto = 'technic';
	protected $table_ref_field = 'ref';

	public $table_element_line = 'returnmgmt_return_line';
	public $class_element_line = 'ReturnRequestLine';
	public $fk_element = 'fk_returnmgmt_return';

	// Status constants
	const STATUS_DRAFT      = 0;
	const STATUS_PENDING    = 1;
	const STATUS_APPROVED   = 2;
	const STATUS_RECEIVED   = 3;
	const STATUS_PROCESSING = 4;
	const STATUS_COMPLETED  = 5;
	const STATUS_REJECTED   = 9;

	// Resolution types
	const RESOLUTION_REFUND   = 'refund';
	const RESOLUTION_EXCHANGE = 'exchange';
	const RESOLUTION_REPAIR   = 'repair';
	const RESOLUTION_REJECT   = 'reject';

	// Return reasons
	const REASON_DEFECTIVE          = 'defective';
	const REASON_WRONG_ITEM         = 'wrong_item';
	const REASON_NOT_AS_DESCRIBED   = 'not_as_described';
	const REASON_BUYER_REMORSE      = 'buyer_remorse';
	const REASON_DAMAGED_IN_SHIPPING = 'damaged_in_shipping';
	const REASON_OTHER              = 'other';

	// DB fields
	public $ref;
	public $entity;
	public $fk_soc;
	public $fk_product;
	public $serial_number;
	public $return_reason;
	public $resolution_type;
	public $fk_warehouse;
	public $return_tracking;
	public $date_received;
	public $date_resolved;
	public $fk_user_assigned;
	public $condition_on_receipt;
	public $refund_amount;
	public $exchange_fk_product;
	public $exchange_serial_number;
	public $fk_expedition;
	public $label;
	public $date_return;
	public $status;
	public $note_private;
	public $note_public;
	public $date_creation;
	public $date_validation;
	public $date_closed;
	public $fk_user_creat;
	public $fk_user_valid;
	public $fk_user_close;
	public $fk_user_modif;
	public $import_key;
	public $model_pdf;
	public $last_main_doc;

	public $lines = array();

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
	 * Create return request in database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$this->ref = $this->getNextNumRef();
		if (empty($this->ref)) {
			$this->error = 'ErrorFailedToGetNextRef';
			$this->db->rollback();
			return -1;
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."returnmgmt_return (";
		$sql .= "ref, entity, fk_soc, fk_product, serial_number";
		$sql .= ", return_reason, resolution_type, fk_warehouse";
		$sql .= ", fk_user_assigned, fk_expedition, label, date_return";
		$sql .= ", status, note_private, note_public";
		$sql .= ", date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."'";
		$sql .= ", ".((int) $conf->entity);
		$sql .= ", ".((int) $this->fk_soc);
		$sql .= ", ".(empty($this->fk_product) ? "NULL" : ((int) $this->fk_product));
		$sql .= ", ".(empty($this->serial_number) ? "NULL" : "'".$this->db->escape($this->serial_number)."'");
		$sql .= ", ".(empty($this->return_reason) ? "NULL" : "'".$this->db->escape($this->return_reason)."'");
		$sql .= ", ".(empty($this->resolution_type) ? "NULL" : "'".$this->db->escape($this->resolution_type)."'");
		$sql .= ", ".(empty($this->fk_warehouse) ? "NULL" : ((int) $this->fk_warehouse));
		$sql .= ", ".(empty($this->fk_user_assigned) ? "NULL" : ((int) $this->fk_user_assigned));
		$sql .= ", ".(empty($this->fk_expedition) ? "NULL" : ((int) $this->fk_expedition));
		$sql .= ", ".(empty($this->label) ? "NULL" : "'".$this->db->escape($this->label)."'");
		$sql .= ", ".(empty($this->date_return) ? "NULL" : "'".$this->db->idate($this->date_return)."'");
		$sql .= ", ".self::STATUS_DRAFT;
		$sql .= ", ".(empty($this->note_private) ? "NULL" : "'".$this->db->escape($this->note_private)."'");
		$sql .= ", ".(empty($this->note_public) ? "NULL" : "'".$this->db->escape($this->note_public)."'");
		$sql .= ", '".$this->db->idate($now)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."returnmgmt_return");
			$this->status = self::STATUS_DRAFT;
			$this->date_creation = $now;
			$this->fk_user_creat = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_CREATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Fetch object from database
	 *
	 * @param  int    $id  Object ID
	 * @param  string $ref Object ref
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		$sql = "SELECT t.rowid, t.ref, t.entity, t.fk_soc, t.fk_product, t.serial_number";
		$sql .= ", t.return_reason, t.resolution_type, t.fk_warehouse";
		$sql .= ", t.return_tracking, t.date_received, t.date_resolved";
		$sql .= ", t.fk_user_assigned, t.condition_on_receipt";
		$sql .= ", t.refund_amount, t.exchange_fk_product, t.exchange_serial_number";
		$sql .= ", t.fk_expedition, t.label, t.date_return";
		$sql .= ", t.status, t.note_private, t.note_public";
		$sql .= ", t.date_creation, t.date_validation, t.date_closed, t.tms";
		$sql .= ", t.fk_user_creat, t.fk_user_valid, t.fk_user_close, t.fk_user_modif";
		$sql .= ", t.import_key, t.model_pdf, t.last_main_doc";
		$sql .= " FROM ".MAIN_DB_PREFIX."returnmgmt_return as t";
		$sql .= " WHERE t.entity IN (".getEntity('returnrequest').")";

		if ($id > 0) {
			$sql .= " AND t.rowid = ".((int) $id);
		} elseif (!empty($ref)) {
			$sql .= " AND t.ref = '".$this->db->escape($ref)."'";
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id                     = $obj->rowid;
				$this->ref                    = $obj->ref;
				$this->entity                 = $obj->entity;
				$this->fk_soc                 = $obj->fk_soc;
				$this->fk_product             = $obj->fk_product;
				$this->serial_number          = $obj->serial_number;
				$this->return_reason          = $obj->return_reason;
				$this->resolution_type        = $obj->resolution_type;
				$this->fk_warehouse           = $obj->fk_warehouse;
				$this->return_tracking        = $obj->return_tracking;
				$this->date_received          = $this->db->jdate($obj->date_received);
				$this->date_resolved          = $this->db->jdate($obj->date_resolved);
				$this->fk_user_assigned       = $obj->fk_user_assigned;
				$this->condition_on_receipt   = $obj->condition_on_receipt;
				$this->refund_amount          = $obj->refund_amount;
				$this->exchange_fk_product    = $obj->exchange_fk_product;
				$this->exchange_serial_number = $obj->exchange_serial_number;
				$this->fk_expedition          = $obj->fk_expedition;
				$this->label                  = $obj->label;
				$this->date_return            = $this->db->jdate($obj->date_return);
				$this->status                 = $obj->status;
				$this->note_private           = $obj->note_private;
				$this->note_public            = $obj->note_public;
				$this->date_creation          = $this->db->jdate($obj->date_creation);
				$this->date_validation        = $this->db->jdate($obj->date_validation);
				$this->date_closed            = $this->db->jdate($obj->date_closed);
				$this->fk_user_creat          = $obj->fk_user_creat;
				$this->fk_user_valid          = $obj->fk_user_valid;
				$this->fk_user_close          = $obj->fk_user_close;
				$this->fk_user_modif          = $obj->fk_user_modif;
				$this->import_key             = $obj->import_key;
				$this->model_pdf              = $obj->model_pdf;
				$this->last_main_doc          = $obj->last_main_doc;

				$this->db->free($resql);

				$this->fetchLines();

				return 1;
			}
			$this->db->free($resql);
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Fetch lines of return request
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function fetchLines()
	{
		$this->lines = array();

		$sql = "SELECT rowid, fk_returnmgmt_return, fk_product, description";
		$sql .= ", qty, serial_number, fk_expedition, fk_expeditiondet, fk_commandedet";
		$sql .= ", fk_entrepot, comment, subprice, total_ht, tva_tx, rang";
		$sql .= " FROM ".MAIN_DB_PREFIX."returnmgmt_return_line";
		$sql .= " WHERE fk_returnmgmt_return = ".((int) $this->id);
		$sql .= " ORDER BY rang ASC, rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$line = new ReturnRequestLine($this->db);
				$line->id                    = $obj->rowid;
				$line->fk_returnmgmt_return  = $obj->fk_returnmgmt_return;
				$line->fk_product            = $obj->fk_product;
				$line->description           = $obj->description;
				$line->qty                   = $obj->qty;
				$line->serial_number         = $obj->serial_number;
				$line->fk_expedition         = $obj->fk_expedition;
				$line->fk_expeditiondet      = $obj->fk_expeditiondet;
				$line->fk_commandedet        = $obj->fk_commandedet;
				$line->fk_entrepot           = $obj->fk_entrepot;
				$line->comment               = $obj->comment;
				$line->subprice              = $obj->subprice;
				$line->total_ht              = $obj->total_ht;
				$line->tva_tx                = $obj->tva_tx;
				$line->rang                  = $obj->rang;
				$this->lines[$i] = $line;
				$i++;
			}
			$this->db->free($resql);
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update return request in database
	 *
	 * @param  User $user      User that modifies
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " fk_soc = ".((int) $this->fk_soc);
		$sql .= ", fk_product = ".(empty($this->fk_product) ? "NULL" : ((int) $this->fk_product));
		$sql .= ", serial_number = ".(empty($this->serial_number) ? "NULL" : "'".$this->db->escape($this->serial_number)."'");
		$sql .= ", return_reason = ".(empty($this->return_reason) ? "NULL" : "'".$this->db->escape($this->return_reason)."'");
		$sql .= ", resolution_type = ".(empty($this->resolution_type) ? "NULL" : "'".$this->db->escape($this->resolution_type)."'");
		$sql .= ", fk_warehouse = ".(empty($this->fk_warehouse) ? "NULL" : ((int) $this->fk_warehouse));
		$sql .= ", return_tracking = ".(empty($this->return_tracking) ? "NULL" : "'".$this->db->escape($this->return_tracking)."'");
		$sql .= ", date_received = ".(empty($this->date_received) ? "NULL" : "'".$this->db->idate($this->date_received)."'");
		$sql .= ", date_resolved = ".(empty($this->date_resolved) ? "NULL" : "'".$this->db->idate($this->date_resolved)."'");
		$sql .= ", fk_user_assigned = ".(empty($this->fk_user_assigned) ? "NULL" : ((int) $this->fk_user_assigned));
		$sql .= ", condition_on_receipt = ".(empty($this->condition_on_receipt) ? "NULL" : "'".$this->db->escape($this->condition_on_receipt)."'");
		$sql .= ", refund_amount = ".(empty($this->refund_amount) ? "NULL" : ((float) $this->refund_amount));
		$sql .= ", exchange_fk_product = ".(empty($this->exchange_fk_product) ? "NULL" : ((int) $this->exchange_fk_product));
		$sql .= ", exchange_serial_number = ".(empty($this->exchange_serial_number) ? "NULL" : "'".$this->db->escape($this->exchange_serial_number)."'");
		$sql .= ", fk_expedition = ".(empty($this->fk_expedition) ? "NULL" : ((int) $this->fk_expedition));
		$sql .= ", label = ".(empty($this->label) ? "NULL" : "'".$this->db->escape($this->label)."'");
		$sql .= ", date_return = ".(empty($this->date_return) ? "NULL" : "'".$this->db->idate($this->date_return)."'");
		$sql .= ", note_private = ".(empty($this->note_private) ? "NULL" : "'".$this->db->escape($this->note_private)."'");
		$sql .= ", note_public = ".(empty($this->note_public) ? "NULL" : "'".$this->db->escape($this->note_public)."'");
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Delete return request from database
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_DELETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		// Delete lines
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."returnmgmt_return_line";
			$sql .= " WHERE fk_returnmgmt_return = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = 'Error '.$this->db->lasterror();
			}
		}

		// Delete extrafields
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."returnmgmt_return_extrafields";
			$sql .= " WHERE fk_object = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = 'Error '.$this->db->lasterror();
			}
		}

		// Delete linked objects
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."element_element";
			$sql .= " WHERE (fk_source = ".((int) $this->id)." AND sourcetype = '".$this->db->escape($this->element)."')";
			$sql .= " OR (fk_target = ".((int) $this->id)." AND targettype = '".$this->db->escape($this->element)."')";
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = 'Error '.$this->db->lasterror();
			}
		}

		// Delete main record
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."returnmgmt_return";
			$sql .= " WHERE rowid = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = 'Error '.$this->db->lasterror();
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Validate (submit) return request: DRAFT -> PENDING
	 *
	 * @param  User $user      User that validates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorReturnRequestNotDraft';
			return -1;
		}

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " status = ".self::STATUS_PENDING;
		$sql .= ", date_validation = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_valid = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_PENDING;
			$this->date_validation = $now;
			$this->fk_user_valid = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_VALIDATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Approve return request: PENDING -> APPROVED
	 *
	 * @param  User $user      User that approves
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function approve($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_PENDING) {
			$this->error = 'ErrorReturnRequestNotPending';
			return -1;
		}

		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " status = ".self::STATUS_APPROVED;
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_APPROVED;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_APPROVE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Reject return request: PENDING -> REJECTED
	 *
	 * @param  User $user      User that rejects
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function reject($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_PENDING) {
			$this->error = 'ErrorReturnRequestNotPending';
			return -1;
		}

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " status = ".self::STATUS_REJECTED;
		$sql .= ", resolution_type = '".self::RESOLUTION_REJECT."'";
		$sql .= ", date_closed = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_close = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_REJECTED;
			$this->resolution_type = self::RESOLUTION_REJECT;
			$this->date_closed = $now;
			$this->fk_user_close = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_REJECT', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Receive items: APPROVED -> RECEIVED
	 * Creates stock movements to add returned qty into warehouse.
	 *
	 * @param  User $user      User that marks as received
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function receive($user, $notrigger = 0)
	{
		global $langs;

		if ($this->status != self::STATUS_APPROVED) {
			$this->error = 'ErrorReturnRequestNotApproved';
			return -1;
		}

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " status = ".self::STATUS_RECEIVED;
		$sql .= ", date_received = '".$this->db->idate($now)."'";
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_RECEIVED;
			$this->date_received = $now;
		}

		// Create stock movements for each line
		if (!$error && isModEnabled('stock')) {
			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

			if (empty($this->lines)) {
				$this->fetchLines();
			}

			$langs->load('returnmgmt@returnmgmt');

			foreach ($this->lines as $line) {
				if ($line->fk_product > 0 && $line->qty > 0) {
					$warehouse_id = $line->fk_entrepot > 0 ? $line->fk_entrepot : $this->fk_warehouse;
					if ($warehouse_id > 0) {
						$mouv = new MouvementStock($this->db);
						$mouv->setOrigin($this->element, $this->id);
						$result = $mouv->reception(
							$user,
							$line->fk_product,
							$warehouse_id,
							$line->qty,
							0,
							$langs->trans('ReturnValidated', $this->ref),
							'',
							'',
							$line->serial_number ? $line->serial_number : ''
						);
						if ($result < 0) {
							$error++;
							$this->errors[] = $mouv->error;
							break;
						}
					}
				}
			}
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_RECEIVE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Start processing: RECEIVED -> PROCESSING
	 *
	 * @param  User $user      User that starts processing
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function process($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_RECEIVED) {
			$this->error = 'ErrorReturnRequestNotReceived';
			return -1;
		}

		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " status = ".self::STATUS_PROCESSING;
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_PROCESSING;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_PROCESS', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Complete return: PROCESSING -> COMPLETED
	 *
	 * @param  User $user      User that completes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function complete($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_PROCESSING) {
			$this->error = 'ErrorReturnRequestNotProcessing';
			return -1;
		}

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return SET";
		$sql .= " status = ".self::STATUS_COMPLETED;
		$sql .= ", date_resolved = '".$this->db->idate($now)."'";
		$sql .= ", date_closed = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_close = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_COMPLETED;
			$this->date_resolved = $now;
			$this->date_closed = $now;
			$this->fk_user_close = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('RETURNMGMT_RETURNREQUEST_COMPLETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Get next free reference for return request
	 *
	 * @return string Next ref or empty on error
	 */
	public function getNextNumRef()
	{
		global $conf, $langs;

		$classname = getDolGlobalString('RETURNMGMT_ADDON', 'mod_returnmgmt_standard');

		$file = DOL_DOCUMENT_ROOT.'/custom/returnmgmt/core/modules/returnmgmt/'.$classname.'.php';
		if (file_exists($file)) {
			require_once $file;
			$obj = new $classname();
			$numref = $obj->getNextValue('', $this);
			if ($numref != '') {
				return $numref;
			}
			$this->error = $obj->error;
			return '';
		}

		$this->error = 'ErrorNumberingModuleNotFound';
		return '';
	}

	/**
	 * Return clickable link of object (with optional picto)
	 *
	 * @param  int    $withpicto           Add picto into link
	 * @param  string $option              Variant of link
	 * @param  int    $notooltip           1=Disable tooltip
	 * @param  string $morecss             More CSS on link
	 * @param  int    $save_lastsearch_value -1=Auto, 0=No save, 1=Save
	 * @return string                      HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs, $conf;

		$url = dol_buildpath('/returnmgmt/returnrequest_card.php', 1).'?id='.$this->id;

		$linkstart = '<a href="'.$url.'"';
		if (empty($notooltip)) {
			$label = '<u>'.$langs->trans('ReturnRequest').'</u><br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
			$linkstart .= ' title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkstart .= ($morecss ? ' class="'.$morecss.'"' : '');
		}
		$linkstart .= '>';
		$linkend = '</a>';

		$result = $linkstart;
		if ($withpicto) {
			$result .= img_object($langs->trans('ReturnRequest'), $this->picto, 'class="paddingright"');
		}
		$result .= $this->ref;
		$result .= $linkend;

		return $result;
	}

	/**
	 * Return status label
	 *
	 * @param  int $mode  0=long, 1=short, 2=picto+short, 3=picto, 4=picto+long, 5=short+picto, 6=long+picto
	 * @return string     Status label
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->status, $mode);
	}

	/**
	 * Return status label for a given status
	 *
	 * @param  int $status Status value
	 * @param  int $mode   Display mode
	 * @return string      Status HTML
	 */
	public static function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('returnmgmt@returnmgmt');

		$statusLabels = array(
			self::STATUS_DRAFT      => array('short' => 'Draft',      'long' => 'StatusDraft',      'type' => 'status0'),
			self::STATUS_PENDING    => array('short' => 'Pending',    'long' => 'StatusPending',    'type' => 'status1'),
			self::STATUS_APPROVED   => array('short' => 'Approved',   'long' => 'StatusApproved',   'type' => 'status3'),
			self::STATUS_RECEIVED   => array('short' => 'Received',   'long' => 'StatusReceived',   'type' => 'status4'),
			self::STATUS_PROCESSING => array('short' => 'Processing', 'long' => 'StatusProcessing', 'type' => 'status4'),
			self::STATUS_COMPLETED  => array('short' => 'Completed',  'long' => 'StatusCompleted',  'type' => 'status6'),
			self::STATUS_REJECTED   => array('short' => 'Rejected',   'long' => 'StatusRejected',   'type' => 'status9'),
		);

		if (!isset($statusLabels[$status])) {
			return '';
		}

		$labelShort = $langs->transnoentitiesaliases($statusLabels[$status]['short']);
		$labelLong = $langs->transnoentitiesaliases($statusLabels[$status]['long']);
		$statusType = $statusLabels[$status]['type'];

		return dolGetStatus($labelLong, $labelShort, '', $statusType, $mode);
	}

	/**
	 * Add a line to the return request
	 *
	 * @param  int    $fk_product       Product ID
	 * @param  double $qty              Quantity
	 * @param  string $description      Description
	 * @param  string $serial_number    Serial number
	 * @param  double $subprice         Unit price
	 * @param  double $tva_tx           Tax rate
	 * @param  int    $fk_expedition    Source shipment ID
	 * @param  int    $fk_expeditiondet Source shipment line ID
	 * @param  int    $fk_commandedet   Source order line ID
	 * @param  int    $fk_entrepot      Warehouse ID
	 * @param  string $comment          Line comment
	 * @return int                      >0 if OK, <0 if KO
	 */
	public function addLine($fk_product, $qty, $description = '', $serial_number = '', $subprice = 0, $tva_tx = 0, $fk_expedition = 0, $fk_expeditiondet = 0, $fk_commandedet = 0, $fk_entrepot = 0, $comment = '')
	{
		$line = new ReturnRequestLine($this->db);
		$line->fk_returnmgmt_return = $this->id;
		$line->fk_product = $fk_product;
		$line->qty = $qty;
		$line->description = $description;
		$line->serial_number = $serial_number;
		$line->subprice = $subprice;
		$line->total_ht = $subprice * $qty;
		$line->tva_tx = $tva_tx;
		$line->fk_expedition = $fk_expedition;
		$line->fk_expeditiondet = $fk_expeditiondet;
		$line->fk_commandedet = $fk_commandedet;
		$line->fk_entrepot = $fk_entrepot;
		$line->comment = $comment;

		$result = $line->insert();
		if ($result > 0) {
			$this->lines[] = $line;
			return $line->id;
		}
		$this->error = $line->error;
		$this->errors = $line->errors;
		return -1;
	}

	/**
	 * Update a line
	 *
	 * @param  int    $lineid        Line ID
	 * @param  int    $fk_product    Product ID
	 * @param  double $qty           Quantity
	 * @param  string $description   Description
	 * @param  string $serial_number Serial number
	 * @param  double $subprice      Unit price
	 * @param  double $tva_tx        Tax rate
	 * @return int                   >0 if OK, <0 if KO
	 */
	public function updateLine($lineid, $fk_product, $qty, $description = '', $serial_number = '', $subprice = 0, $tva_tx = 0)
	{
		$line = new ReturnRequestLine($this->db);
		$line->id = $lineid;
		$line->fk_returnmgmt_return = $this->id;
		$line->fk_product = $fk_product;
		$line->qty = $qty;
		$line->description = $description;
		$line->serial_number = $serial_number;
		$line->subprice = $subprice;
		$line->total_ht = $subprice * $qty;
		$line->tva_tx = $tva_tx;

		return $line->update();
	}

	/**
	 * Delete a line
	 *
	 * @param  int $lineid Line ID
	 * @return int         >0 if OK, <0 if KO
	 */
	public function deleteLine($lineid)
	{
		$line = new ReturnRequestLine($this->db);
		$line->id = $lineid;
		return $line->delete();
	}

	/**
	 * Get the linked invoice by traversing element_element:
	 * expedition → commande → facture
	 *
	 * @return int Invoice ID or 0 if not found
	 */
	public function getLinkedInvoice()
	{
		if (empty($this->fk_expedition)) {
			return 0;
		}

		// Step 1: expedition → commande (sourcetype='commande', targettype='shipping')
		$sql = "SELECT el.fk_source AS fk_commande";
		$sql .= " FROM ".MAIN_DB_PREFIX."element_element el";
		$sql .= " WHERE el.fk_target = ".((int) $this->fk_expedition);
		$sql .= " AND el.targettype = 'shipping'";
		$sql .= " AND el.sourcetype = 'commande'";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql || !$this->db->num_rows($resql)) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$fk_commande = (int) $obj->fk_commande;

		// Step 2: commande → facture (sourcetype='commande', targettype='facture')
		$sql2 = "SELECT el.fk_target AS fk_facture";
		$sql2 .= " FROM ".MAIN_DB_PREFIX."element_element el";
		$sql2 .= " WHERE el.fk_source = ".((int) $fk_commande);
		$sql2 .= " AND el.sourcetype = 'commande'";
		$sql2 .= " AND el.targettype = 'facture'";
		$sql2 .= " LIMIT 1";

		$resql2 = $this->db->query($sql2);
		if (!$resql2 || !$this->db->num_rows($resql2)) {
			return 0;
		}
		$obj2 = $this->db->fetch_object($resql2);
		return (int) $obj2->fk_facture;
	}
}
