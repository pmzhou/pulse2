<?php

/**
 * (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
 *
 * $Id$
 *
 * This file is part of LMC.
 *
 * LMC is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * LMC is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LMC; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

function getStatusTable() {
    return array("upload_in_progress" => _T("Upload in progress", "msc"),
                     "upload_done" => _T("Upload done", "msc"),
                     "upload_failed" => _T("Upload failed", "msc"),
                     "execution_in_progress" => _T("Execution in progress", "msc"),
                     "execution_done" => _T("Execution done", "msc"),
                     "execution_failed" => _T("Execution failed", "msc"),
                     "delete_in_progress" => _T("Delete in progress", "msc"),
                     "delete_done" => _T("Delete done", "msc"),
                     "delete_failed" => _T("Delete failed", "msc"),
                     "inventory_in_progress" => _T("Inventory in progress", "msc"),
                     "inventory_failed" => _T("Inventory failed", "msc"),
                     "inventory_done" => _T("Inventory done", "msc"),
                     "reboot_in_progress" => _T("Reboot in progress", "msc"),
                     "reboot_failed" => _T("Reboot failed", "msc"),
                     "reboot_done" => _T("Reboot done", "msc"),
                     "wol_in_progress" => _T("WOL in progress", "msc"),
                     "wol_failed" => _T("WOL failed", "msc"),
                     "wol_done" => _T("WOL done", "msc"),
                     "not_reachable" => _T("Not reachable", "msc"),
                     "done" => _T("Done", "msc"),
                     "pause" => _T("Pause", "msc"),
                     "stop" => _T("Stopped", "msc"),
                     "scheduled" => _T("Scheduled", "msc"),
                     "rescheduled" => _T("Rescheduled", "msc"),
                     "failed" => _T("Failed", "msc"),
                     "TODO" => _T("To do", "msc"),
                     "IGNORED" => _T("Ignored", "msc"),
                     "DONE" => _T("Done", "msc"),
                     "FAILED" => _T("Failed", "msc"),
                     "WORK_IN_PROGRESS" => _T("Work in progress", "msc")
                     );
}

$statusTable = getStatusTable();

function return_icon($state)
{
    switch($state) {
        case "TODO":
            return "led_circle_grey.png";
            break;
        case "IGNORED":
            return "led_circle_black.png";
            break;
        case "DONE":
            return "led_circle_green.png";
            break;
        case "FAILED":
            return "led_circle_red.png";
            break;
        case "WORK_IN_PROGRESS":
            return "led_circle_orange.png";
            break;
    }
}

function state2icon($current_state) {
    switch ($current_state) {
        case "wol_in_progress";
        case "upload_in_progress" :
        case "execution_in_progress":
        case "delete_in_progress":
        case "inventory_in_progress";
        case "reboot_in_progress";
            return "led_circle_orange.png";

        case "wol_done";
        case "upload_done" :
        case "execution_done":
        case "delete_done":
        case "inventory_done";
        case "reboot_done";
        case "done":
            return "led_circle_green.png";

        case "not_reachable":
        case "wol_failed";
        case "upload_failed" :
        case "execution_failed":
        case "delete_failed":
        case "inventory_failed";
        case "reboot_failed";
            return "led_circle_red.png";
        case "pause":
        case "stop":
            return "led_circle_black.png";

        case "rescheduled":
        case "scheduled":
            return "led_circle_grey.png";
    }
}
function history_stat2icon($state) {
    switch ($state) {
        case "wol_done";
        case "upload_done" :
        case "delete_done":
        case "execution_done":
        case "inventory_done";
        case "reboot_done";
        case "done":
            return "led_circle_green.png";

        case "not_reachable":
        case "wol_failed";
        case "upload_failed" :
        case "execution_failed":
        case "delete_failed":
        case "inventory_failed";
        case "reboot_failed";
            return "led_circle_red.png";

        case "wol_in_progress";
        case "upload_in_progress" :
        case "execution_in_progress":
        case "delete_in_progress":
        case "inventory_in_progress";
        case "reboot_in_progress";
            return "led_circle_orange.png";

        case "pause":
        case "stop":
            return "led_circle_black.png";

        case "rescheduled":
        case "scheduled":
            return "led_circle_gray.png";
    }
}

function state_tmpl($current_state) {
    # based on http://pulse2.mandriva.org/ticket/29
    $ret = array(
        'play' => '',
        'stop' => '',
        'pause' => ''
    );

    # task is scheduled
    if (in_array(
        $current_state,
        array(
            'scheduled',
            'rescheduled',
            'not_reachable',
            'upload_done',
            'upload_failed',
            'execution_done',
            'execution_failed',
            'delete_done',
            'delete_failed',
            'inventory_failed',
            'inventory_done',
            'wol_failed',
            'wol_done',
            'reboot_failed',
            'reboot_done'
        )
    ))
        $ret = array(
            'play' => 'BUTTON_START',
            'stop' => 'BUTTON_STOP',
            'pause' => 'BUTTON_PAUSE'
        );

    # task is running
    if (in_array(
        $current_state,
        array(
            'upload_in_progress',
            'execution_in_progress',
            'delete_in_progress',
            'inventory_in_progress',
            'reboot_in_progress',
            'wol_in_progress',
        )
    ))
        $ret = array(
            'play' => '',
            'stop' => 'BUTTON_STOP',
            'pause' => 'BUTTON_PAUSE'
        );

    # task is completed
    if (in_array(
        $current_state,
        array(
            'stop',
            'done',
            'failed'
        )
    ))
        $ret = array(
            'play' => 'BUTTON_START',
            'stop' => '',
            'pause' => ''
        );

    # task is paused
    if (in_array(
        $current_state,
        array(
            'pause',
        )
    ))
        $ret = array(
            'play' => 'BUTTON_START',
            'stop' => 'BUTTON_STOP',
            'pause' => 'BUTTON_PAUSE'
        );
    return $ret;
}

function template_set_cmd_by_page(&$template, $tmpl_name, $number_command_by_page) {

    /**
     * Number command by page display item selected
     */
    if ($number_command_by_page==10) {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_10_SELECTED", "page_10_selected");
        $template->parse("page_10_selected", "NUMBER_BY_PAGE_10_SELECTED");
    } else {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_10_SELECTED", "page_10_selected");
        $template->set_var("page_10_selected", "");
    }

    if ($number_command_by_page==20) {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_20_SELECTED", "page_20_selected");
        $template->parse("page_20_selected", "NUMBER_BY_PAGE_20_SELECTED");
    } else {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_20_SELECTED", "page_20_selected");
        $template->set_var("page_20_selected", "");
    }

    if ($number_command_by_page==50) {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_50_SELECTED", "page_50_selected");
        $template->parse("page_50_selected", "NUMBER_BY_PAGE_50_SELECTED");
    } else {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_50_SELECTED", "page_50_selected");
        $template->set_var("page_50_selected", "");
    }

    if ($number_command_by_page==100) {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_100_SELECTED", "page_100_selected");
        $template->parse("page_100_selected", "NUMBER_BY_PAGE_100_SELECTED");
    } else {
        $template->set_block($tmpl_name, "NUMBER_BY_PAGE_100_SELECTED", "page_100_selected");
        $template->set_var("page_100_selected", "");
    }
}


?>
