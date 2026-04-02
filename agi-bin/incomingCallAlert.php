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
$baseData = ModuleDidUrl::findFirst(['conditions' => 'did = :did:', 'bind' => ['did' => $params['did']]]);
if (!$baseData) {
    $baseData = ModuleDidUrl::findFirst(['conditions' => 'did = :did:', 'bind' => ['did' => '']]);;
}
if ($baseData) {
    $baseUrl = $baseData->url;
}

$resultUrl = '';
$settings = ModuleHttpAlert::findFirst();
if ($settings && !empty($baseUrl)) {
    $agi->verbose($baseUrl . '?' . $settings->urlStartCall);
    
    $result = WorkerHTTP::invoke('httpGet', [$baseUrl . '?' . $settings->urlStartCall, $params]);
    if (!$result->success) {
        $errorMessage = $result->messages[0]['message'] ?? 'Unknown error';
        Util::sysLogMsg('ModuleHttpAlert-AGI', "HTTP request failed: {$errorMessage}");
        $agi->verbose("HTTP error: {$errorMessage}");
    } else {
        [$code, $cidName] = $result->data;
        $cidName = preg_replace('/[^a-zA-Z0-9\s]/', '', $cidName);
        if (!empty($cidName)) {
            $agi->set_variable('CALLERID(name)', substr($cidName, 0, 40));
        }
    }
}
