<?php
/**
 * (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
 * (c) 2007-2010 Mandriva, http://www.mandriva.com
 *
 * $Id$
 *
 * This file is part of Mandriva Management Console (MMC).
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
 * along with MMC.  If not, see <http://www.gnu.org/licenses/>.
 */
require("modules/msc/logs/localSidebar.php");
require("graph/navbar.inc.php");

require_once('modules/msc/includes/commands_xmlrpc.inc.php');
require_once('modules/msc/includes/command_history.php');
require_once('modules/msc/includes/functions.php');
require_once('modules/msc/includes/widgets.inc.php');
require_once('modules/msc/includes/mscoptions_xmlrpc.php');

$p = new PageGenerator(_T("", 'msc'));
$p->setSideMenu($sidemenu);
$p->display();

print '<h2>' . _T('Current tasks', 'msc') . '</h2>';

//include("modules/msc/logs/ajaxConsultLogsFilter.php");
// Running commands
$ajax = new AjaxFilterCommands(urlStrRedirect("msc/logs/ajaxConsultLogsFilter"), "divRunning", "commands", array('expired' => 0, 'divID' => 'divRunning'), 'formRunning');
$ajax->setRefresh(web_def_refresh_time());
$ajax->display();
print "<br/><br/><br/>";
$ajax->displayDivToUpdate();

print "<br/><br/><br/>";

// Expired commands

print '<h2>' . _T('Expired tasks', 'msc') . '</h2>';

$ajax = new AjaxFilterCommands(urlStrRedirect("msc/logs/ajaxConsultLogsFilter"), "divExpired", "commands", array('expired' => 1, 'divID' => 'divExpired'), 'formExpired');
$ajax->setRefresh(web_def_refresh_time());
$ajax->display();
print "<br/><br/><br/>";
$ajax->displayDivToUpdate();
?>

<script type="text/javascript" src="jsframework/lib/raphael/raphael-min.js"></script>
<script type="text/javascript" src="jsframework/lib/raphael/g.raphael-min.js"></script>
<script type="text/javascript" src="jsframework/lib/raphael/g.pie-min.js"></script>
<script type="text/javascript" src="jsframework/lib/raphael/utilities.js"></script>