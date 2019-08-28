<?php

$viewdefs['base']['view']['popup-management'] = array(
    'panels' => array(
        array(
            'name' => 'popup_panel',
            'fields' => array(
                array(
                    'name' => 'popup_status',
                    'vname' => 'Lead Pop-Up',
                    'type' => 'switch-button',
                ),
                array(
                    'name' => 'popup_visibility_start',
                    'vname' => 'From',
                    'type' => 'enum',
                    'default' => '00:00',
                    'options' => 'employee_time_list',
                    'required' => true,
                ),
                array(
                    'name' => 'popup_visibility_end',
                    'vname' => 'To',
                    'type' => 'enum',
                    'default' => '00:00',
                    'options' => 'employee_time_list',
                    'required' => true,
                ),
                array(
                    'name' => 'popup_one_duration',
                    'vname' => 'Pop-Up Duration ( minutes )',
                    'type' => 'int',
                    'default' => '',
                    'required' => true,
                ),
                /* CRED-1200: On-Screen-Alert for New-Lead-Notification (After first reminder pop-up will be shown each minutes)
                array(
                    'name' => 'popup_two_duration',
                    'vname' => 'Second Pop-Up Duration ( minutes )',
                    'type' => 'varchar',
                    'default' => '',
                ),*/
                /**
                 * CRED-1213 : Admin for Instant-Call-Alerts
                 */
                array(
                    'name' => 'popup_days',
                    'vname' => 'Pop-Up Visibility',
                    'type' => 'enum',
                    'isMultiSelect' => true,
                    'module' => 'Leads',
                    'options' => 'dom_cal_day_of_week',      
                    'default' => '',
                    'required' => true,
                ),
            )
        )
    )
);
