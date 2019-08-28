<?php

/**
 * CRED-1362 : Auto-Task-Creation with Summary of Document-Tracking-Records in Description
 */
class TaskHelper
{
    public $pendingDescription = array();
    /**
     * Create task according to provided parameters
     * @param Array $params subject, due date, team name, parent type, parent id
     * @return string
     */
    public function createTask($params)
    {
        if (!empty($params['subject'])) {
            $task = new Task();
            $task->name = $params['subject'];
            $task->assigned_user_id = $params['assigned_to'];
            $task->date_due = $params['due_date'];
            if (empty($task->date_due)) {
                $now = new DateTime($GLOBALS['timedate']->nowDb());
                $now = $now->format('Y-m-d H:i:s');
                $task->date_due = $now;
            }
            $task->parent_type = $params['parent_type'];
            $task->parent_module = $params['parent_type'];
            $task->parent_id = $params['parent_id'];
            $teamSet = new TeamSet();
            $teamSet->getTeams($task->team_set_id);
            $task->load_relationship('teams');
            $task->teams->add($this->getTeamID($params['team_name']));
            $task->description = !empty($params['task_description']) ? $params['task_description']
                : $this->getDescriptionString(
                    $params['parent_type'],
                    $params['parent_id'],
                    false
            );
            if ($params['parent_type'] == 'Leads' &&
                $task->load_relationship('leads') &&
                !empty($params['parent_id'])) {
                $task->leads->add($params['parent_id']);
            }

            $task->save();
            if (!empty($params['return_bean']) && $params['return_bean']) {
                return $task;
            }
            return $task->id;
        }
    }

    /**
     * Find team with name and return its id
     * @global $db
     * @param string $team_name
     * @return string
     */
    public function getTeamID($team_name)
    {
        if (!empty($team_name)) {
            global $db;
            $result = $db->query(
                "SELECT id
                FROM teams
                WHERE name = '$team_name'
                AND deleted = 0
                LIMIT 1"
            );
            $team = $db->fetchByAssoc($result);
            return $team['id'];
        }
        return null;
    }

    /**
     * Return description with Categories per line
     * @param string $parent_type
     * @param string $parent_id
     * @return string
     */
    public function getDescriptionString($parent_type, $parent_id, $autoDispatched)
    {
        if (!empty($parent_type) && !empty($parent_id)) {
            $s_query = new SugarQuery();
            $s_query->select()->fieldRaw('dt.category');
            $s_query->select()->fieldRaw('dt.status');
            $s_query->select()->fieldRaw('dt.document_explanation');
            $s_query->from(
                BeanFactory::newBean('Documents'),
                array(
                    'alias' => 'doc',
                    'team_security' =>false
                )
            );
            $s_query->join(
                'documents_dotb7_document_tracking_1',
                array(
                    'alias' => 'dt',
                    'team_security' => false
                )
            );
            if ($parent_type == 'Leads') {
                $s_query->join(
                    'leads_documents_1',
                    array(
                        'alias' => 'ld',
                        'team_security' => false
                    )
                )->on()->equals('ld.id', $parent_id);
                $s_query->orderBy('dt.category', 'ASC');
                if ($autoDispatched) {
                    $contractQuery = "SELECT c.id,cc.provider_id_c FROM contracts c"
                            . " JOIN contracts_cstm cc ON c.id = cc.id_c"
                            . " JOIN contracts_leads_1_c clc ON clc.contracts_leads_1contracts_ida = c.id"
                            . " WHERE contracts_leads_1leads_idb = '{$parent_id}' AND c.deleted = 0"
                            . " AND clc.deleted = 0 ORDER BY c.date_modified DESC LIMIT 0,1";
                    $contractQueryResult = $GLOBALS['db']->query($contractQuery);
                    if ($contractQueryResult->num_rows > 0) {
                        $contractQueryRow = $GLOBALS['db']->fetchByAssoc($contractQueryResult);
                        $s_query->where()->equals('dt.provider_list', $contractQueryRow['provider_id_c']);
                    }
                    $s_query->where()->equals('dt.signed_contracts', '1');
                    /**
                     * CRED-1487 : Document-Tracking-Summary in Task upon Workflow-Execution | Exclude VOID documents
                     */
                    $s_query->where()->notEquals('dt.status', 'void');
                    $categoryResult = $s_query->execute();
                    if (!empty($this->pendingDescription)) {
                        foreach ($this->pendingDescription as $key => $value) {
                            $matchIndex = -1;
                            $matchIndex = array_search($this->pendingDescription[$key], $categoryResult);
                            if ($matchIndex > -1) {
                                unset($categoryResult[$matchIndex]);
                            }
                        }
                    }
                    $this->pendingDescription = array_values($categoryResult);
                } else {
                    $s_query->where()->equals('dt.required_document', '1');
                    $s_query->where()->in('dt.status', array('nok', 'fehlt'));
                    $this->pendingDescription = $s_query->execute();
                }
            }
            return $this->makeDescriptionFromCats($this->pendingDescription);
        }
    }

    /**
     * Make description string, one category per line, with fields provided
     * @param Array $categories
     * @return string
     */
    public function makeDescriptionFromCats($categories)
    {
        $description = '';
        global $app_list_strings;
        $status_map = $app_list_strings['status_list'];
        $category_map1 = $app_list_strings['document_cat_list']; // for document subpanel on user profile
        $category_map2 = $app_list_strings['dotb_document_category_list'];
        $explanation_map = $app_list_strings['document_explanation_list'];
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                $status = $status_map[$cat['status']];
                $category_text = !empty($category_map1[$cat['category']]) ? 
                    $category_map1[$cat['category']] : $category_map2[$cat['category']];
                $explanation = unencodeMultienum($cat['document_explanation']);
                foreach ($explanation as $key => $value) {
                    $explanation[$key] = $explanation_map[$value];
                }
                $explanation = implode(' | ', $explanation);
                $description .= "{$category_text}, {$status}";
                $description .= !empty($explanation) ? ", {$explanation}\r\n" : "\r\n";
            }
        }
        return $description;
    }
    
    /**
     * CRED-871 : Close tasks automatically
     * 
     * @param String $parent_type
     * @param String $parent_id
     * @return Array
     */
    public function checkOpenTasks($parent_type, $parent_id)
    {
        global $db;
        $tasksData = array();
        $countQuery = "SELECT id, name FROM tasks "
                . " WHERE parent_type = ".$db->quoted($parent_type)
                . " AND parent_id = ".$db->quoted($parent_id)." "
                . " AND status = 'open' AND deleted = 0";
        $countQueryResult = $db->query($countQuery);
        if ($countQueryResult->num_rows > 0) {
            $tasksData['open_count'] = $countQueryResult->num_rows;
            $rowData = array();
            while ($countQueryRow = $db->fetchByAssoc($countQueryResult)) {
                $rowData[] = array('id' => $countQueryRow['id'], 'name' => $countQueryRow['name']);
            }
            $tasksData['data'] = $rowData;
        }
        return $tasksData;
    }
    /**
     * CRED-1445 : Fill Shipping-Information in Task-Description on
     * Tasks with Subject: <Vertrag erstellen>
     * 
     * @param String $parent_type
     * @return String
     */
    public function populateDescriptionFromApp($parent_id)
    {
        try {
            global $app_list_strings;
            $shipping_method = '';
            $applicationQuery = "SELECT c.id,opp.method_of_mailout FROM contracts c JOIN contracts_leads_1_c cl"
                    . " ON cl.contracts_leads_1contracts_ida = c.id"
                    . " JOIN contracts_opportunities co ON co.contract_id = cl.contracts_leads_1contracts_ida"
                    . " JOIN opportunities opp ON opp.id = co.opportunity_id"
                    . " WHERE c.deleted = 0 AND cl.deleted = 0 AND co.deleted = 0 AND opp.deleted= 0"
                    . " AND cl.contracts_leads_1leads_idb = " . $GLOBALS['db']->quoted($parent_id)
                    . " ORDER BY c.date_entered DESC LIMIT 1";
            
            $applicationQueryResult = $GLOBALS['db']->query($applicationQuery);
            if ($applicationQueryResult->num_rows > 0) {
                $applicationQueryRow = $GLOBALS['db']->fetchByAssoc($applicationQueryResult);
                $shipping_method = $app_list_strings['mailout_method_list'][$applicationQueryRow['method_of_mailout']];
            }

            return $shipping_method;
        } catch (Exception $ex) {
            $GLOBALS['log']->fatal("Shipping Method Population Failure :" . $ex->getMessage());
        }
    }
}
