<?

require('modules/msc/includes/machines.inc.php');
require("modules/base/computers/localSidebar.php");
require("graph/navbar.inc.php");

if (!isset($_GET['hostname'])) { $_GET['hostname'] = $_GET['cn']; }
if (!isset($_GET['uuid'])) { $_GET['uuid'] = $_GET['objectUUID']; }

if ($_GET['uuid']) {
    $machine = getMachine(array('uuid'=>$_GET['uuid']), $ping = False);
    if ($machine->uuid != $_GET['uuid']) {
        $p = new PageGenerator(sprintf(_T("%s's machine secure control", 'msc'), $_GET['hostname']));
        $p->setSideMenu($sidemenu);
        $p->display();
        include('modules/msc/msc/header.php');
    } else {
        $p = new TabbedPageGenerator();
        $p->setSideMenu($sidemenu);
        $p->addTop(sprintf(_T("%s's machine secure control", 'msc'), $machine->hostname), "modules/msc/msc/header.php");
        $p->addTab("tablaunch", _T("Launch Actions", 'msc'), "", "modules/msc/msc/launch.php", array('uuid'=>$machine->uuid, 'hostname'=>$machine->hostname));
        $p->addTab("tablogs", _T("Logs", 'msc'), "", "modules/msc/msc/logs.php", array('uuid'=>$machine->uuid, 'hostname'=>$machine->hostname));
        $p->addTab("tabhistory", _T("History", 'msc'), "", "modules/msc/msc/history.php", array('uuid'=>$machine->uuid, 'hostname'=>$machine->hostname));
        $p->display();
    }
} elseif ($_GET['gid']) {
    $p = new TabbedPageGenerator();
    $p->setSideMenu($sidemenu);
    require("modules/dyngroup/includes/includes.php");
    $group = new Group($_GET['gid'], true);
    $p->addTop(sprintf(_T("%s's group secure control", 'msc'), $group->getName()), "modules/msc/msc/header.php");
    $p->addTab("grouptablaunch", _T("Launch Actions", 'msc'), "", "modules/msc/msc/launch.php", array('gid'=>$_GET['gid']));
    $p->addTab("grouptablogs", _T("Logs", 'msc'), "", "modules/msc/msc/logs.php", array('gid'=>$_GET['gid']));
    $p->addTab("grouptabhistory", _T("History", 'msc'), "", "modules/msc/msc/history.php", array('gid'=>$_GET['gid']));
    $p->display();
} else {
    $p = new PageGenerator();
    $p->setSideMenu($sidemenu);
    $p->display();
    print _T("Not enough informations", "msc");
}

?>
