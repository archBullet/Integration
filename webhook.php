<?php
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/InputData.php';
require_once __DIR__ . '/src/connect.php';
require_once __DIR__ . '/src/AmoCrmV4Client.php';
require_once __DIR__ . '/src/AmoFunctions.php';
require_once __DIR__ . '/src/Logger.php';

header('Content-Type: application/json');
http_response_code(200);

$jsonData = json_decode(file_get_contents('php://input'), true);

$inputData = [
    'json' => $jsonData,
    'GET' => $_GET,
    'POST' => $_POST
];
$amoConnect = new AmoCrmV4Client(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
$amoFun = new AmoFunctions($amoConnect);

if (!empty($inputData['json']) || !empty($inputData['GET']) || !empty($inputData['POST'])) {
    controlWrite('data.json', $inputData);
}
$hookData = $inputData['json'];
$trigger = $inputData['POST'];

//хук из триггера на изменение статуса заявки
if (!empty($trigger)) {
    try {
        $log = new Logger('trigger');
        $log->setNewRunFlag(date('d.m.Y H:i:s', time()));
        $log->AddToLog('inputData', $inputData);

        $status = $inputData['GET']['status'];
        if (empty($status)) {
            throw new Exception('Пустой статус');
        }
        $statusCode = getStatusCode($status);
        if (!$statusCode) {
            throw new Exception('Невалидный статус');
        }
        $status = switchStatus($status);

        $leadId = $_POST['leads']['status'][0]['id'];
        if (!$leadId) {
            throw new Exception('Нет id сделки или не хук смены статуса');
        }

        $leadData = $amoFun->getLeadData($leadId);
        if (!$leadData['id']) {
            throw new Exception('Ошибка получения данных сделки');
        }

        $partnerLeadId = $amoFun->getPartnerEntityId($leadData);
        if (!$partnerLeadId) {
            throw new Exception('Не найден id сделки партнера');
        }

        $postFields = [
            'Id' => $partnerLeadId,
            'EntityId' => $partnerLeadId,
            'EntityType' => 'lead',
            'Action' => 'update',
            'Message' => $status,
            'Parameters' => [
                'status' => $status,
                'statuscode' => $statusCode
            ]
        ];

        $updateResult = changePartnerLeadStatus($postFields);

        if ($updateResult['http_code'] != 200) {
            $log->AddToLog('postFields', $postFields);
            throw new Exception('Ошибка обновления статуса');
        }
    } catch (Exception $e) {
        $log->addToLog('Error', $e->getMessage());
        echo error($e->getMessage());
    } finally {
        $log->writeToJsonFile();
    }
}
//хук из партнерки на фиксацию и продление
if (!empty($hookData)) {
    try {
        $log = new Logger('hook');
        $log->setNewRunFlag(date('d.m.Y H:i:s', time()));
        $log->AddToLog('inputData', $inputData);
        $data = new InputData($hookData);
        $amoFun->setValuesData($data);
        $eventType = $data->getEventType(); //тип события, для фиксации 'fix', для продления 'extended'
        //новая заявка
        if ($eventType == 'fix') {
            //поиск клиента по номеру телефона
            $clientContactId = [];
            $clientSearchResult = $amoFun->queryContactSearch($data->getClientPhone());
            $log->AddToLog('clientSearchResult', $clientSearchResult);
            if ($clientSearchResult) {
                $clientContactId = $amoFun->getContactsId($clientSearchResult);
                $contactLeadsId = $amoFun->getContactLeadsId($clientSearchResult);
                //поиск сделок контакта с полученным ЖК 
                if (!empty($contactLeadsId)) {
                    $leads = $amoFun->getLeadsData($contactLeadsId);
                    $log->AddToLog('leads', $leads);
                    $findedLeadsId = $amoFun->filterLeadsByComplexName($leads);
                    if (!empty($findedLeadsId)) {
                        //поиск по событиям “задачи”, “входящие/исходящие звонки”, “входящие/исходящие сообщения”
                        $lastEventDate = $amoFun->searchLastEventDate($findedLeadsId, $clientContactId);
                        $log->AddToLog('lastEventDate', $lastEventDate);
                        $offset = 8035200; //93 дня
                        if ($lastEventDate && ($lastEventDate + $offset) > time()) {
                            $postFields = setNotUniqLeadPostFields($data->getEntityId(), 'Заявка не уникальна', 'notunique');
                            echo json_encode($postFields);
                            throw new Exception("У клиента есть активные сделки в ЖК '{$data->getComplexName()}'");

                        }
                    }
                }
            }

            //контакт клиента
            if (empty($clientContactId)) {
                $clientContactStruct = $amoFun->buildClientContactFields();
                $clientCreateResult = $amoFun->createContact($clientContactStruct);
                $log->AddToLog('clientCreateResult', $clientCreateResult);
                $clientContactId[] = $clientCreateResult['_embedded']['contacts'][0]['id'];
            }

            //контакт агента
            $amoFun->getAmoContactCustomFields();
            $agentSearchResult = $amoFun->queryContactSearch($data->getAgentPhone());
            $log->AddToLog('agentSearchResult', $agentSearchResult);
            $agentContactId = [];
            if ($agentSearchResult) {
                $agentContactId = $amoFun->getContactsId($agentSearchResult);
                if (!empty($data->getAgencyName())) {
                    $checkResult = $amoFun->checkAgentAgencyValue($agentSearchResult);
                    if (!$checkResult) {
                        $patchResult = $amoFun->patchAgent($agentContactId[0]);
                    }
                }
            } else {
                $agentContactStruct = $amoFun->buildAgentContactFields();
                $agentCreateResult = $amoFun->createContact($agentContactStruct);
                $log->AddToLog('agentCreateResult', $agentCreateResult);
                $agentContactId[] = $agentCreateResult['_embedded']['contacts'][0]['id'];
            }
            $contactTaskCreateResult = $amoFun->createAgentTask($agentContactId[0]);
            $log->AddToLog('contactTaskCreateResult', $contactTaskCreateResult);

            //создание сделки
            $amoFun->getAmoLeadCustomFields();
            $leadStruct = $amoFun->buildLeadFields($clientContactId, $agentContactId);
            $leadCreateResult = $amoFun->createLead($leadStruct);
            $log->AddToLog('leadCreateResult', $leadCreateResult);
            $leadId = $leadCreateResult['_embedded']['leads'][0]['id'];

            if ($leadId) {
                $postFields = setFixLeadPostFields($data->getEntityId(), 'Заявка уникальна', 'active');
                echo json_encode($postFields);
            }

            $leadNoteCreate = $amoFun->createLeadNote($leadId);
            $log->AddToLog('leadNoteCreate', $leadNoteCreate);
        }

        //продление
        if ($eventType == 'extended') {
            $leadSearchResult = $amoFun->queryLeadSearch($data->getEntityIdFromTask());
            $log->AddToLog('leadSearchResult', $leadSearchResult);
            $leadCreateDate = $leadSearchResult['_embedded']['leads'][0]['created_at'];

            if (empty($leadCreateDate)) {
                throw new Exception('Сделка для продления не найдена');
            }
            $leadId = $leadSearchResult['_embedded']['leads'][0]['id'];
            $tasksSearchResult = $amoFun->getLeadTasks($leadId);

            if (!is_null($tasksSearchResult)) {
                $response = setTaskPostFields($data->getEntityId(), 'Активный', 'active', 'Данная заявка ранее уже продлевалась. Продление невозможно.', 0);
                echo json_encode($response);

                throw new Exception('Заявка ранее была продлена');
            }

            $offset = 8035200; //93 дня
            if (($leadCreateDate + $offset) > time()) {
                $leadTaskCreateResult = $amoFun->createLeadTask($leadId);
                $log->AddToLog('leadTaskCreateResult', $leadTaskCreateResult);
            }

            $response = setTaskPostFields($data->getEntityId(), 'Активный', 'active', 'Заявка продлена');
            echo json_encode($response);

            $postFields = [
                'Id' => $data->getEntityIdFromTask(),
                'EntityId' => $data->getEntityIdFromTask(),
                'EntityType' => 'lead',
                'Action' => 'update',
                'Parameters' => [
                    'uniquecode' => 'extendedto',
                    'uniqueuntil' => date('Y-m-d\TH:i:s', time() + 8035200)
                ]
            ];

            $updateResult = changePartnerLeadStatus($postFields);
        }
    } catch (Exception $e) {
        $log->addToLog('Error', $e->getMessage());
    } finally {
        $log->writeToJsonFile();
    }
}