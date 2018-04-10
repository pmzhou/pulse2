<?php
/**
 * (c) 2016 Siveo, http://siveo.net
 * $Id$
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

// Import the css needed
require("modules/kiosk/graph/index.css");
require("modules/kiosk/graph/packages.css");

//Import the functions and classes needed
require_once("modules/kiosk/includes/xmlrpc.php");
require_once("modules/pkgs/includes/xmlrpc.php");
require_once("modules/kiosk/includes/html.inc.php");
require_once("modules/imaging/includes/class_form.php");

require("graph/navbar.inc.php");
require("modules/kiosk/kiosk/localSidebar.php");

$profile = xmlrpc_get_profile_by_id($_GET['id']);

$p = new PageGenerator(_T("Edit Profile",'kiosk'));
$p->setSideMenu($sidemenu);
$p->display();

$f = new ValidatingForm(array("id" => "profile-form"));

$f->push(new Table());


$f->add(new SpanElement('',"packages"));

// -------
// Add an input for the profile name
// -------
$f->add(
//InputTplTitle came from modules/imaging/includes/class_form.php
    new TrFormElement(_T('Profile Name','kiosk').":", new InputTplTitle('name',_T('Profile Name','kiosk'))),
    array("value" => _T($profile['name'],'kiosk'), 'placeholder'=> _T('Name','kiosk'), "required" => True)
);

// -------
// Add a selector to activate / desactivate the profile
// -------
$profileStatus = new SelectItemtitle("status",_T("Set the profile to active / inactive state", "kiosk"));
$profileStatus->setElements([_T('Active',"kiosk"), _T('Inactive','kiosk')]);
$profileStatus->setElementsVal([1,0]);
$f->add(
    new TrFormElement(_T('Profile Status','kiosk').":", $profileStatus),
    array("value" => $profile['active'],"required" => True)
);
$f->pop(); // End of the table

//SepTpl came from modules/imaging/includes/class_form.php
$f->add( new SepTpl());
// Create a section without table in the form
$f->add(new TitleElement(_T("Manage packages", "kiosk")));

// Get the list of the packages
$available_packages = [];
$available_packages_str = "";

$allowedPackages = [];
$restrictedPackages = [];

// Divide the packages into two list : restrictedPackages and allowedPackages
foreach($profile['packages'] as $tmpPackage)
{
    if($tmpPackage['status'] == 'restricted')
        $restrictedPackages[$tmpPackage['name']]=$tmpPackage['uuid'];
    else
        $allowedPackages[$tmpPackage['name']]=$tmpPackage['uuid'];
}

// Generate a simplified list of package. This list contains all the packages. If some packages must be ignored it is precised here.
foreach(xmpp_packages_list() as $package)
{
    $available_packages[$package['name']] = $package['uuid'];


}

// Create a third list of the remaining packages
$tmpAvailableList = [];
foreach($available_packages as $name=>$uuid)
{
    if(in_array($uuid, $allowedPackages))
        continue;
    else if(in_array($uuid, $restrictedPackages))
        continue;
    else
    {
        $tmpAvailableList[$name] = $uuid;
    }
}

// The packages are contained into the lists :
// - available_packages
// - allowedPackages
// - restrictedPackages
$available_packages = $tmpAvailableList;

// Generate the list of packages in the available list. This is the process by default when adding new profile
foreach($available_packages as $package_name=>$package_uuid){
    $available_packages_str .= '<li data-draggable="item" data-uuid="'.$package_uuid.'">'.$package_name.'</li>';
}
foreach($allowedPackages as $package_name=>$package_uuid){
    $allowed_packages_str .= '<li data-draggable="item" data-uuid="'.$package_uuid.'">'.$package_name.'</li>';
}
foreach($restrictedPackages as $package_name=>$package_uuid){
    $restricted_packages_str .= '<li data-draggable="item" data-uuid="'.$package_uuid.'">'.$package_name.'</li>';
}

$f->add(new SpanElement('<div style="display:inline-flex; width:100%" id="packages">
        <!-- Source : https://www.sitepoint.com/accessible-drag-drop/ -->
        <div style="width:100%">
            <h1>'._T("Available packages","kiosk").'</h1>
            <ol data-draggable="target" id="available-packages">'.$available_packages_str.'</ol>
        </div>
    
        <div style="width:100%">
            <h1>'._T("Restricted packages","kiosk").'</h1>
            <ol data-draggable="target" id="available-packages">'.$restricted_packages_str.'</ol>
        </div>
    
        <div style="width:100%">
            <h1>'._T("Allowed packages","kiosk").'</h1>
            <ol data-draggable="target" id="available-packages">'.$allowed_packages_str.'</ol>
        </div>
    </div>',"packages"));

$f->add(new HiddenTpl("jsonDatas"), array("value" => "", "hide" => True));

$bo = new ValidateButtonTpl('bvalid', _T("Create",'kiosk'),'btnPrimary',_T("Create the profile", "kiosk"));
//$rr = new TrFormElementcollapse($bo);
$bo->setstyle("text-align: center;");
$f->add($bo);

$f->pop(); // end of form

$f->display(); // display the form
?>


<script src="modules/kiosk/graph/js/packages.js">
    // Manage drag&drop for the packages boxes
    // Generate a json with the packages
</script>
<script src="modules/kiosk/graph/js/add_validate.js"></script>