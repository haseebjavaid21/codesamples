<?php
/**
 * CRED-1365 : Cat-Auto-Dispatch | Contract-Information (domestic or foreign)
 */
class CategoryAutoDispatch
{
    /**
     * Auto dispatch categories based on status change and nationality
     * @param SugarBean $bean
     * @param Array $event
     * @param Array $arguments
     */
    public function autoDispatch($bean, $event, $arguments)
    {
        if (isset($arguments['isUpdate']) &&
            $arguments['isUpdate'] == true &&
            $bean->fetched_row['credit_request_status_id_c'] != $bean->credit_request_status_id_c &&
            $bean->credit_request_status_id_c == '05_checking_request') {
            require_once 'custom/include/CategoryDispatchHelper/CategoryDispatchHelper.php';
            $dispatchHelper = new CategoryDispatchHelper();
            $nationality = $bean->dotb_iso_nationality_code_c == 'ch' ? 'lead_swiss' : 'lead_not_swiss';
            $dispatchHelper->dispatchCategory($nationality, $bean->id, $bean->module_dir, true);
        }
    }
}
