<?php


class AmoFunctions
{
    private AmoCrmV4Client $amoConnect;
    private InputData $valuesData;
    private array $leadCustomFieldsValues;
    private array $contactCustomFieldsValues;
    private array $fieldsId = [
        //тип сделки - список (В РАБОТЕ)
        'leadType' => 473252,
        //ID сделки, Партнерка
        'partnerLeadId' => 561182,
        //жилой комплекс - список
        'complex' => 312577,
        //объект интереса - список
        'objectType' => 219667,
        //источник заявки - список
        'source' => 219671,
        //агенство недвижимости - список
        'agencyName' => 552424,
    ];

    public function __construct(AmoCrmV4Client $amoConnect)
    {
        $this->amoConnect = $amoConnect;
    }

    public function getLeadCustomFields()
    {
        return $this->leadCustomFieldsValues;
    }

    public function getContactCustomFields()
    {
        return $this->contactCustomFieldsValues;
    }

    public function getFieldsId()
    {
        return $this->fieldsId;
    }

    public function checkAgentAgencyValue($agentContactData)
    {
        foreach ($agentContactData['_embedded']['contacts'][0]['custom_fields_values'] as $customField) {
            if ($customField['field_id'] == 551266) {
                if ($customField['values'][0]['value'] == $this->getValidContactAgencyName()) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    public function patchAgent($agentContactId)
    {
        $postFields = [
            'id' => $agentContactId,
            'custom_fields_values' => [
                [
                    'field_id' => 551266,
                    'values' => [
                        [
                            'value' => (string) $this->getValidContactAgencyName()
                        ]
                    ]
                ]
            ]
        ];
        $result = $this->amoConnect->POSTRequestApi("contacts", [$postFields], 'PATCH');
        return $result;
    }

    public function queryContactSearch($clientPhone)
    {
        return $this->amoConnect->GETRequestApi('contacts', ['query' => $clientPhone, 'with' => 'leads']);
    }

    public function queryLeadSearch($partnerLeadId)
    {
        return $this->amoConnect->GETRequestApi('leads', ['query' => $partnerLeadId]);
    }

    public function searchLastEventDate($leadsId, $clientContactId)
    {
        $leadEventsData = $this->searchLastLeadEvent($leadsId);
        $closedTasks = $this->searchLastCompletedTask($leadsId, $clientContactId);
        $contactEventsData = $this->searchLastContactEvent($clientContactId);
        $merged = array_merge($leadEventsData, $closedTasks, $contactEventsData);
        return max($merged);
    }

    private function searchLastLeadEvent($leadsId)
    {
        $leadsIdToEventRequest = array_chunk($leadsId, 10);
        $eventsData = [];
        foreach ($leadsIdToEventRequest as $leadsIdArr) {
            $eventsSearchResult = $this->amoConnect->GETRequestApi('events', [
                'limit' => 1,
                'filter[entity]' => 'lead',
                'filter[entity_id]' => $leadsIdArr,
                'filter[type]' => ['lead_added', 'task_completed', 'incoming_call', 'outgoing_call', 'incoming_chat_message', 'outgoing_chat_message']
            ]);
            if (!is_null($eventsSearchResult)) {
                $eventsData[] = $eventsSearchResult['_embedded']['events'][0]['created_at'];
            }
        }
        return $eventsData;

    }

    private function searchLastCompletedTask($leadsId, $clientContactId)
    {
        $leadsIdToTaskRequest = array_chunk($leadsId, 10);
        $tasks = [];
        $timestamp = time() - 8035200;
        foreach ($leadsIdToTaskRequest as $leadsIdArr) {
            $tasksSearchResult = $this->amoConnect->GETRequestApi('tasks', [
                'limit' => 1,
                'filter[entity_type]' => 'leads',
                'filter[is_completed]' => 1,
                'filter[updated_at]' => $timestamp,
                'filter[entity_id]' => $leadsIdArr,
            ]);
            if (!is_null($tasksSearchResult)) {
                $tasks[] = $tasksSearchResult['_embedded']['tasks'][0]['updated_at'];
            }
        }

        $contactsIdToTaskRequest = array_chunk($clientContactId, 10);
        foreach ($contactsIdToTaskRequest as $contactsIdArr) {
            $conTasksSearchResult = $this->amoConnect->GETRequestApi('tasks', [
                'limit' => 1,
                'filter[entity_type]' => 'contacts',
                'filter[is_completed]' => 1,
                'filter[updated_at]' => $timestamp,
                'filter[entity_id]' => $contactsIdArr,
            ]);
            if (!is_null($conTasksSearchResult)) {
                $tasks[] = $conTasksSearchResult['_embedded']['tasks'][0]['updated_at'];
            }
        }
        return $tasks;
    }

    private function searchLastContactEvent($contactsId)
    {
        $contactsIdToEventRequest = array_chunk($contactsId, 10);
        $eventsData = [];
        foreach ($contactsIdToEventRequest as $contactsIdArr) {
            $eventsSearchResult = $this->amoConnect->GETRequestApi('events', [
                'limit' => 100,
                'filter[entity]' => 'contact',
                'filter[entity_id]' => $contactsIdArr,
                'filter[type]' => ['contact_added', 'task_completed', 'incoming_call', 'outgoing_call', 'incoming_chat_message', 'outgoing_chat_message']
            ]);
            if (!is_null($eventsSearchResult)) {
                $eventsData[] = $eventsSearchResult['_embedded']['events'][0]['created_at'];
            }
        }
        return $eventsData;
    }

    public function setValuesData(InputData $data)
    {
        $this->valuesData = $data;
    }

    /**
     * Summary of buildCustomFields
     * @param mixed $fieldsMap = [
     *                              [field_id, value], 
     *                              [field_id2, value2],
     *                              ...
     *                           ]
     * 
     * @return mixed
     */

    public function buildLeadFields($clientContactId, $agentContactId)
    {
        $leadStruct = [
            'responsible_user_id' => 6370485,
            'name' => $this->valuesData->getClientName(),
            'pipeline_id' => $this->valuesData->getPipelineId(),
            '_embedded' => [
                'contacts' => []
            ]
        ];

        if ($leadStruct['pipeline_id'] == 222958) {
            $leadStruct['status_id'] = 28367106;
        }

        $main = true;
        foreach ($clientContactId as $clientId) {
            $clientContact = [
                'id' => $clientId
            ];
            if ($main) {
                $clientContact['is_main'] = true;
            }
            $leadStruct['_embedded']['contacts'][] = $clientContact;
            $main = false;
        }

        foreach ($agentContactId as $agentId) {
            $agentContact = [
                'id' => $agentId
            ];

            $leadStruct['_embedded']['contacts'][] = $agentContact;
        }

        $customFields = $this->buildCustomFields();
        if (!empty($customFields)) {
            $leadStruct['custom_fields_values'] = $customFields;
        }

        return $leadStruct;
    }

    public function buildClientContactFields(): array
    {
        $contactStruct = [
            'responsible_user_id' => 6370485,
            'name' => $this->valuesData->getClientName(),
            'custom_fields_values' => [
                [
                    'field_code' => 'PHONE',
                    'field_type' => 'WORK',
                    'values' => [
                        [
                            'value' => (string) $this->valuesData->getClientPhone()
                        ]
                    ]
                ]
            ]
        ];

        return $contactStruct;
    }

    public function buildAgentContactFields(): array
    {
        $contactStruct = [
            'responsible_user_id' => 6370485,
            'name' => $this->valuesData->getAgentName(),
            'custom_fields_values' => [
                [
                    'field_code' => 'PHONE',
                    'field_type' => 'WORK',
                    'values' => [
                        [
                            'value' => (string) $this->valuesData->getAgentPhone()
                        ]
                    ]
                ],
                [
                    'field_id' => 551264,
                    'values' => [
                        [
                            'value' => true
                        ]
                    ]
                ]
            ]
        ];
        if (!empty($this->valuesData->getAgencyName())) {
            $validAgencyName = $this->getValidContactAgencyName();
            if ($validAgencyName) {
                $agencyName = [
                    'field_id' => 551266,
                    'values' => [
                        [
                            'value' => (string) $validAgencyName
                        ]
                    ]
                ];
                array_push($contactStruct['custom_fields_values'], $agencyName);
            }
        }

        return $contactStruct;
    }

    private function buildCustomFields(): array
    {
        $fieldsMap = $this->getleadCustomFieldsMap();
        $customFields = [];
        foreach ($fieldsMap as [$id, $value]) {
            if (!empty($value)) {
                $fieldStruct = [
                    'field_id' => (int) $id,
                    'values' => [
                        [
                            'value' => $value
                        ]
                    ]
                ];
                $customFields[] = $fieldStruct;
            }
        }
        return $customFields;
    }

    private function getleadCustomFieldsMap()
    {
        $leadCustomFieldsMap = [
            [$this->fieldsId['leadType'], $this->valuesData->getLeadType()],
            [$this->fieldsId['partnerLeadId'], $this->valuesData->getEntityId()],
            [$this->fieldsId['complex'], $this->getValidComplexName()],
            [$this->fieldsId['objectType'], $this->valuesData->getObjectType()],
            [$this->fieldsId['source'], $this->valuesData->getApplicationSource()],
            [$this->fieldsId['agencyName'], $this->getValidLeadAgencyName()],
        ];
        return $leadCustomFieldsMap;
    }

    private function getValidComplexName()
    {
        foreach ($this->leadCustomFieldsValues as $customField) {
            if ($customField['id'] == $this->fieldsId['complex']) {
                foreach ($customField['enums'] as $enum) {
                    if (strtolower($enum['value']) == strtolower($this->valuesData->getComplexName())) {
                        $validName = $enum['value'];
                        return $validName;
                    }
                }
            }
        }
        return null;
    }

    private function getValidLeadAgencyName()
    {
        foreach ($this->leadCustomFieldsValues as $customField) {
            if ($customField['id'] == $this->fieldsId['agencyName']) {
                foreach ($customField['enums'] as $enum) {
                    if (strtolower($enum['value']) == strtolower($this->valuesData->getAgencyName())) {
                        $validName = $enum['value'];
                        return $validName;
                    }
                }
            }
        }
        return null;
    }

    private function getValidContactAgencyName()
    {
        foreach ($this->contactCustomFieldsValues as $customField) {
            if ($customField['id'] == 551266) {
                foreach ($customField['enums'] as $enum) {
                    if (strtolower($enum['value']) == strtolower($this->valuesData->getAgencyName())) {
                        $validName = $enum['value'];
                        return $validName;
                    }
                }
            }
        }
        return null;
    }

    public function createLead($leadStruct)
    {
        return $this->amoConnect->POSTRequestApi('leads', [$leadStruct]);
    }

    public function createContact($contactStruct)
    {
        return $this->amoConnect->POSTRequestApi('contacts', [$contactStruct]);
    }

    public function createLeadNote($leadId)
    {
        $noteFields = [
            [
                'note_type' => 'common',
                'params' => [
                    'text' => $this->valuesData->getMessage(),
                ]
            ]
        ];
        return $this->amoConnect->POSTRequestApi("leads/$leadId/notes", $noteFields);
    }

    public function getContactLeadsId($contact)
    {
        $contactLeadsId = [];
        foreach ($contact['_embedded']['contacts'] as $contact) {
            foreach ($contact['_embedded']['leads'] as $linkedLead) {
                $contactLeadsId[] = $linkedLead['id'];
            }
        }
        return $contactLeadsId;
    }

    public function getContactsId($contactSearchResult)
    {
        $contactsId = [];
        foreach ($contactSearchResult['_embedded']['contacts'] as $contact) {
            $contactsId[] = $contact['id'];
        }
        return $contactsId;
    }

    public function getLeadsData(array $leadsId)
    {
        return $this->amoConnect->GETRequestApi('leads', ['filter[id]' => $leadsId, 'limit' => 250]);
    }

    public function filterLeadsByComplexName($leads)
    {
        $findedLeadsId = [];
        foreach ($leads['_embedded']['leads'] as $leadData) {
            foreach ($leadData['custom_fields_values'] as $customFieldData) {
                if ($customFieldData['field_id'] == $this->fieldsId['complex'] && strtolower($customFieldData['values'][0]['value']) == strtolower($this->valuesData->getComplexName())) {
                    $findedLeadsId[] = $leadData['id'];
                    break;
                }
            }
        }
        return $findedLeadsId;
    }

    public function createAgentTask($agentContactId)
    {
        $taskFields = [
            'entity_id' => $agentContactId,
            'entity_type' => 'contacts',
            'responsible_user_id' => 6370485,
            'task_type_id' => 1,
            'text' => 'Назначить встречу по Клиенту',
            'complete_till' => strtotime('tomorrow')
        ];
        return $this->amoConnect->POSTRequestApi("tasks", [$taskFields]);
    }

    public function createLeadTask($leadId)
    {
        $taskFields = [
            'entity_id' => $leadId,
            'entity_type' => 'leads',
            'responsible_user_id' => 6370485,
            'task_type_id' => 2521737,
            'text' => 'Агент продлил закрепление',
            'complete_till' => strtotime('tomorrow')
        ];
        return $this->amoConnect->POSTRequestApi("tasks", [$taskFields]);
    }

    public function getLeadData($leadId)
    {
        $leadData = $this->amoConnect->GETRequestApi("leads/$leadId");
        return $leadData;
    }

    public function getPartnerEntityId($leadData)
    {
        $partnerLeadId = null;
        foreach ($leadData['custom_fields_values'] as $customField) {
            if ($customField['field_id'] == $this->fieldsId['partnerLeadId']) {
                $partnerLeadId = $customField['values'][0]['value'];
            }
        }
        return $partnerLeadId;
    }

    public function getAmoLeadCustomFields()
    {
        $values = $this->amoConnect->GETRequestApi('leads/custom_fields');
        $this->leadCustomFieldsValues = $values['_embedded']['custom_fields'];
    }

    public function getAmoContactCustomFields()
    {
        $values = $this->amoConnect->GETRequestApi('contacts/custom_fields');
        $this->contactCustomFieldsValues = $values['_embedded']['custom_fields'];
    }

    public function getLeadTasks($leadId)
    {
        $tasks = $this->amoConnect->GETRequestApi('tasks', [
            'filter[entity_type]' => 'leads',
            'filter[entity_id]' => $leadId,
            'filter[task_type]' => 2521737,
            'text' => 'Агент продлил закрепление'
        ]);
        return $tasks;
    }
}