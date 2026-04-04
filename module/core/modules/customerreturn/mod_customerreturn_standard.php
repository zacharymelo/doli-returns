<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/customerreturn/core/modules/customerreturn/modules_customerreturn.php';

/**
 * Class mod_customerreturn_standard
 *
 * Standard numbering model for customer returns
 */
class mod_customerreturn_standard extends ModeleNumRefCustomerreturn
{
	public $version = '1.0.0';
	public $name = 'standard';
	public $prefix = 'RT';

	/**
	 * Return an example of numbering
	 *
	 * @return string Example
	 */
	public function getExample()
	{
		return $this->prefix.'-'.dol_print_date(dol_now(), '%y%m').'-0001';
	}

	/**
	 * Return next value for ref
	 *
	 * @param  Societe $objsoc  Third party object
	 * @param  Object  $object  Object to get next ref for
	 * @return string           Next ref value or error
	 */
	public function getNextValue($objsoc = '', $object = '')
	{
		global $db, $conf;

		$date = (!empty($object->date_creation) ? $object->date_creation : dol_now());
		$ym = dol_print_date($date, '%y%m');

		$posidx = strlen($this->prefix) + 6; // prefix + dash + 4-digit YYMM + dash

		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posidx.") AS SIGNED)) as max_num";
		$sql .= " FROM ".MAIN_DB_PREFIX."customer_return";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."-".$ym."-%'";
		$sql .= " AND entity = ".((int) $conf->entity);

		$max = 0;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = (int) $obj->max_num;
			}
			$db->free($resql);
		}

		return $this->prefix.'-'.$ym.'-'.sprintf('%04d', $max + 1);
	}
}
