<?php
/**
 * (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
 * (c) 2007-2008 Mandriva, http://www.mandriva.com/
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
 * along with MMC; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */



require_once("modules/pkgs/includes/xmlrpc.php");
require_once("modules/msc/includes/package_api.php");
require_once("modules/msc/includes/utilities.php");
?>
<style>
    a.info{
    position:relative;
    z-index:24;
    color:#000;
    text-decoration:none
}
 
a.info:hover{
    z-index:25;
    background-color:#FFF
}
 
a.info span{
    display: none
}
 
a.info:hover span{
    display:block;
    position:absolute;
    top:2em; left:2em; width:25em;
    border:1px solid #000;
    background-color:#E0FFFF;
    color:#000;
    text-align: justify;
    font-weight:none;
    padding:5px;
}
</style>
<?
global $conf;
$maxperpage = $conf["global"]["maxperpage"];

$filter = array('filter'=> $_GET["filter"], 'location'=> $_GET['location'], 'bundle' => 0);
$filter1 = $_GET["filter"]. '##'.$_GET['location'];

if ($_GET['location']) {
    $filter['packageapi'] = getPApiDetail(base64_decode($_GET['location']));
}
if (isset($_GET["start"])) {
    $start = $_GET["start"];
} else {
    $start = 0;
}
$_SESSION['pkgs_selected'] = base64_decode($filter['location']);


/*
 * These variables will contain the packages info
 */
    $count = 0;
    $params = array();
    $names = array();
    $versions = array();
    $licenses = array();
    $size = array();
    $err = array();
    $desc = array();

    /*
     * Standard mode
     * -------------
     *
     * The standard mode is used by the old package server
     *
     */
    if(!isExpertMode()) {
        $packages = advGetAllPackages($filter, $start, $start + $maxperpage);

        $count = $packages[0];
        $packages = $packages[1];
        foreach ($packages as $p) {
            $p = $p[0];
            if (isset($p['ERR']) && $p['ERR'] == 'PULSE2ERROR_GETALLPACKAGE') {
                $err[] = sprintf(_T("MMC failed to contact package server %s.", "pkgs"), $p['mirror']);
            } else {
                $names[] = $p['label'];
                $versions[] = $p['version'];
                $desc[] = $p['description'];
                // #### begin licenses ####
                $tmp_licenses = '';
                if ($p['associateinventory'] == 1 && isset($p['licenses']) && !empty($p['licenses'])) {
                    $licensescount = getLicensesCount($p['Qvendor'], $p['Qsoftware'], $p['Qversion'])['count'];
                    //lien vers creation groupe des machine avec licence.
                    $param = array();
                    $param['vendor'] = $p['Qvendor'];
                    $param['software'] = $p['Qsoftware'];
                    $param['version'] = $p['Qversion'];
                    $param['count'] = $licensescount;
                    $param['licencemax'] = $p['licenses'];
                    $urlRedirect = urlStrRedirect("pkgs/pkgs/createGroupLicence", $param);
                    $tmp_licenses = '<span style="border-width:1px;border-style:dotted; border-color:black; ">' .
                        '<a href="' .
                        $urlRedirect . '" title="Create group">' .
                        $licensescount .
                        '/' .
                        $p['licenses'] .
                        '</a></span>';

                    if ($licensescount > $p['licenses']) { // highlights the exceeded license count
                        $tmp_licenses = '<font color="FF0000">' . $tmp_licenses . '</font>';
                    }
                }
                $licenses[] = $tmp_licenses;
                // #### end licenses ####
                $size[] = prettyOctetDisplay($p['size']);
                $params[] = array('p_api' => $_GET['location'], 'pid' => base64_encode($p['id']));
            }
        }
    }

    /*
     * Expert mode
     * -------------
     *
     * The expert mode is used by the xmpp package server
     *
     */
    else
    {
        // 1 - Get the list of xmpp packages
        $packages = xmpp_packages_list();
        // 2 - For each package, extract information
        foreach($packages as $package)
        {
            // 3 - Load the appropriate information into each array
            $names[] = $package['name'];
            if(isset($package['version']))
                $versions[] = $package['version'];
            $licenses[] = "";
            $size[] = "";
            $desc[] = $package['description'];
            $err = false;
        }
    }
    echo "<br>";
    if ($err) {
        new NotifyWidgetFailure(implode('<br/>', array_merge($err, array(_T("Please contact your administrator.", "pkgs")))));
    }

    // 4 - Display the list
    $n = new OptimizedListInfos($names, _T("Package name", "pkgs"));
    $n->setCssClass("package");
    $n->disableFirstColumnActionLink();
    $n->addExtraInfo($desc, _T("Description", "pkgs"));
    $n->addExtraInfo($versions, _T("Version", "pkgs"));
    $n->addExtraInfo($licenses, _T("Licenses", "pkgs"));
    $n->addExtraInfo($size, _T("Package size", "pkgs"));
    $n->setItemCount($count);
    $n->setNavBar(new AjaxNavBar($count, $filter1));
    $n->setParamInfo($params);
    $n->start = 0;
    $n->end = $count;

    $n->addActionItem(new ActionItem(_T("Edit a package", "pkgs"), "edit", "edit", "pkgs", "pkgs", "pkgs"));
    $n->addActionItem(new ActionPopupItem(_T("Delete a package", "pkgs"), "delete", "delete", "pkgs", "pkgs", "pkgs"));

    print "<br/><br/>"; // to go below the location bar : FIXME, really ugly as line height dependent

    $n->display();

?>
