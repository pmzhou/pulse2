<?php
/**
 * (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
 * (c) 2007-2008 Mandriva, http://www.mandriva.com
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

require("localSidebar.php");
require("graph/navbar.inc.php");

require_once("modules/backuppc/includes/xmlrpc.php");
require_once("modules/backuppc/includes/functions.php");
require_once("modules/backuppc/includes/html.inc.php");

// Getting Profile ID (if specified) else 0
$ID = intval(@max($_GET['id'],$_POST['id']));



// Receiving POST DATA
if (isset($_POST['bconfirm'])){
    $cfg = array(
        'profilename' => $_POST['profilename'],
        'full'  => $_POST['full'],
        'incr'  => $_POST['incr'],
        'exclude_periods'=>''
    );
    // Formatting Exclude periods
    $starthours = $_POST['starthour'];
    $endhours = $_POST['endhour'];
    
    for ($i = 0 ; $i<count($starthours); $i++) {
        $daystring = implode(',',$_POST['days'.$i]);
        $cfg['exclude_periods'] .= sprintf("%s=>%s:%s\n",
        hhmm2float($starthours[$i]),  hhmm2float($endhours[$i]),$daystring);
    }

    $cfg['exclude_periods'] = trim($cfg['exclude_periods']);

    if ($ID)
        $profile = edit_period_profile($ID,$cfg);
    else
        $profile = add_period_profile($cfg);
    
    // APPLY PROFILE TO ALL CONCERNED HOSTS
    apply_period_profile($ID);
}
else
    if ($ID)
        $profile = edit_period_profile($ID,array(''=>''));
    else
        $profile = array('profilename'=>'','full'=>'','incr'=>'','exclude_periods'=>'0=>0: ');


// Add or Edit
if ($ID)
    $p = new PageGenerator(_T("Edit Backup profile", "backuppc"));
else 
    $p = new PageGenerator(_T("Add Backup profile", "backuppc"));

$p->setSideMenu($sidemenu);
$p->display();

// display an edit profile form 
$f = new ValidatingForm();
$f->push(new Table());


// Profile name
$f->add(
    new TrFormElement('Profile name', new InputTpl('profilename')),
    array("value" => $profile['profilename'],"required" => True)
);


// FULL period
$f->add(
    new TrFormElement('Full period', new InputTpl('full')),
    array("value" => $profile['full'],"required" => True)
);

// INCR period
$f->add(
    new TrFormElement('Inremental period', new InputTpl('incr')),
    array("value" => $profile['incr'],"required" => True)
);

$daynames = array(
    _T('Monday','backuppc'),
    _T('Tuesday','backuppc'),
    _T('Wednesday','backuppc'),
    _T('Thursday','backuppc'),
    _T('Friday','backuppc'),
    _T('Saturday','backuppc'),
    _T('Sunday','backuppc')
);

// Exclude periods
$exclude_periods = explode("\n",$profile['exclude_periods']);
$z = 0;

foreach ($exclude_periods as $period) {
    preg_match("$([0-9.]+)=>([0-9.]+):([^:]+)$",$period, $matches);
    if (count($matches)!=4) continue;
    list($period,$from,$to,$days) = $matches;

    $days = explode(',',$days);
   
    // DAYS SELECT
    $sel = new MultipleSelect('days'.$z++);
    $sel->setElements($daynames);
    $sel->setElementsVal(array('1','2','3','4','5','6','7'));
    foreach ($days as $day)
        $sel->setSelected(trim($day));
    
    // Start hour
    $fields = array(
        new hourInputTpl('starthour[]'),
        new textTpl('to'),
        new hourInputTpl('endhour[]'),
        new textTpl('during'),
        $sel,
        new buttonTpl('removePeriod',_T('Remove'),'removePeriod')
        );
    
    $values = array(
        float2hhmm($from),
        '',
        float2hhmm($to),
        '',
        ''
    );
    
    $f->add(
        new TrFormElement('Do not backup from', new multifieldTpl($fields)),
        array("value" => $values,"required" => True)
    );

    
}

// Add Period button
$addPeriodBtn = new buttonTpl('addPeriod','Add period');
$addPeriodBtn->setClass('btnPrimary');
$f->add(
    new TrFormElement('', $addPeriodBtn),
    array()
);

// If BackupProfile id is transmitten, we write it in the form
if ($ID) {
    $f->add(new HiddenTpl("id"), array("value" => $ID, "hide" => True));
}
elseif (isset($profile['id']))
    $f->add(new HiddenTpl("id"), array("value" => $profile['id'], "hide" => True));


$f->pop();
$f->addValidateButton("bconfirm");
$f->display();

?>

<script src="modules/backuppc/lib/jquery-1.10.1.min.js"></script>
<script src="modules/backuppc/lib/jquery-ui.min.js"></script>
<script src="modules/backuppc/lib/jquery.maskedinput-1.3.min.js"></script>
<script src="modules/backuppc/lib/jquery.multiselect.js"></script>
<script type="text/javascript">
// Avoid prototype <> jQuery conflicts
jQuery.noConflict();

jQuery(function(){
    
    periodLine = jQuery('.removePeriod:first').parents('tr:first').clone();
    
    // Multiselect listbox
    jQuery("select").multiselect({
        height: 120,
        header: false,
        minWidth : 180,
        noneSelectedText : '<?php echo _T('Select days','backuppc'); ?>',
        selectedText : '<?php echo _T('Select days','backuppc'); ?>'
     });
     
     // Remove period button
     jQuery('.removePeriod').click(function(){
         if (jQuery('.removePeriod').length > 1)
             jQuery(this).parents('tr:first').remove();
     });
     
     // Hour mask inputs
     jQuery('input[name="starthour[]"]').mask('99:99');
     jQuery('input[name="endhour[]"]').mask('99:99');
     
     // Add period button
     jQuery('#addPeriod').click(function(){
        var idx = parseInt(jQuery('select:last').attr('name').replace('days','').replace('[]',''))+1;        
        var newline = periodLine.clone().insertBefore(jQuery(this).parents('tr:first'));
         newline.find('input[type=text]').val('');
         newline.find('select').val([])
                 .attr({'name':'days'+idx+'[]','id':'days'+idx+'[]'})
                 .multiselect({
                    height: 120,
                    header: false,
                    minWidth : 180,
                    noneSelectedText : '<?php echo _T('Select days','backuppc'); ?>',
                    selectedText : '<?php echo _T('Select days','backuppc'); ?>'
         });
         newline.find('.removePeriod').click(function(){
            if (jQuery('.removePeriod').length > 1)
                jQuery(this).parents('tr:first').remove();
        });
        // Hour mask inputs
        newline.find('input[name="starthour[]"]').mask('99:99');
        newline.find('input[name="endhour[]"]').mask('99:99');
     });
    
});
     
</script>