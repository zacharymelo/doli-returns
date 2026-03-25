<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

abstract class ModeleNumRefReturnmgmt extends CommonNumRefGenerator
{
	/**
	 * Return next value
	 *
	 * @param  Societe $objsoc  Third party object
	 * @param  Object  $object  Object to get next ref for
	 * @return string           Next ref value
	 */
	abstract public function getNextValue($objsoc = '', $object = '');

	/**
	 * Return an example of numbering
	 *
	 * @return string Example
	 */
	abstract public function getExample();
}

abstract class ModelePDFReturnmgmt extends CommonDocGenerator
{
	/**
	 * Return list of active generation modules
	 *
	 * @param  DoliDB $db          Database handler
	 * @param  int    $maxfilename Max length of value to show
	 * @return array               List of templates
	 */
	public static function liste_modeles($db, $maxfilename = 0)
	{
		return parent::liste_modeles_genx($db, $maxfilename, array(), 'returnmgmt');
	}
}
