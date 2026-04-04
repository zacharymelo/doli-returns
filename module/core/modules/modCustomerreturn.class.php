<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modCustomerreturn
 *
 * Module descriptor for the customerreturn module
 */
class modCustomerreturn extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 510100;
		$this->family = 'crm';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Customer merchandise return management with stock movement tracking and credit note generation';
		$this->version = '2.2.8';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'dollyrevert';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array('elementproperties', 'productcard', 'commonobject', 'expeditioncard'),
				'entity' => '0',
			),
		);

		$this->dirs = array('/customerreturn/temp');
		$this->config_page_url = array('setup.php@customerreturn');

		$this->depends = array('modSociete', 'modProduct', 'modStock');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('customerreturn@customerreturn');

		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		// Constants
		$this->const = array(
			array('CUSTOMERRETURN_ADDON', 'chaine', 'mod_customerreturn_standard', 'Name of numbering module for customer returns', 0),
		);

		// Permissions
		$this->rights = array();
		$this->rights_class = 'customerreturn';
		$r = 0;

		$r++;
		$this->rights[$r][0] = 510101;
		$this->rights[$r][1] = 'Read customer returns';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'customerreturn';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 510102;
		$this->rights[$r][1] = 'Create/edit customer returns';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'customerreturn';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = 510103;
		$this->rights[$r][1] = 'Delete customer returns';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'customerreturn';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = 510104;
		$this->rights[$r][1] = 'Validate customer returns';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'customerreturn';
		$this->rights[$r][5] = 'validate';

		$r++;
		$this->rights[$r][0] = 510105;
		$this->rights[$r][1] = 'Close customer returns';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'customerreturn';
		$this->rights[$r][5] = 'close';

		// Menus — inject under Products sidebar, after Receptions
		$this->menu = array();
		$r = 0;

		// Heading: Customer Returns (level 0 under Products, same level as Shipments/Receptions)
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=products',
			'type'     => 'left',
			'titre'    => 'CustomerReturns',
			'prefix'   => img_picto('', 'dollyrevert', 'class="em092 flip infobox-order_supplier pictofixedwidth"'),
			'mainmenu' => 'products',
			'leftmenu' => 'customerreturns',
			'url'      => '/customerreturn/customerreturn_list.php',
			'langs'    => 'customerreturn@customerreturn',
			'position' => 2700,
			'enabled'  => 'isModEnabled("customerreturn")',
			'perms'    => '$user->hasRight("customerreturn", "customerreturn", "read")',
			'target'   => '',
			'user'     => 0,
			'level'    => 0,
		);

		// Child: New Customer Return
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=products,fk_leftmenu=customerreturns',
			'type'     => 'left',
			'titre'    => 'NewCustomerReturn',
			'mainmenu' => 'products',
			'leftmenu' => 'customerreturn_new',
			'url'      => '/customerreturn/customerreturn_card.php?action=create',
			'langs'    => 'customerreturn@customerreturn',
			'position' => 2701,
			'enabled'  => 'isModEnabled("customerreturn")',
			'perms'    => '$user->hasRight("customerreturn", "customerreturn", "write")',
			'target'   => '',
			'user'     => 0,
		);

		// Child: List
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=products,fk_leftmenu=customerreturns',
			'type'     => 'left',
			'titre'    => 'List',
			'mainmenu' => 'products',
			'leftmenu' => 'customerreturn_listall',
			'url'      => '/customerreturn/customerreturn_list.php',
			'langs'    => 'customerreturn@customerreturn',
			'position' => 2702,
			'enabled'  => 'isModEnabled("customerreturn")',
			'perms'    => '$user->hasRight("customerreturn", "customerreturn", "read")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled
	 *
	 * @param  string $options Options when enabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/customerreturn/sql/');
		if ($result < 0) {
			return -1;
		}

		// Clean old menus before _init() calls insert_menus() to avoid duplicates on re-enable
		$this->delete_menus();

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled
	 *
	 * @param  string $options Options when disabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
