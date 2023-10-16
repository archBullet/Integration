<?php


$json = '{"ActivityType":"extended","Description":"vbmcmc","Subject":" Тест, AFI TOWER","LeadId":"099eefeb-8457-ee11-8100-001dd8bb025e","attributes":{}}';

$result = changePartnerLeadStatus($json);
debug($result);

function changePartnerLeadStatus($postFields)
{
    $ch = curl_init();

    $headers = [
        'Content-type: application/json',
        "Authorization:bearer 'neOfqTBxAzaC3FZwAdZQ0Q1FZ3K61VJK9q6ZCoqofgb4g92zlu32vC3LhRUdfoFIarR79LCu47LTEsMPiqOaP9pKYZuWh5BMUQnO7Jeg1-67XIXUdCCKHyMqXc2CAGu9Ca58qHRl79ntlUMMg4_9lmLZyI5weFCCs6iMiDo2-VW0yGxeFOQ4R6IoUiLFYkl4kqey-jG0WDnVysIQcDfsaF93xbgm6T3h2Oc4IoUEYatBXwTgImZEopK18g9pFts1VEsIJTEW-Vjx5JrKClaoq52DGp4aj-dY_vTqjC4s5ZsgNZIJRtgHnxq0UUx1ycQTbW9xiYNhaP4OZmSFzGksZv2o0YfB5J4oZK6jnPzls3B3xMLp1jlR60dDXsNyveCO580K-t4BaXpzZJMI4lxSAMNseMKPEE5mqyMPO6qqqw1Bf-OHk7LivpfOcpMdhKAHJ1KDLUhyvWJI7QMUGRUcYS_t9g6MhsrGvdltG9C8uN5Yvatbimvi5eHpjRABWrL-Ssm32aZ67VCH5NvEoW-KN1gwthEJRW9UihTpW2Bbee4'"
    ];
    // $token = 'aOW0TEbZRmd+tgPPkHYDjrfbHC1a3iZyGxfyALW+2TPGh3aUOCl7H5RqwooKOrFURQieqNKt4VzLyXwtp4X5OK8fqC0/oArDl4xOcNj/sEfjoQjE/y64MOK7ti3SgbBq';
    curl_setopt($ch, CURLOPT_URL, "https://new.partnerka.app/api//activity");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
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


function debug($var, $name = 0)
{
    if ($var) {
        foreach ($GLOBALS as $varName => $value) {
            if ($value === $var) {
                $name = $varName;
                break;
            }
        }
    }
    var_export([$name => $var]);
}