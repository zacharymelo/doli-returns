<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

class ReturnRequestLine extends CommonObjectLine
{
	public $element = 'returnrequestline';
	public $table_element = 'returnmgmt_return_line';
	public $fk_element = 'fk_returnmgmt_return';

	public $fk_returnmgmt_return;
	public $fk_product;
	public $description;
	public $qty;
	public $serial_number;
	public $subprice;
	public $total_ht;
	public $tva_tx;
	public $rang;

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
	 * Insert line into database
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function insert()
	{
		$error = 0;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."returnmgmt_return_line (";
		$sql .= "fk_returnmgmt_return, fk_product, description, qty";
		$sql .= ", serial_number, subprice, total_ht, tva_tx, rang, date_creation";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->fk_returnmgmt_return);
		$sql .= ", ".(empty($this->fk_product) ? "NULL" : ((int) $this->fk_product));
		$sql .= ", ".(empty($this->description) ? "NULL" : "'".$this->db->escape($this->description)."'");
		$sql .= ", ".((float) $this->qty);
		$sql .= ", ".(empty($this->serial_number) ? "NULL" : "'".$this->db->escape($this->serial_number)."'");
		$sql .= ", ".((float) $this->subprice);
		$sql .= ", ".((float) $this->total_ht);
		$sql .= ", ".((float) $this->tva_tx);
		$sql .= ", ".((int) $this->rang);
		$sql .= ", '".$this->db->idate(dol_now())."'";
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."returnmgmt_return_line");
			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update line in database
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function update()
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."returnmgmt_return_line SET";
		$sql .= " fk_product = ".(empty($this->fk_product) ? "NULL" : ((int) $this->fk_product));
		$sql .= ", description = ".(empty($this->description) ? "NULL" : "'".$this->db->escape($this->description)."'");
		$sql .= ", qty = ".((float) $this->qty);
		$sql .= ", serial_number = ".(empty($this->serial_number) ? "NULL" : "'".$this->db->escape($this->serial_number)."'");
		$sql .= ", subprice = ".((float) $this->subprice);
		$sql .= ", total_ht = ".((float) $this->total_ht);
		$sql .= ", tva_tx = ".((float) $this->tva_tx);
		$sql .= ", rang = ".((int) $this->rang);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Delete line from database
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function delete()
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."returnmgmt_return_line";
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}
}
