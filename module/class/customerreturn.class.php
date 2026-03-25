<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/customerreturn/class/customerreturnline.class.php';

class CustomerReturn extends CommonObject
{
	public $TRIGGER_PREFIX = 'CUSTOMERRETURN';
	public $element = 'customerreturn';
	public $table_element = 'customer_return';
	public $picto = 'dollyrevert';
	protected $table_ref_field = 'ref';

	public $table_element_line = 'customer_return_line';
	public $class_element_line = 'CustomerReturnLine';
	public $fk_element = 'fk_customer_return';

	// Status constants
	const STATUS_DRAFT     = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CLOSED    = 2;

	// DB fields
	public $ref;
	public $entity;
	public $fk_soc;
	public $fk_commande;
	public $fk_expedition;
	public $fk_project;
	public $fk_warehouse;
	public $label;
	public $return_date;
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
	 * Create customer return in database
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

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."customer_return (";
		$sql .= "ref, entity, fk_soc, fk_commande, fk_expedition, fk_project";
		$sql .= ", fk_warehouse, label, return_date";
		$sql .= ", status, note_private, note_public";
		$sql .= ", date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."'";
		$sql .= ", ".((int) $conf->entity);
		$sql .= ", ".((int) $this->fk_soc);
		$sql .= ", ".(empty($this->fk_commande) ? "NULL" : ((int) $this->fk_commande));
		$sql .= ", ".(empty($this->fk_expedition) ? "NULL" : ((int) $this->fk_expedition));
		$sql .= ", ".(empty($this->fk_project) ? "NULL" : ((int) $this->fk_project));
		$sql .= ", ".(empty($this->fk_warehouse) ? "NULL" : ((int) $this->fk_warehouse));
		$sql .= ", ".(empty($this->label) ? "NULL" : "'".$this->db->escape($this->label)."'");
		$sql .= ", ".(empty($this->return_date) ? "NULL" : "'".$this->db->idate($this->return_date)."'");
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
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."customer_return");
			$this->status = self::STATUS_DRAFT;
			$this->date_creation = $now;
			$this->fk_user_creat = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('CUSTOMERRETURN_CUSTOMERRETURN_CREATE', $user);
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
		$sql = "SELECT t.rowid, t.ref, t.entity, t.fk_soc, t.fk_commande";
		$sql .= ", t.fk_expedition, t.fk_project, t.fk_warehouse";
		$sql .= ", t.label, t.return_date";
		$sql .= ", t.status, t.note_private, t.note_public";
		$sql .= ", t.date_creation, t.date_validation, t.date_closed, t.tms";
		$sql .= ", t.fk_user_creat, t.fk_user_valid, t.fk_user_close, t.fk_user_modif";
		$sql .= ", t.import_key, t.model_pdf, t.last_main_doc";
		$sql .= " FROM ".MAIN_DB_PREFIX."customer_return as t";
		$sql .= " WHERE t.entity IN (".getEntity('customerreturn').")";

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

				$this->id              = $obj->rowid;
				$this->ref             = $obj->ref;
				$this->entity          = $obj->entity;
				$this->fk_soc          = $obj->fk_soc;
				$this->fk_commande     = $obj->fk_commande;
				$this->fk_expedition   = $obj->fk_expedition;
				$this->fk_project      = $obj->fk_project;
				$this->fk_warehouse    = $obj->fk_warehouse;
				$this->label           = $obj->label;
				$this->return_date     = $this->db->jdate($obj->return_date);
				$this->status          = $obj->status;
				$this->note_private    = $obj->note_private;
				$this->note_public     = $obj->note_public;
				$this->date_creation   = $this->db->jdate($obj->date_creation);
				$this->date_validation = $this->db->jdate($obj->date_validation);
				$this->date_closed     = $this->db->jdate($obj->date_closed);
				$this->fk_user_creat   = $obj->fk_user_creat;
				$this->fk_user_valid   = $obj->fk_user_valid;
				$this->fk_user_close   = $obj->fk_user_close;
				$this->fk_user_modif   = $obj->fk_user_modif;
				$this->import_key      = $obj->import_key;
				$this->model_pdf       = $obj->model_pdf;
				$this->last_main_doc   = $obj->last_main_doc;

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
	 * Fetch lines of customer return
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function fetchLines()
	{
		$this->lines = array();

		$sql = "SELECT rowid, fk_customer_return, fk_product, description";
		$sql .= ", qty, serial_number, fk_expedition, fk_expeditiondet, fk_commandedet";
		$sql .= ", fk_entrepot, comment, subprice, total_ht, tva_tx, rang";
		$sql .= " FROM ".MAIN_DB_PREFIX."customer_return_line";
		$sql .= " WHERE fk_customer_return = ".((int) $this->id);
		$sql .= " ORDER BY rang ASC, rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$line = new CustomerReturnLine($this->db);
				$line->id                   = $obj->rowid;
				$line->fk_customer_return   = $obj->fk_customer_return;
				$line->fk_product           = $obj->fk_product;
				$line->description          = $obj->description;
				$line->qty                  = $obj->qty;
				$line->serial_number        = $obj->serial_number;
				$line->fk_expedition        = $obj->fk_expedition;
				$line->fk_expeditiondet     = $obj->fk_expeditiondet;
				$line->fk_commandedet       = $obj->fk_commandedet;
				$line->fk_entrepot          = $obj->fk_entrepot;
				$line->comment              = $obj->comment;
				$line->subprice             = $obj->subprice;
				$line->total_ht             = $obj->total_ht;
				$line->tva_tx               = $obj->tva_tx;
				$line->rang                 = $obj->rang;
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
	 * Update customer return in database
	 *
	 * @param  User $user      User that modifies
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."customer_return SET";
		$sql .= " fk_soc = ".((int) $this->fk_soc);
		$sql .= ", fk_commande = ".(empty($this->fk_commande) ? "NULL" : ((int) $this->fk_commande));
		$sql .= ", fk_expedition = ".(empty($this->fk_expedition) ? "NULL" : ((int) $this->fk_expedition));
		$sql .= ", fk_project = ".(empty($this->fk_project) ? "NULL" : ((int) $this->fk_project));
		$sql .= ", fk_warehouse = ".(empty($this->fk_warehouse) ? "NULL" : ((int) $this->fk_warehouse));
		$sql .= ", label = ".(empty($this->label) ? "NULL" : "'".$this->db->escape($this->label)."'");
		$sql .= ", return_date = ".(empty($this->return_date) ? "NULL" : "'".$this->db->idate($this->return_date)."'");
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
			$result = $this->call_trigger('CUSTOMERRETURN_CUSTOMERRETURN_MODIFY', $user);
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
	 * Delete customer return from database
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
			$result = $this->call_trigger('CUSTOMERRETURN_CUSTOMERRETURN_DELETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		// Delete lines
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."customer_return_line";
			$sql .= " WHERE fk_customer_return = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = 'Error '.$this->db->lasterror();
			}
		}

		// Delete extrafields
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."customer_return_extrafields";
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
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."customer_return";
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
	 * Validate customer return: DRAFT -> VALIDATED
	 * Creates stock movements to add returned qty into warehouse.
	 *
	 * @param  User $user      User that validates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorCustomerReturnNotDraft';
			return -1;
		}

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."customer_return SET";
		$sql .= " status = ".self::STATUS_VALIDATED;
		$sql .= ", date_validation = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_valid = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_VALIDATED;
			$this->date_validation = $now;
			$this->fk_user_valid = $user->id;
		}

		// Create stock movements for each line
		if (!$error && isModEnabled('stock')) {
			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

			if (empty($this->lines)) {
				$this->fetchLines();
			}

			global $langs;
			$langs->load('customerreturn@customerreturn');

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
			$result = $this->call_trigger('CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE', $user);
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
	 * Close customer return: VALIDATED -> CLOSED
	 *
	 * @param  User $user      User that closes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function close($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_VALIDATED) {
			$this->error = 'ErrorCustomerReturnNotValidated';
			return -1;
		}

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."customer_return SET";
		$sql .= " status = ".self::STATUS_CLOSED;
		$sql .= ", date_closed = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_close = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_CLOSED;
			$this->date_closed = $now;
			$this->fk_user_close = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('CUSTOMERRETURN_CUSTOMERRETURN_CLOSE', $user);
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
	 * Reopen customer return: VALIDATED -> DRAFT
	 *
	 * @param  User $user      User that reopens
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function reopen($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_VALIDATED) {
			$this->error = 'ErrorCustomerReturnNotValidated';
			return -1;
		}

		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."customer_return SET";
		$sql .= " status = ".self::STATUS_DRAFT;
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = 'Error '.$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_DRAFT;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('CUSTOMERRETURN_CUSTOMERRETURN_REOPEN', $user);
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
	 * Get next free reference for customer return
	 *
	 * @return string Next ref or empty on error
	 */
	public function getNextNumRef()
	{
		global $conf, $langs;

		$classname = getDolGlobalString('CUSTOMERRETURN_ADDON', 'mod_customerreturn_standard');

		$file = DOL_DOCUMENT_ROOT.'/custom/customerreturn/core/modules/customerreturn/'.$classname.'.php';
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

		$url = dol_buildpath('/customerreturn/customerreturn_card.php', 1).'?id='.$this->id;

		$linkstart = '<a href="'.$url.'"';
		if (empty($notooltip)) {
			$label = '<u>'.$langs->trans('CustomerReturn').'</u><br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
			$linkstart .= ' title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkstart .= ($morecss ? ' class="'.$morecss.'"' : '');
		}
		$linkstart .= '>';
		$linkend = '</a>';

		$result = $linkstart;
		if ($withpicto) {
			$result .= img_object($langs->trans('CustomerReturn'), $this->picto, 'class="paddingright"');
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
		$langs->load('customerreturn@customerreturn');

		$statusLabels = array(
			self::STATUS_DRAFT     => array('short' => 'Draft',     'long' => 'StatusDraft',     'type' => 'status0'),
			self::STATUS_VALIDATED => array('short' => 'Validated', 'long' => 'StatusValidated', 'type' => 'status4'),
			self::STATUS_CLOSED    => array('short' => 'Closed',    'long' => 'StatusClosed',    'type' => 'status6'),
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
	 * Add a line to the customer return
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
		$line = new CustomerReturnLine($this->db);
		$line->fk_customer_return = $this->id;
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
		$line = new CustomerReturnLine($this->db);
		$line->id = $lineid;
		$line->fk_customer_return = $this->id;
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
		$line = new CustomerReturnLine($this->db);
		$line->id = $lineid;
		return $line->delete();
	}

	/**
	 * Get the linked invoice by traversing element_element:
	 * expedition -> commande -> facture
	 *
	 * @return int Invoice ID or 0 if not found
	 */
	public function getLinkedInvoice()
	{
		if (empty($this->fk_expedition)) {
			return 0;
		}

		// Step 1: expedition -> commande
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

		// Step 2: commande -> facture
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
