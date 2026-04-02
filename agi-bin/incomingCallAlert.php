#!/usr/bin/php -q
<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 5 2018
 *
 * Работа с историей звонков / записями разговоров.
 */

use MikoPBX\Core\Asterisk\AGI;
use MikoPBX\Core\System\Util;
use Modules\ModuleHttpAlert\Models\ModuleDidUrl;
use Modules\ModuleHttpAlert\Models\ModuleHttpAlert;
use Modules\ModuleHttpAlert\bin\WorkerHTTP;
use Modules\ModuleHttpAlert\Lib\Logger;

require_once 'Globals.php';

function getPhoneIndex($number)
{
    $number = preg_replace('/\D+/', '', $number);
    return substr($number, -10);
}

$agi = new AGI();
$agi->exec('Ringing', '');
$agi->set_variable('AGIEXITONHANGUP', 'yes');
$agi->set_variable('AGISIGHUP', 'yes');
$agi->set_variable('__ENDCALLONANSWER', 'yes');

$params = [
    'number'     =>  $agi->request['agi_callerid'],
    'did'        => $agi->get_variable('FROM_DID', true),
    'shotNumber' => getPhoneIndex($agi->request['agi_callerid']),
    'shotDid'    => getPhoneIndex($agi->get_variable('FROM_DID', true)),
    'id'         => $agi->get_variable('CHANNEL(linkedid)', true)
];

$baseUrl = '';
$baseData = ModuleDidUrl::findFirst("did='{$params['did']}'");
if (!$baseData) {
    $baseData = ModuleDidUrl::findFirst("did=''");
}
if ($baseData) {
    $baseUrl = $baseData->url;
}

$resultUrl = '';
$settings = ModuleHttpAlert::findFirst();
if ($settings && !empty($baseUrl)) {
    $agi->verbose($baseUrl . '?' . $settings->urlStartCall);
    
    try {
        $result = WorkerHTTP::invoke('httpGet', [$baseUrl . '?' . $settings->urlStartCall, $params]);
        
        // Проверка результата
        if (!($result instanceof \MikoPBX\PBXCoreREST\Lib\PBXApiResult)) {
            throw new \Exception('Invalid response type from WorkerHTTP::invoke');
        }
        
        if (!$result->success) {
            $errorMessage = $result->messages[0]['message'] ?? 'Unknown error';
            throw new \Exception("HTTP request failed: {$errorMessage}");
        }
        
        [$code, $cidName] = $result->data;
        
        if ($result->success) {
            $cidName = preg_replace('/[^a-zA-Z0-9]/', '', $cidName);
            if (!empty($cidName)) {
                $agi->set_variable('CALLERID(name)', substr($cidName, 0, 40));
            }
        } else {
            $agi->verbose("HTTP error code: {$code}");
        }
    } catch (\Throwable $e) {
        // Логирование ошибки в системный лог
        Util::sysLogMsg('ModuleHttpAlert-AGI', 'HTTP request error: ' . $e->getMessage());
        $agi->verbose("Error: " . $e->getMessage());
    }
}
