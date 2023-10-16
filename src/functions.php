<?php


function setFixLeadPostFields($partnerLeadId, $status, $statusCode)
{
    $postFields = [
        'Id' => $partnerLeadId,
        'EntityId' => $partnerLeadId,
        'EntityType' => 'lead',
        'Action' => 'create',
        'Processing' => 1,
        'Message' => 'Заявка создана',
        'Results' => [
            '_id_' => $partnerLeadId,
            '_type_' => 'lead',
            '_status_' => $status,
            '_statuscode_' => $statusCode,
            '_date_' => date('Y-m-d\TH:i:s', time()),
            '_uniquecode_' => 'fixingtо',
            '_uniqueuntil_' => date('Y-m-d\TH:i:s', time() + 7776000)
        ]
    ];

    return $postFields;
}
function setNotUniqLeadPostFields($partnerLeadId, $status, $statusCode)
{
    $postFields = [
        'Id' => $partnerLeadId,
        'EntityId' => $partnerLeadId,
        'EntityType' => 'lead',
        'Action' => 'create',
        'Processing' => 1,
        'Message' => $status,
        'Results' => [
            '_id_' => $partnerLeadId,
            '_type_' => 'lead',
            '_status_' => $status,
            '_statuscode_' => $statusCode,
            '_date_' => date('Y-m-d\TH:i:s', time()),
        ]
    ];

    return $postFields;
}

function setTaskPostFields($partnerLeadId, $status, $statusCode, $message, $processing = 1)
{
    $postFields = [
        'Id' => $partnerLeadId,
        'EntityId' => $partnerLeadId,
        'EntityType' => 'task',
        'Action' => 'create',
        'Processing' => 1,
        'Message' => $message,
        'Results' => [
            '_id_' => $partnerLeadId,
            '_type_' => 'task',
            '_status_' => $status,
            '_statuscode_' => $statusCode,
            '_date_' => date('Y-m-d\TH:i:s', time()),
        ]
    ];

    return $postFields;
}

function changePartnerLeadStatus($postFields)
{
    $ch = curl_init();

    $headers = [
        'Content-type: application/json'
    ];
    $token = '';
    curl_setopt($ch, CURLOPT_URL, "https://partnerka.app/api/Integrate?token=$token");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $objResponce = json_decode($response, true);
    $objResponce['http_code'] = $httpCode;
    return $objResponce;
}

function getStatusCode($status)
{
    $status = strtolower($status);
    switch ($status) {
        case 'client_transfer': //Клиент передан
        case 'visit': //Визит
        case 'booking': //Бронь
            $statusParam = 'active';
            break;

        case 'completed': // Сделка состоялась
            $statusParam = 'completed';
            break;

        case 'canceled': // Не состоялся
            $statusParam = 'canceled';
            break;

        default:
            $statusParam = null;
            break;
    }
    return $statusParam;
}

function switchStatus($status)
{
    $status = strtolower($status);
    switch ($status) {
        case 'client_transfer': //Клиент передан
            $state = 'Клиент передан';
            break;

        case 'visit': //Визит
            $state = 'Визит';
            break;

        case 'booking': //Бронь
            $state = 'Бронь';
            break;

        case 'completed': // Сделка состоялась
            $state = 'Сделка состоялась';
            break;

        case 'canceled': // Не состоялся
            $state = 'Не состоялся';
            break;

        default:
            $state = null;
            break;
    }
    return $state;
}

function error($message)
{
    return json_encode(['type' => 'error', 'message' => $message, 'data' => []], JSON_UNESCAPED_UNICODE);
}

function success($data)
{
    return json_encode(['type' => 'success', 'message' => [], 'data' => $data], JSON_UNESCAPED_UNICODE);
}

function controlWrite($filename, $data, $append = true, $filesize = 10485760)
{
    if (file_exists($filename) && filesize($filename) > $filesize) {
        unlink($filename);
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $json .= $append ? PHP_EOL : '';

    file_put_contents($filename, $json, $append ? FILE_APPEND : 0);
}

function debug($var, $name = 0)
{
    if ($var && $name != 0) {
        foreach ($GLOBALS as $varName => $value) {
            if ($value === $var) {
                $name = $varName;
                break;
            }
        }
    }
    var_export([$name => $var]);
}