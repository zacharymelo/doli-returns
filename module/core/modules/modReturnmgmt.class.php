<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modReturnmgmt extends DolibarrModules
{
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 520000;
		$this->family = 'crm';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Product return management with refund, exchange, repair and rejection workflows';
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'technic';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array('elementproperties', 'productcard', 'commonobject', 'expeditioncard'),
				'entity' => '0',
			),
		);

		$this->dirs = array('/returnmgmt/temp');
		$this->config_page_url = array('setup.php@returnmgmt');

		$this->depends = array('modSociete', 'modProduct', 'modStock');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('returnmgmt@returnmgmt');

		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		// Constants
		$this->const = array(
			array('RETURNMGMT_ADDON', 'chaine', 'mod_returnmgmt_standard', 'Name of numbering module for return requests', 0),
			array('RETURNMGMT_RETURN_WINDOW_DAYS', 'chaine', '30', 'Number of days after purchase a return is accepted', 0),
		);

		// Permissions
		$this->rights = array();
		$this->rights_class = 'returnmgmt';
		$r = 0;

		$r++;
		$this->rights[$r][0] = 520001;
		$this->rights[$r][1] = 'Read return requests';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'returnrequest';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 520002;
		$this->rights[$r][1] = 'Create/edit return requests';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'returnrequest';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = 520003;
		$this->rights[$r][1] = 'Delete return requests';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'returnrequest';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = 520004;
		$this->rights[$r][1] = 'Approve or reject return requests';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'returnrequest';
		$this->rights[$r][5] = 'approve';

		$r++;
		$this->rights[$r][0] = 520005;
		$this->rights[$r][1] = 'Complete/close return requests';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'returnrequest';
		$this->rights[$r][5] = 'close';

		// Menus
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r++] = array(
			'fk_menu'  => 0,
			'type'     => 'top',
			'titre'    => 'Returns',
			'prefix'   => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'returnmgmt',
			'leftmenu' => '',
			'url'      => '/returnmgmt/returnrequest_list.php',
			'langs'    => 'returnmgmt@returnmgmt',
			'position' => 100,
			'enabled'  => 'isModEnabled("returnmgmt")',
			'perms'    => '$user->hasRight("returnmgmt", "returnrequest", "read")',
			'target'   => '',
			'user'     => 0,
		);

		// Left: Return Requests list
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=returnmgmt',
			'type'     => 'left',
			'titre'    => 'ReturnRequestList',
			'mainmenu' => 'returnmgmt',
			'leftmenu' => 'returnmgmt_returnrequest_list',
			'url'      => '/returnmgmt/returnrequest_list.php',
			'langs'    => 'returnmgmt@returnmgmt',
			'position' => 100,
			'enabled'  => 'isModEnabled("returnmgmt")',
			'perms'    => '$user->hasRight("returnmgmt", "returnrequest", "read")',
			'target'   => '',
			'user'     => 0,
		);

		// Left: New Return Request
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=returnmgmt,fk_leftmenu=returnmgmt_returnrequest_list',
			'type'     => 'left',
			'titre'    => 'NewReturnRequest',
			'mainmenu' => 'returnmgmt',
			'leftmenu' => 'returnmgmt_returnrequest_new',
			'url'      => '/returnmgmt/returnrequest_card.php?action=create',
			'langs'    => 'returnmgmt@returnmgmt',
			'position' => 101,
			'enabled'  => 'isModEnabled("returnmgmt")',
			'perms'    => '$user->hasRight("returnmgmt", "returnrequest", "write")',
			'target'   => '',
			'user'     => 0,
		);
	}

	public function init($options = '')
	{
		$result = $this->_load_tables('/returnmgmt/sql/');
		if ($result < 0) {
			return -1;
		}

		// Clean old menus before _init() calls insert_menus() to avoid duplicates on re-enable
		$this->delete_menus();

		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
