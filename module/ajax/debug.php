<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/debug.php
 * \ingroup customerreturn
 * \brief   Debug diagnostics for customerreturn module.
 *          Gated by admin permission + CUSTOMERRETURN_DEBUG_MODE setting.
 *
 * Modes (via ?mode=):
 *   overview    — Module config, DB tables, element properties, templates (default)
 *   object      — Deep inspect a single return (?mode=object&id=2)
 *   links       — All element_element rows involving this module
 *   settings    — All CUSTOMERRETURN_* constants
 *   classes     — Class loading + method checks
 *   sql         — Read-only diagnostic query (?mode=sql&q=SELECT...)
 *   triggers    — Trigger registration and handled events
 *   hooks       — Hook contexts and actions class methods
 *   all         — Run every diagnostic at once
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->admin) { http_response_code(403); print 'Admin only'; exit; }
if (!getDolGlobalInt('CUSTOMERRETURN_DEBUG_MODE')) {
	http_response_code(403);
	print 'Debug mode not enabled. Go to Customer Returns > Setup and enable Debug Mode.';
	exit;
}

header('Content-Type: text/plain; charset=utf-8');

$mode = GETPOST('mode', 'alpha') ?: 'overview';
$run_all = ($mode === 'all');

$MODULE_NAME  = 'customerreturn';
$MODULE_UPPER = 'CUSTOMERRETURN';
$OBJECTS = array(
	'customerreturn' => array(
		'class'     => 'CustomerReturn',
		'classfile' => 'customerreturn',
		'table'     => 'customer_return',
		'prefixed'  => 'customerreturn_customerreturn',
		'fk_fields' => array('fk_soc', 'fk_expedition', 'fk_commande', 'fk_project', 'fk_warehouse', 'fk_user_creat', 'fk_user_valid', 'fk_user_close'),
	),
);

print "=== CUSTOMERRETURN DEBUG DIAGNOSTICS ===\n";
print "Timestamp: ".date('Y-m-d H:i:s T')."\n";
print "Dolibarr: ".(defined('DOL_VERSION') ? DOL_VERSION : 'unknown')."\n";
print "Mode: $mode\n";
print "Usage: ?mode=overview|object|links|settings|classes|sql|triggers|hooks|all\n";
print "       ?mode=object&id=2\n";
print "       ?mode=sql&q=SELECT+rowid,ref+FROM+llx_customer_return+LIMIT+5\n";
print str_repeat('=', 60)."\n\n";


// =====================================================================
// OVERVIEW
// =====================================================================
if ($mode === 'overview' || $run_all) {
	print "--- MODULE STATUS ---\n";
	print "isModEnabled('customerreturn'): ".(isModEnabled('customerreturn') ? 'YES' : 'NO')."\n";

	// DB tables
	print "\n--- DATABASE TABLES ---\n";
	foreach (array('customer_return', 'customer_return_line', 'customer_return_extrafields') as $tbl) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$tbl;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "  llx_$tbl: ".$obj->cnt." rows\n";
		} else {
			print "  llx_$tbl: TABLE MISSING OR ERROR\n";
		}
	}

	// Element properties
	print "\n--- ELEMENT PROPERTIES ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		foreach (array($bare, $odef['prefixed']) as $etype) {
			$props = getElementProperties($etype);
			$ok = (!empty($props['classname']) && $props['classname'] === $odef['class']);
			print "  $etype → classname=".$props['classname']." ".($ok ? 'OK' : 'MISMATCH (expected '.$odef['class'].')')."\n";
		}
	}

	// Templates
	print "\n--- LINKED OBJECT TEMPLATES ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		$tplpath = $MODULE_NAME.'/'.$bare.'/tpl/linkedobjectblock.tpl.php';
		$fullpath = dol_buildpath('/'.$tplpath);
		print "  $tplpath: ".(file_exists($fullpath) ? 'EXISTS' : 'MISSING ('.$fullpath.')')."\n";
	}
	print "\n";
}


// =====================================================================
// OBJECT
// =====================================================================
if ($mode === 'object' || $run_all) {
	$oid = GETPOSTINT('id');
	if ($oid <= 0 && !$run_all) {
		print "--- OBJECT DIAGNOSIS ---\nUsage: ?mode=object&id=2\n\n";
	} elseif ($oid > 0) {
		print "--- OBJECT DIAGNOSIS: customerreturn id=$oid ---\n";
		dol_include_once('/'.$MODULE_NAME.'/class/customerreturn.class.php');

		if (!class_exists('CustomerReturn')) {
			print "  Class CustomerReturn NOT FOUND!\n\n";
		} else {
			$obj = new CustomerReturn($db);
			$fetch_result = $obj->fetch($oid);
			print "  fetch(): $fetch_result\n";

			if ($fetch_result > 0) {
				print "  ref: $obj->ref\n";
				print "  element: $obj->element\n";
				print "  module: ".(property_exists($obj, 'module') ? ($obj->module ?: '(empty)') : '(NOT DEFINED)')."\n";
				print "  getElementType(): ".$obj->getElementType()."\n";
				print "  getNomUrl(): ".(method_exists($obj, 'getNomUrl') ? 'defined' : 'MISSING')."\n";
				print "  getLibStatut(): ".(method_exists($obj, 'getLibStatut') ? 'defined' : 'MISSING')."\n";
				print "  status: $obj->status\n";

				// FK fields
				print "\n  FK fields (non-empty):\n";
				$odef = $OBJECTS['customerreturn'];
				$has_fk = false;
				foreach ($odef['fk_fields'] as $fk) {
					$val = isset($obj->$fk) ? $obj->$fk : null;
					if (!empty($val)) {
						print "    $fk = $val\n";
						$has_fk = true;
					}
				}
				if (!$has_fk) print "    (none populated)\n";

				// element_element rows
				$etype = $obj->getElementType();
				$bare = $obj->element;
				print "\n  element_element rows:\n";
				$where_parts = array();
				foreach (array($etype, $bare) as $st) {
					$where_parts[] = "(fk_source = $oid AND sourcetype = '".$db->escape($st)."')";
					$where_parts[] = "(fk_target = $oid AND targettype = '".$db->escape($st)."')";
				}
				$where_parts[] = "(fk_source = $oid AND sourcetype LIKE '%customerreturn%')";
				$where_parts[] = "(fk_target = $oid AND targettype LIKE '%customerreturn%')";

				$sql = "SELECT DISTINCT rowid, fk_source, sourcetype, fk_target, targettype"
					." FROM ".MAIN_DB_PREFIX."element_element"
					." WHERE ".implode(" OR ", $where_parts)
					." ORDER BY rowid";
				$resql = $db->query($sql);
				if ($resql) {
					$cnt = 0;
					while ($row = $db->fetch_object($resql)) {
						$cnt++;
						print "    [$row->rowid] source=$row->fk_source ($row->sourcetype) → target=$row->fk_target ($row->targettype)\n";
					}
					if ($cnt == 0) print "    (none)\n";
				}

				// fetchObjectLinked
				print "\n  fetchObjectLinked():\n";
				$obj->fetchObjectLinked();
				if (!empty($obj->linkedObjectsIds)) {
					foreach ($obj->linkedObjectsIds as $ltype => $lids) {
						print "    linkedObjectsIds[$ltype]: ".implode(', ', $lids)."\n";
					}
				} else {
					print "    linkedObjectsIds: (empty)\n";
				}
				if (!empty($obj->linkedObjects)) {
					foreach ($obj->linkedObjects as $ltype => $lobjs) {
						foreach ($lobjs as $lkey => $lobj) {
							print "    linkedObjects[$ltype][$lkey]: ".get_class($lobj)." ref=".$lobj->ref."\n";
						}
					}
				} else {
					print "    linkedObjects: (empty)\n";
				}

				// Lines
				print "\n  Lines: ".count($obj->lines)."\n";
				foreach ($obj->lines as $i => $line) {
					print "    [$i] product=".$line->fk_product." qty=".$line->qty." serial=".$line->serial_number." warehouse=".$line->fk_entrepot."\n";
				}
			}
		}
		print "\n";
	}
}


// =====================================================================
// LINKS
// =====================================================================
if ($mode === 'links' || $run_all) {
	print "--- ALL ELEMENT_ELEMENT ROWS FOR CUSTOMERRETURN ---\n";
	$sql = "SELECT rowid, fk_source, sourcetype, fk_target, targettype"
		." FROM ".MAIN_DB_PREFIX."element_element"
		." WHERE sourcetype LIKE '%customerreturn%' OR targettype LIKE '%customerreturn%'"
		." ORDER BY rowid DESC LIMIT 50";
	$resql = $db->query($sql);
	if ($resql) {
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			$cnt++;
			print "  [$row->rowid] source=$row->fk_source ($row->sourcetype) → target=$row->fk_target ($row->targettype)\n";
		}
		print "  Total: $cnt rows (max 50)\n";
	}
	print "\n";
}


// =====================================================================
// SETTINGS
// =====================================================================
if ($mode === 'settings' || $run_all) {
	print "--- CUSTOMERRETURN SETTINGS ---\n";
	$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const"
		." WHERE name LIKE '".$MODULE_UPPER."%'"
		." AND entity IN (0, ".((int) $conf->entity).")"
		." ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		while ($row = $db->fetch_object($resql)) {
			print "  $row->name = $row->value\n";
		}
	}
	print "\n";
}


// =====================================================================
// CLASSES
// =====================================================================
if ($mode === 'classes' || $run_all) {
	print "--- CLASS LOADING & METHODS ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		print "  $bare ({$odef['class']}):\n";
		$inc = @dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
		print "    dol_include_once: ".($inc ? 'OK' : 'FAILED')."\n";
		print "    class_exists: ".(class_exists($odef['class']) ? 'YES' : 'NO')."\n";
		if (class_exists($odef['class'])) {
			$obj = new $odef['class']($db);
			print "    \$module: ".(property_exists($obj, 'module') ? ($obj->module ?: '(empty)') : 'NOT DEFINED')."\n";
			print "    \$element: ".$obj->element."\n";
			print "    getElementType(): ".$obj->getElementType()."\n";
			$required = array('create', 'fetch', 'update', 'delete', 'validate', 'getNomUrl', 'getLibStatut');
			$missing = array();
			foreach ($required as $m) {
				if (!method_exists($obj, $m)) $missing[] = $m;
			}
			print "    Required methods: ".(empty($missing) ? 'ALL PRESENT' : 'MISSING: '.implode(', ', $missing))."\n";
		}
		print "\n";
	}
}


// =====================================================================
// SQL
// =====================================================================
if ($mode === 'sql') {
	$q = GETPOST('q', 'restricthtml');
	print "--- SQL QUERY ---\n";
	if (empty($q)) {
		print "Usage: ?mode=sql&q=SELECT+rowid,ref+FROM+llx_customer_return+LIMIT+5\n\n";
		print "Useful queries:\n";
		print "  ?mode=sql&q=SELECT rowid,ref,fk_soc,fk_expedition,status FROM llx_customer_return ORDER BY rowid DESC LIMIT 10\n";
		print "  ?mode=sql&q=SELECT * FROM llx_customer_return_line ORDER BY rowid DESC LIMIT 10\n";
		print "  ?mode=sql&q=SELECT * FROM llx_element_element WHERE sourcetype LIKE '%customerreturn%' OR targettype LIKE '%customerreturn%' ORDER BY rowid DESC LIMIT 20\n";
	} else {
		$q_trimmed = trim($q);
		if (stripos($q_trimmed, 'SELECT') !== 0) {
			print "ERROR: Only SELECT queries allowed.\n";
		} else {
			$blocked = array('INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT');
			$safe = true;
			foreach ($blocked as $kw) {
				if (stripos($q_trimmed, $kw) !== false && stripos($q_trimmed, $kw) !== stripos($q_trimmed, 'SELECT')) {
					$safe = false;
					break;
				}
			}
			if (!$safe) {
				print "ERROR: Query contains blocked keywords.\n";
			} else {
				if (stripos($q_trimmed, 'LIMIT') === false) $q_trimmed .= ' LIMIT 50';
				print "Query: $q_trimmed\n\n";
				$resql = $db->query($q_trimmed);
				if ($resql) {
					$first = true;
					$row_num = 0;
					while ($obj = $db->fetch_array($resql)) {
						if ($first) {
							print implode("\t", array_keys($obj))."\n".str_repeat('-', 80)."\n";
							$first = false;
						}
						$row_num++;
						$vals = array();
						foreach ($obj as $v) {
							$vals[] = ($v === null) ? 'NULL' : (strlen($v) > 40 ? substr($v, 0, 40).'...' : $v);
						}
						print implode("\t", $vals)."\n";
					}
					print "\n$row_num rows.\n";
				} else {
					print "SQL ERROR: ".$db->lasterror()."\n";
				}
			}
		}
	}
	print "\n";
}


// =====================================================================
// TRIGGERS
// =====================================================================
if ($mode === 'triggers' || $run_all) {
	print "--- TRIGGER REGISTRATION ---\n";
	$trigger_dir = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/core/triggers';
	if (is_dir($trigger_dir)) {
		$files = scandir($trigger_dir);
		foreach ($files as $f) {
			if (preg_match('/^interface_.*\.class\.php$/', $f)) {
				print "  Found: $f\n";
				include_once $trigger_dir.'/'.$f;
				$classname = str_replace('.class.php', '', $f);
				print "    Class exists: ".(class_exists($classname) ? 'YES' : 'NO')."\n";
			}
		}
		// Parse handled events
		foreach ($files as $f) {
			if (preg_match('/^interface_.*\.class\.php$/', $f)) {
				$content = file_get_contents($trigger_dir.'/'.$f);
				preg_match_all("/case\s+'([^']+)'/", $content, $matches);
				if (!empty($matches[1])) {
					print "\n  Events handled in $f:\n";
					foreach ($matches[1] as $event) print "    - $event\n";
				}
			}
		}
	} else {
		print "  Trigger directory not found: $trigger_dir\n";
	}
	print "\n";
}


// =====================================================================
// HOOKS
// =====================================================================
if ($mode === 'hooks' || $run_all) {
	print "--- HOOK REGISTRATION ---\n";
	if (isset($conf->modules_parts['hooks'])) {
		foreach ($conf->modules_parts['hooks'] as $context => $modules) {
			if (is_array($modules)) {
				foreach ($modules as $mod) {
					if (stripos($mod, 'customerreturn') !== false) {
						print "  context='$context' module='$mod'\n";
					}
				}
			}
		}
	}

	$actions_file = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/class/actions_'.$MODULE_NAME.'.class.php';
	print "\n  Actions class:\n";
	print "    File: ".(file_exists($actions_file) ? 'EXISTS' : 'MISSING')."\n";
	if (file_exists($actions_file)) {
		include_once $actions_file;
		$ac = 'ActionsCustomerReturn';
		print "    Class: ".(class_exists($ac) ? 'YES' : 'NO')."\n";
		if (class_exists($ac)) {
			foreach (array('getElementProperties', 'formObjectOptions', 'showLinkToObjectBlock') as $m) {
				print "    $m(): ".(method_exists($ac, $m) ? 'defined' : 'MISSING')."\n";
			}
		}
	}
	print "\n";
}

print "=== END DEBUG ===\n";
