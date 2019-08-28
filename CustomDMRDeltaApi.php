<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}
/**
 * CRED-1004 : Create an API end point that would generate 
 * delta of the records create since last Pull
 * 
 * Custom End Point for DMR Delta
 */
class CustomDMRDeltaApi extends SugarApi
{
    public $leadFields = array();
    public $partnerFields = array();
    public $addressFields = array();
    public $appFields = array();
    public $contractFields = array();
    
    /**
     * Return array containing infomartion about
     * End Point Registration
     * 
     * @return Array
     */
    public function registerApiRest()
    {
        return array(
            'getDMRDelta' => array(
                'reqType' => 'POST',
                'path' => array('DMRDelta', 'getLatestDelta'),
                'pathVars' => array('', ''),
                'method' => 'getLatestDelta',
                'shortHelp' => 'Generates delta of the records create since last pull and return it',
                'longHelp' => '',
            )
        );
    }

    /**
     * Gathers Lead and Related Information and Deleted
     * Leads Information and return a json array
     * 
     * @param  Object $api
     * @param  Array  $args
     * @return Array
     */
    public function getLatestDelta($api, $args)
    {
        $finalData = array();
        $leadObject = array();
        $leadIds = array();

        /**
         * Subtracting 5 mins from last execution time to handle boundary scenarios
         */
        $this->requireArgs($args, array('lastExecution'));
        $lastExecution = $args['lastExecution'];
        $time = strtotime($lastExecution);
        $time = $time - (5 * 60);
        $lastExecution = date("Y-m-d H:i:s", $time);

        /**
         * Fields List for use in Queries and data population
         */
        $this->leadFields = array('leads.id', 'credit_request_status_id_c', 'lq_next_best_steps_c', 'leads.first_name',
            'leads.last_name', 'leads.birthdate', 'dotb_correspondence_language_c', 'dotb_gender_id_c',
            'leads.phone_other', 'leads.phone_mobile', 'leads.phone_work', 'u.first_name as assigned_user_first_name',
            'u.last_name as assigned_user_last_name', 'credit_amount_c', 'credit_duration_c', 'c.first_name as cfname',
            'c.last_name as clname', 'email_address as email1', 'leads.primary_address_street',
            'leads.primary_address_postalcode', 'leads.primary_address_city', 'leads.primary_address_country',
            'leads.correspondence_address_street', 'leads.correspondence_address_postalcode',
            'leads.correspondence_address_city', 'leads.correspondence_address_country', 'dotb_resident_since_c',
            'dotb_bank_name_c', 'dotb_bank_zip_code_c', 'dotb_bank_city_name_c', 'dotb_iban_c', 'dotb_employer_name_c',
            'dotb_employer_npa_c', 'dotb_employer_town_c', 'dotb_is_in_probation_period_c','dotb_monthly_net_income_c',
            'dotb_monthly_gross_income_c', 'dot_second_job_employer_name_c', 'dotb_second_job_employer_npa_c',
            'dot_second_job_employer_town_c', 'dotb_monthly_net_income_nb_c', 'dotb_second_job_gross_income_c',
            'sideline_hired_since_c', 'dotb_rent_or_alimony_income_c', 'dotb_mortgage_amount_c',
            'dotb_housing_costs_rent_c', 'cstm_last_name_c', 'cc_id','leads.date_modified');

        $this->partnerFields = array('contacts.id', 'contacts.first_name', 'contacts.last_name', 'contacts.birthdate',
            'email_address as email1', 'contacts.phone_mobile', 'contacts.phone_other', 'contacts.phone_work',
            'u.first_name as assigned_user_first_name','u.last_name as assigned_user_last_name','contacts.address_c_o',
            'contacts.primary_address_street', 'contacts.primary_address_postalcode', 'contacts.primary_address_city',
            'contacts.primary_address_country', 'contacts.correspondence_address_street',
            'contacts.correspondence_address_postalcode', 'contacts.correspondence_address_city',
            'contacts.correspondence_address_country', 'contacts.dotb_bank_name', 'contacts.dotb_bank_zip_code',
            'contacts.dotb_bank_city_name', 'contacts.dotb_iban', 'contacts.dotb_employment_type_id',
            'contacts_cstm.dotb_is_pensioner', 'contacts.dotb_pension_type_id',
            'contacts_cstm.dotb_is_unable_to_work', 'contacts_cstm.dotb_unable_to_work_in_last_5_years',
            'contacts_cstm.dotb_partner_agreement_c', 'contacts.dotb_employer_name',
            'contacts.dotb_employer_npa', 'contacts.dotb_employer_town', 'contacts.dotb_is_in_probation_period',
            'contacts.dotb_monthly_net_income', 'contacts.dotb_monthly_gross_income',
            'contacts.dotb_has_thirteenth_salary', 'contacts_cstm.sideline_hired_since_c',
            'contacts.dotb_rent_or_alimony_income', 'contacts.dotb_mortgage_amount',
            'contacts_cstm.dotb_housing_costs_rent_c','contacts.date_modified');

        $this->addressFields = array('dot10_addresses.id', 'dot10_addresses.first_name', 'dot10_addresses.last_name',
            'c.first_name as cfname', 'c.last_name as clname', 'l.first_name as lfname', 'l.last_name as llname',
            'dot10_addresses.primary_address_street', 'dot10_addresses.primary_address_postalcode',
            'dot10_addresses.primary_address_city','dot10_addresses.date_modified');

        $this->appFields = array('opportunities.id', 'opportunities_cstm.provider_application_no_c',
            'opportunities.provider_contract_no', 'opportunities_cstm.credit_amount_c',
            'opportunities_cstm.credit_duration_c', 'opportunities_cstm.interest_rate_c',
            'opportunities.approved_saldo', 'opportunities_cstm.contract_credit_amount',
            'opportunities_cstm.contract_credit_duration', 'opportunities_cstm.contract_interest_rate',
            'opportunities.date_modified');

        $this->contractFields = array('contracts.id', 'contracts_cstm.provider_id_c', 'contracts_cstm.credit_amount_c',
            'contracts_cstm.interest_rate_c', 'contracts_cstm.credit_duration_c', 'contracts_cstm.contract_date_c',
            'contracts_cstm.paying_date_c', 'c.first_name as cfname',
            'c.last_name as clname', 'contracts_cstm.customer_credit_amount_c',
            'contracts_cstm.customer_credit_duration_c', 'contracts_cstm.customer_interest_rate_c',
            'contracts.date_modified');

        /**
         * Updated Leads Id's Lookup 
         */
        $leadQuery = "SELECT id FROM leads"
                . " WHERE deleted = 0 AND ( date_entered >='$lastExecution'"
                . " OR date_modified>='$lastExecution' )"
                . " ORDER BY date_modified DESC";

        $leads = $GLOBALS['db']->query($leadQuery);
        if ($leads->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($leads)) {
                /**
                 * Identifying  Lead ID
                 */
                $leadIds[] = $row['id'];
            }
        }

        /**
         * Updated Partner Id's Lookup 
         */
        $partnerQuery = "SELECT DISTINCT lc.leads_contacts_1leads_ida FROM contacts"
                . " LEFT JOIN contacts_cstm ON contacts.id = contacts_cstm.id_c"
                . " LEFT JOIN leads_contacts_1_c lc ON lc.leads_contacts_1contacts_idb = contacts.id"
                . " WHERE (( contacts.date_entered >='$lastExecution' "
                . " OR contacts.date_modified >='$lastExecution' )"
                . " OR lc.date_modified >= '$lastExecution' )"
                . " AND contacts_cstm.relative_type_c = 'partner'"
                . " ORDER BY contacts.date_modified";


        $partnerResult = $GLOBALS['db']->query($partnerQuery);
        if ($partnerResult->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($partnerResult)) {
                /**
                 * Identifying parent Lead ID
                 */
                $leadIds[] = $row['leads_contacts_1leads_ida'];
            }
        }

        /**
         * Updated Addresses Id's Lookup 
         */
        $addressQuery = "SELECT DISTINCT lc.leads_dot10_addresses_1leads_ida "
                . " FROM dot10_addresses"
                . " LEFT JOIN leads_dot10_addresses_1_c lc "
                . " ON lc.leads_dot10_addresses_1dot10_addresses_idb = dot10_addresses.id"
                . " WHERE (( dot10_addresses.date_entered >='$lastExecution'"
                . " OR dot10_addresses.date_modified>='$lastExecution' )"
                . " OR lc.date_modified >= '$lastExecution' )"
                . " ORDER BY dot10_addresses.date_modified DESC";

        $addressResult = $GLOBALS['db']->query($addressQuery);
        if ($addressResult->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($addressResult)) {
                /**
                 * Identifying parent Lead ID
                 */
                $leadIds[] = $row['leads_dot10_addresses_1leads_ida'];
            }
        }

        /**
         * Updated Applications Id's Lookup 
         */
        $applicationQuery = "SELECT DISTINCT lo.leads_opportunities_1leads_ida FROM opportunities"
                . " LEFT JOIN leads_opportunities_1_c lo"
                . " ON lo.leads_opportunities_1opportunities_idb = opportunities.id"
                . " WHERE (( opportunities.date_entered >='$lastExecution'"
                . " OR opportunities.date_modified>='$lastExecution' )"
                . " OR lo.date_modified >= '$lastExecution' )"
                . " ORDER BY opportunities.date_modified DESC";

        $applicationResult = $GLOBALS['db']->query($applicationQuery);
        if ($applicationResult->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($applicationResult)) {
                /**
                 * Identifying parent Lead ID
                 */
                $leadIds[] = $row['leads_opportunities_1leads_ida'];
            }
        }

        /**
         * Updated Contract Id's Lookup 
         */
        $contractQuery = "SELECT DISTINCT cl.contracts_leads_1leads_idb FROM contracts"
                . " LEFT JOIN contracts_leads_1_c cl "
                . " ON cl.contracts_leads_1contracts_ida = contracts.id"
                . " WHERE (( contracts.date_entered >='$lastExecution'"
                . " OR contracts.date_modified>='$lastExecution' )"
                . " OR cl.date_modified >= '$lastExecution' )"
                . " ORDER BY contracts.date_modified DESC";

        $contractResult = $GLOBALS['db']->query($contractQuery);
        if ($contractResult->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($contractResult)) {
                /**
                 * Identifying parent Lead ID
                 */
                $leadIds[] = $row['contracts_leads_1leads_idb'];
            }
        }

        /**
         * Filtering Lead Id's List
         */
        $leadIds = array_filter($leadIds);
        /**
         * Removing Duplicates from Lead List
         */
        $leadIds = array_unique($leadIds);

        foreach ($leadIds as $ids) {
            /**
             * Removing empty Index if no Data is returned
             */
            if (!empty($this->getDataFromLead($ids))) {
                $finalData[$ids] = $this->getDataFromLead($ids);
            }
        }

        $finalData = json_encode($finalData);
        
        /**
         * Deleted Leads Lookup 
         */
        $deletedLeads = array();
        $leadQuery = "SELECT id FROM leads"
                . " WHERE deleted = 1 AND date_modified >='$lastExecution'"
                . " ORDER BY date_modified DESC";
        $leads = $GLOBALS['db']->query($leadQuery);
        if ($leads->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($leads)) {
                foreach ($row as $key => $value) {
                    $deletedLeads[] = $row['id'];
                }
            }
        }
        return array('updated' => $finalData, 'deleted' => $deletedLeads);
    }
    
    /**
     * Returns data of updates Leads and their related records.
     * 
     * @param varchar $leadId
     * @return Array
     */
    public function getDataFromLead($leadId)
    {
        /**
         * Lead Data Lookup 
         */
        $fields = '';
        $leadData = array();

        /**
         * Preparing field list for query
         */
        foreach ($this->leadFields as $field) {
            $fields .= $field . ',';
        }
        $fields = rtrim($fields, ',');
        $createdLeads = "SELECT $fields FROM leads"
                . " LEFT JOIN leads_cstm ON leads.id = leads_cstm.id_c AND deleted = 0"
                . " LEFT JOIN users u ON u.id = leads.assigned_user_id AND u.deleted = 0"
                . " LEFT JOIN email_addr_bean_rel  ebr ON ebr.bean_id = leads.id AND ebr.bean_module = 'Leads'"
                . " AND ebr.deleted = 0"
                . " LEFT JOIN email_addresses ea ON ea.id = ebr.email_address_id  AND ea.deleted = 0"
                . " LEFT JOIN contacts c ON c.id = leads.contact_id AND c.deleted = 0"
                . " WHERE leads.deleted = 0 AND leads.id = '$leadId'";

        $leadsResult = $GLOBALS['db']->query($createdLeads);
        if ($leadsResult->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($leadsResult)) {
                $leadData = $row;
                $leadData['assigned_user_name']
                        = trim($row['assigned_user_first_name'] . " " . $row['assigned_user_last_name']);
                $leadData['contact_name'] = trim($row['cfname'] . " " . $row['clname']);

                /**
                 * Partner Data Lookup
                 */
                $fields = '';
                $partnerData = array();
                $partners = array();

                /**
                 * Preparing field list for query
                 */
                foreach ($this->partnerFields as $field) {
                    $fields .= $field . ',';
                }
                $fields = rtrim($fields, ',');

                $partnerQuery = "SELECT $fields,lc.leads_contacts_1leads_ida FROM contacts"
                        . " LEFT JOIN contacts_cstm ON contacts.id = contacts_cstm.id_c"
                        . " LEFT JOIN leads_contacts_1_c lc ON lc.leads_contacts_1contacts_idb = contacts.id"
                        . " AND lc.deleted = 0"
                        . " LEFT JOIN email_addr_bean_rel ebr ON ebr.bean_id = contacts.id "
                        . " AND ebr.bean_module = 'Contacts' AND ebr.deleted = 0"
                        . " LEFT JOIN email_addresses ea ON ea.id = ebr.email_address_id  AND ea.deleted = 0"
                        . " LEFT JOIN users u ON u.id = contacts.assigned_user_id AND u.deleted = 0 "
                        . " WHERE  contacts.deleted = 0 AND lc.leads_contacts_1leads_ida = '$leadId'"
                        . " ORDER BY contacts.date_modified DESC LIMIT 0,1";

                $partnerResult = $GLOBALS['db']->query($partnerQuery);
                if ($partnerResult->num_rows > 0) {
                    while ($row = $GLOBALS['db']->fetchByAssoc($partnerResult)) {
                        $partnerData = $row;
                        $partnerData['assigned_user_name']
                                = trim($row['assigned_user_first_name'] . " " . $row['assigned_user_last_name']);
                        /**
                         * Populating Partner Data in a List
                         */
                        $leadData['partner'] = $partnerData;
                    }
                }

                /**
                 * Address Data Lookup 
                 */
                $fields = '';
                $addressData = array();
                $address = array();

                /**
                 * Preparing field list for query
                 */
                foreach ($this->addressFields as $field) {
                    $fields .= $field . ',';
                }
                $fields = rtrim($fields, ',');

                $addressQuery = "SELECT $fields,lc.leads_dot10_addresses_1leads_ida  FROM dot10_addresses"
                        . " LEFT JOIN dot10_addresses_cstm ON dot10_addresses.id = dot10_addresses_cstm.id_c"
                        . " LEFT JOIN leads_dot10_addresses_1_c lc "
                        . " ON lc.leads_dot10_addresses_1dot10_addresses_idb = dot10_addresses.id AND lc.deleted = 0"
                        . " LEFT JOIN leads l ON l.id = lc.leads_dot10_addresses_1leads_ida AND l.deleted = 0"
                        . " LEFT JOIN contacts_dot10_addresses_1_c cd"
                        . " ON cd.contacts_dot10_addresses_1dot10_addresses_idb = dot10_addresses.id AND cd.deleted = 0"
                        . " LEFT JOIN contacts c ON c.id = cd.contacts_dot10_addresses_1contacts_ida AND c.deleted = 0"
                        . " WHERE dot10_addresses.deleted = 0 AND l.id  = '$leadId'"
                        . " ORDER BY dot10_addresses.date_modified DESC LIMIT 0,2";


                $addressResult = $GLOBALS['db']->query($addressQuery);
                if ($addressResult->num_rows > 0) {
                    while ($row = $GLOBALS['db']->fetchByAssoc($addressResult)) {
                        $addressData = $row;
                        $addressData['contacts_dot10_addresses_1_name'] = trim($row['cfname'] . " " . $row['clname']);
                        $addressData['leads_dot10_addresses_1_name'] = trim($row['lfname'] . " " . $row['llname']);
                        /**
                         * Populating Address Data in a List
                         */
                        $address[] = $addressData;
                    }
                    $leadData['address'] = $address;
                }

                /**
                 * Application Data Lookup 
                 */
                $fields = '';
                $applicationData = array();
                $application = array();

                /**
                 * Preparing field list for query
                 */
                foreach ($this->appFields as $field) {
                    $fields .= $field . ',';
                }
                $fields = rtrim($fields, ',');

                $applicationQuery = "SELECT $fields,lo.leads_opportunities_1leads_ida FROM opportunities"
                        . " LEFT JOIN opportunities_cstm ON opportunities.id = opportunities_cstm.id_c"
                        . " LEFT JOIN leads_opportunities_1_c lo"
                        . " ON lo.leads_opportunities_1opportunities_idb = opportunities.id AND lo.deleted = 0"
                        . " WHERE opportunities.deleted = 0 AND lo.leads_opportunities_1leads_ida = '$leadId'"
                        . " ORDER BY opportunities.date_modified DESC LIMIT 0,3";

                $applicationResult = $GLOBALS['db']->query($applicationQuery);
                if ($applicationResult->num_rows > 0) {
                    while ($row = $GLOBALS['db']->fetchByAssoc($applicationResult)) {
                        $applicationData = $row;
                        /**
                         * Populating Application Data in a List
                         */
                        $application[] = $applicationData;
                    }
                    $leadData['application'] = $application;
                }

                /**
                 * Contract Data Lookup 
                 */
                $fields = '';
                $contractData = array();

                /**
                 * Preparing field list for query
                 */
                foreach ($this->contractFields as $field) {
                    $fields .= $field . ',';
                }
                $fields = rtrim($fields, ',');

                $contractQuery = "SELECT $fields,cl.contracts_leads_1leads_idb FROM contracts"
                        . " LEFT JOIN contracts_cstm ON contracts.id = contracts_cstm.id_c"
                        . " LEFT JOIN contracts_leads_1_c cl "
                        . " ON cl.contracts_leads_1contracts_ida = contracts.id AND cl.deleted = 0"
                        . " LEFT JOIN  contracts_contacts cc ON cc.contract_id = contracts.id AND cc.deleted = 0"
                        . " LEFT JOIN contacts c ON c.id = cc.contact_id AND c.deleted = 0"
                        . " WHERE contracts.deleted = 0 AND cl.contracts_leads_1leads_idb = '$leadId'"
                        . " GROUP BY cl.contracts_leads_1leads_idb ORDER BY contracts.date_modified DESC LIMIT 0,1";

                $contractResult = $GLOBALS['db']->query($contractQuery);
                if ($contractResult->num_rows > 0) {
                    while ($row = $GLOBALS['db']->fetchByAssoc($contractResult)) {
                        $contractData = $row;
                        $contractData['contacts_contracts_1_name'] = trim($row['cfname'] . " " . $row['clname']);
                        /**
                         * Populating Contract Data in a List
                         */
                        $leadData['contract'] = $contractData;
                    }
                }
            }
        }


        return $leadData;
    }

}
