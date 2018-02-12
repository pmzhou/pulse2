<?php
/**
 * (c) 2016 Siveo, http://siveo.net
 *
 * This file is part of Management Console (MMC).
 *
 * MMC is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * MMC is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MMC; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * module declaration
 */
require_once("modules/pulse2/version.php");

$mod = new Module("kiosk");
$mod->setVersion("4.0");
$mod->setRevision('');
$mod->setDescription(_T("kiosk", "kiosk"));
$mod->setAPIVersion("0:0:0");
$mod->setPriority(10);

$submod = new SubModule("kiosk");
$submod->setDescription(_T("kiosk", "kiosk"));
$submod->setVisibility(True);
$submod->setImg('modules/kiosk/graph/navbar/kiosk');
$submod->setDefaultPage("kiosk/kiosk/index");
$submod->setPriority(-10);



$page = new Page("index", _T('kiosk status', 'kiosk'));
$page->setFile("modules/kiosk/kiosk/index.php");//, array("expert" => True)
$submod->addPage($page);


$mod->addSubmod($submod);

$MMCApp =& MMCApp::getInstance();
$MMCApp->addModule($mod);

?>
