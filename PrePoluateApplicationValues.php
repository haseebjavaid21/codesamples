<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * CRED-1488 : Status-Mgmt of SWK-Identification-Process | Unique Lead-Identifier
 */
class PrePoluateApplicationValues
{
    /**
     * Function for Application values population
     * @param Bean $bean
     * @param Array $event
     * @param Array $arguments
     */
    public function getApplicationValues($bean, $event, $arguments)
    {
        if ($bean->name == 'Online-Identifikation erfolgreich' && $bean->parent_type == 'Leads'
                && !empty($bean->parent_id) && empty($bean->application_provider_c)) {
            $taskQuery = "SELECT oc.user_id_c,oc.provider_id_c,o.provider_contract_no"
                    . " FROM opportunities_cstm oc JOIN opportunities o ON o.id = oc.id_c "
                    . " JOIN leads_opportunities_1_c lop ON lop.leads_opportunities_1opportunities_idb = o.id"
                    . " JOIN leads l ON l.id = lop.leads_opportunities_1leads_ida"
                    . " WHERE o.deleted = 0 AND oc.provider_id_c = 'swk' "
                    . " AND l.id =".$GLOBALS['db']->quoted($bean->parent_id). " LIMIT 0,1";
            
            $taskQuery = $GLOBALS['db']->query($taskQuery);
            if ($taskQuery->num_rows > 0) {
                $taskQueryResult = $GLOBALS['db']->fetchByAssoc($taskQuery);
                $bean->user_id_c = $taskQueryResult['user_id_c'];
                $bean->application_provider_c = $taskQueryResult['provider_id_c'];
                $bean->provider_contract_no = $taskQueryResult['provider_contract_no'];
                $bean->save();
            }
        }
    }
}
