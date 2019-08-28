<?php

/**
 * CRED-1219 : Automated Dispatch of Document-Categories
 */
class ContractHelper
{
    /**
     * @params String $bean
     * @params String $event
     * @params String  $arguments
     *
     * @description: Function to fetch contract info and provider and initiate auto-dispatch
     * CRED-1301 : CR Doc-Mgmt from User-Training | Doc-Categories and Explanation-Value
     */
    public function createandLinkDocuments($leadId, $contractId, $provider)
    {
        global $db, $app_list_strings;
        
        /**
         * CRED-1356 : SubPanel Documents | Improvement Usability - Cat-Sorting
         * CRED-1457 : Integration of new Provider: SWK
         * CRED-1425 : Contract-Creation: Auto-Dispatch of Cat-Tracking-Records by Provider
         */
        $commonCat = array('vertrag', 'avb', 'budgetblatt', 'formular_a', 'auszahlungsformular');
        $providersToCatMapping = array(
            'rci' => array('antrag', 'formular_lsv', 'zahlungsauftrag_fremdbank_vertragsdokument'),
            'cash_gate' => array(
                'verzichtserklarung_vertragsdokument',
                'formular_lsv', 'formular_dauerauftrag',
                'freiwilliger_ppiantrag_cembra_cashgate_vertragsdokument',
                'beitrittsserklarung_ppi'
            ),
            'cembra' => array(
                'verzichtserklarung_vertragsdokument',
                'zahlungsauftrag_fremdbank_vertragsdokument',
                'freiwilliger_ppiantrag_cembra_cashgate_vertragsdokument'
            ),
            'swk' => array('formular_lsv')
        );
        $categories = isset($providersToCatMapping[$provider]) ? $providersToCatMapping[$provider] : array();
        $GLOBALS['log']->debug('Provider Specific categories ..', $categories);
        $categories = array_unique(array_merge($categories, $commonCat));
        $GLOBALS['log']->debug('All categories ..', $categories);
        $trackingRelatedData = array();
        $leadRelatedData = array();
        
        /**
         * CRED-1383 : Automated Dispatch of Doc-Tracking-Categories
         * upon Creation of Contract
         */
        $categories = $this->unsetCategory($categories, $provider);

        foreach ($categories as $category) {
            $GLOBALS['log']->debug('Creating document for Category '.$category);
            $translatedCat = isset($app_list_strings['dotb_document_category_list'][$category]) ?
                            $app_list_strings['dotb_document_category_list'][$category] : $category;
            
            $docTrackItemBean = BeanFactory::newBean('dotb7_document_tracking');
            $docTrackItemBean->name = $translatedCat;
            $docTrackItemBean->category = $category;
            $docTrackItemBean->status = 'fehlt';
            /**
             * CRED-1361 : Provider-Value for Document-Tracking-Records
             */
            $docTrackItemBean->provider_list = $provider;
            /**
             * CRED-1356 : SubPanel Documents | Improvement Usability - Cat-Sorting
             */
            $docTrackItemBean->signed_contracts = 1;
            $doctrackId = $docTrackItemBean->save();
            
            
            $document = BeanFactory::newBean('Documents');
            $document->name = $translatedCat;
            $docId = $document->save();
            $trackingRelatedData[] = array(
                'ida' => $docId,
                'idb' => $doctrackId,
            );
            $leadRelatedData[] = array(
                'ida' => $leadId,
                'idb' => $docId,
            );
            $GLOBALS['log']->debug('Document created for Category '.$category);
        }
        $this->insertDataIntoTable($trackingRelatedData, 'documents_dotb7_document_tracking_1_c');
        $this->insertDataIntoTable($leadRelatedData, 'leads_documents_1_c');

        /**
         * CRED-1433 : Relating Cat-Value to Translation-Value
         * Translation needed to be done for all auto-dispatched document trackings
         */
        require_once 'custom/modules/Documents/trackingTranslation.php';
        $docTranslation = new trackingTranslation();
        $arguments = array();
        $arguments['related_module'] = 'Leads';
        $arguments['link'] = 'leads_documents_1';

        foreach ($leadRelatedData as $track) {
            $documentBean = BeanFactory::getBean('Documents', $track['idb']);
            if ($documentBean->id) {
                $arguments['related_id'] = $track['ida'];
                $docTranslation->updateTrackingRecordTranslation($documentBean, null, $arguments);
            }
        }
    }
    
    public function insertDataIntoTable($data, $table)
    {
        $GLOBALS['log']->debug('Inserting data in relationship table: '.$table);
        global $db;
        $insertQuery = "INSERT INTO {$table} VALUES ";
        $values = "";
        $sep = " ";
        foreach ($data as $row) {
            $values .= $sep."(uuid(), now(), 0, '{$row['ida']}', '{$row['idb']}', NULL)";
            $sep = ",";
        }
        if (!empty($values)) {
            $insertQuery = $insertQuery . $values;
            $GLOBALS['log']->debug('Insert Query: ' . $insertQuery);
            $result = $db->query($insertQuery);
            $GLOBALS['log']->debug('Data Inserted: ', $result);
            return $result;
        }
        $GLOBALS['log']->debug('No Data to insert');
        return false;
    }
    
    /**
     * @params String $leadId
     * @params String $contractId
     * @params String $provider
     *
     * @description: Function to identify auto-dispatched documents and change status to VOID
     * CRED-1301 : CR Doc-Mgmt from User-Training | Doc-Categories and Explanation-Value
     * CRED-1457 : Integration of new Provider: SWK
     */
    public function voidDocuments($leadId, $contractId, $provider)
    {
        global $db, $app_list_strings;
        
        /**
         * CRED-1425 : Contract-Creation: Auto-Dispatch of Cat-Tracking-Records by Provider
         */
        $commonCat = array('vertrag', 'avb', 'budgetblatt', 'formular_a', 'auszahlungsformular');
        $providersToCatMapping = array(
            'rci' => array('antrag', 'formular_lsv', 'zahlungsauftrag_fremdbank_vertragsdokument'),
            'cash_gate' => array(
                'verzichtserklarung_vertragsdokument',
                'formular_lsv', 'formular_dauerauftrag',
                'freiwilliger_ppiantrag_cembra_cashgate_vertragsdokument',
                'beitrittsserklarung_ppi'
            ),
            'cembra' => array(
                'verzichtserklarung_vertragsdokument',
                'zahlungsauftrag_fremdbank_vertragsdokument',
                'freiwilliger_ppiantrag_cembra_cashgate_vertragsdokument'
            ),
            'swk' => array('formular_lsv')
        );
        $categories = isset($providersToCatMapping[$provider]) ? $providersToCatMapping[$provider] : array();
        $GLOBALS['log']->debug('Provider Specific categories ..', $categories);
        $categories = array_unique(array_merge($categories, $commonCat));
        $GLOBALS['log']->debug('All categories ..', $categories);
        
        /**
         * CRED-1383 : Automated Dispatch of Doc-Tracking-Categories
         * upon Creation of Contract
         * CRED-1490 : SubPanel Documents on Lead: Handling-Exceptions | Provider Check
         */
        $categories = $this->unsetCategory($categories, $provider);
        
        try {
            $categoriesAll = implode(',', array_map('add_quotes', $categories));
            $updateQuery = "UPDATE  dotb7_document_tracking dt
            JOIN documents_dotb7_document_tracking_1_c  ddt 
            ON ddt.documents_dotb7_document_tracking_1dotb7_document_tracking_idb = dt.id
            JOIN documents d ON ddt.documents_dotb7_document_tracking_1documents_ida = d.id
            JOIN leads_documents_1_c lc ON lc.leads_documents_1documents_idb = d.id
            SET dt.status = 'void'
            WHERE dt.deleted = 0 AND ddt.deleted = 0 AND d.deleted = 0 AND lc.deleted = 0
            AND dt.category IN ($categoriesAll) AND dt.provider_list = '{$provider}'
            AND lc.leads_documents_1leads_ida = '{$leadId}'";
            $GLOBALS['db']->query($updateQuery);
        } catch (Exception $ex) {
            $GLOBALS['log']->fatal("Query Failed to Update Status :-".$updateQuery);
        }
    }
    
    /**
     * CRED-1383 : Automated Dispatch of Doc-Tracking-Categories
     * upon Creation of Contract
     * CRED-1490 : SubPanel Documents on Lead: Handling-Exceptions
     * 
     * @param Array  $categories
     * @param String $provider
     * @return Array
     */
    public function unsetCategory($categories, $provider)
    {
        $providerMap = array('cash_gate' => 'avb', 'bob' => 'auszahlungsformular');
        if (isset($providerMap[$provider])) {
            $index = array_search($providerMap[$provider], $categories);
            unset($categories[$index]);
        }
        return $categories;
    }
}
