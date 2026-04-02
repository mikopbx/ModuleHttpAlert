<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2022 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleHttpAlert\bin;
require_once 'Globals.php';

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use Modules\ModuleHttpAlert\Lib\Logger;
use Modules\ModuleHttpAlert\Models\ModuleDidUrl;
use Modules\ModuleHttpAlert\Models\ModuleHttpAlert;

class ListenerAMI extends WorkerBase
{
    private int     $extensionLength = 4;
    private array   $users = [];
    private array   $innerNums = [];
    private int     $lastUpdateSettings = 0;

    private string $urlCompleteCdr = '';
    private string $urlCompleteCdrGlobal = '';
    private string $urlDialEnd = '';
    private string $urlAnswer = '';
    private string $urlCreateChan = '';
    private string $urlDial = '';
    private string $urlHangup = '';
    private bool $incomingOnly = true;

    private array  $didData = [];

    private Logger $logger;

    /**
     * Соответстввие Linked ID и сведиений о каналах.
     * @var array
     */
    private array $calls = [];

    /**
     * Счетчик каналов. Считаем каналы при входящем с множественной регистрацией.
     * @var array
     */
    private array $channelCounter = [];

    /**
     * Соответствие канала и номера телефона.
     * @var array
     */
    private array $activeChannels = [];

    /**
     * Handles the received signal.
     *
     * @param int $signal The signal to handle.
     *
     * @return void
     */
    public function signalHandler(int $signal): void
    {
        parent::signalHandler($signal);
        cli_set_process_title('SHUTDOWN_'.cli_get_process_title());
        exit(0);
    }

    /**
     * Старт работы листнера.
     *
     * @param $argv
     */
    public function start($argv):void
    {
        $this->logger = new Logger('ListenerAMI', 'ModuleHttpAlert');
        $this->logger->writeInfo('Starting...');
        $this->am = Util::getAstManager();
        $this->setFilter();

        $this->logger->writeInfo('Init beanstalk...');
        $this->checkUpdateSettings();
        $this->logger->writeInfo('Get settings, update 10 sec...');

        $this->am->addEventHandler("userevent", [$this, "callback"]);
        while ($this->needRestart === false) {
            $result = $this->am->waitUserEvent(true);
            if (!$result) {
                // Нужен реконнект.
                usleep(100000);
                $this->am = Util::getAstManager();
                $this->setFilter();
            }
        }
    }

    /**
     * Обновление настроек сервиса.
     * @return void
     */
    private function checkUpdateSettings():void
    {
        if(time() - $this->lastUpdateSettings <= 10){
            return;
        }

        $this->logger->rotate();
        $this->lastUpdateSettings = time();
        $this->users     = [];
        $this->innerNums = [];

        $tmpUsers = [];

        $settings = ModuleHttpAlert::findFirst();
        if($settings){
            $this->urlCompleteCdrGlobal = $settings->urlCompleteCdrGlobal;
            $this->urlCompleteCdr       = $settings->urlCompleteCdr;
            $this->urlDialEnd           = $settings->urlDialEnd;
            $this->urlAnswer            = $settings->urlAnswer;
            $this->urlCreateChan        = $settings->urlCreateChan;
            $this->urlDial              = $settings->urlDial;
            $this->urlHangup            = $settings->urlHangup;
            $this->incomingOnly         = $settings->incomingOnly === '1';
            unset($settings);
        }

        $this->didData = [];
        $didData = ModuleDidUrl::find();
        foreach ($didData as $didDataRule){
            $this->didData[trim($didDataRule->did)] = trim($didDataRule->url);
        }

        /** @var Extensions $ext */
        $extensions = Extensions::find(['order' => 'type DESC']);
        foreach ($extensions as $ext){
            if($ext->type === Extensions::TYPE_SIP){
                $tmpUsers[$ext->userid] = $ext->number;
                $this->users[$ext->number] = $ext->number;
            }elseif($ext->type === Extensions::TYPE_EXTERNAL && isset($tmpUsers[$ext->userid])){
                $this->users[self::getPhoneIndex($ext->number)] = $tmpUsers[$ext->userid];
            }
            $this->innerNums[] = self::getPhoneIndex($ext->number);
        }
    }

    /**
     * Возвращает усеценный слева номер телефона.
     *
     * @param $number
     *
     * @return bool|string
     */
    public static function getPhoneIndex($number)
    {
        $number = preg_replace('/\D+/', '', $number);
        return substr($number, -10);
    }

    /**
     * Установка фильтра
     *
     */
    private function setFilter():void
    {
        $pingTube = $this->makePingTubeName(self::class);
        $params = ['Operation' => 'Add', 'Filter' => 'UserEvent: '.$pingTube];
        $this->am->sendRequestTimeout('Filter', $params);
        $params = ['Operation' => 'Add', 'Filter' => 'UserEvent: CdrConnector'];
        $this->am->sendRequestTimeout('Filter', $params);
    }

    /**
     * Функция обработки оповещений.
     * @param $parameters
     * @return void
     */
    public function callback($parameters):void
    {

        if ($this->replyOnPingRequest($parameters)){
            return;
        }
        if ('CdrConnector' !== $parameters['UserEvent']) {
            return;
        }
        $this->checkUpdateSettings();
        try {
            $data = json_decode(base64_decode($parameters['AgiData']), true, 512, JSON_THROW_ON_ERROR);
        }catch (\JsonException $e){
            $data['action'] = '';
        }
        switch ($data['action']) {
            case 'hangup_chan':
                $this->actionHangupChan($data);
                break;
            case 'dial_create_chan':
            case 'transfer_dial_create_chan':
                $this->actionDialCreateChan($data);
                break;
            case 'dial_answer':
            case 'transfer_dial_answer':
                $this->actionDialAnswer($data);
                break;
            case 'dial_end':
                $this->actionDialEnd($data);
                break;
            case 'dial':
            case 'transfer_dial':
            case 'sip_transfer':
            case 'answer_pickup_create_cdr':
                $this->actionDial($data);
                break;
            case 'hangup_update_cdr':
                $this->actionCompleteCdr($data);
                break;
            default:
                break;
        }

    }

    /**
     * Обработка оповещения о звонке.
     *
     * @param $data
     */
    private function actionDial($data):void {
        $generalNumber = null;
        if ($data['transfer'] === '1') {
            $history = $this->calls[$data['linkedid']]??[];
            if (!empty($history)) {
                // Определим номер того, кого переадресуют.
                if ($data['src_num'] === $history[0]['src']) {
                    $generalNumber = $history[0]['dst'];
                } else {
                    $generalNumber = $history[0]['src'];
                }
            }
        }
        $tmpCalls = [];
        if(in_array($data['action_extra']??'', ['originate_start', 'originate_end'], true)){
            return;
        }
        $this->createCdrCheckInner($tmpCalls, $data, $generalNumber);
        $this->createCdrCheckOutgoing($tmpCalls, $data, $generalNumber);
        $this->createCdrCheckIncoming($tmpCalls, $data, $generalNumber);
        foreach ($tmpCalls as $call){
            $callFound = false;
            $callsById = $this->calls[$data['linkedid']]??[];
            foreach ($callsById as $oldCall) {
                if($call['uid'] === $oldCall['uid']){
                    $callFound = true;
                    break;
                }
            }
            if(!$callFound){
                $this->calls[$call['id']][$call['uid']] = $call;
            }
            $this->activeChannels[$data['src_chan']] = $data['src_num'];
            if($this->incomingOnly && $call['type'] !== 'incoming'){
                continue;
            }
            if(!empty($call['user'])){
                // http://127.0.0.1/pbxcore/api/module-http-alert/v1/test?date=<date>&id=<id>&uid=<uid>&user=<user>&src=<src>&dst=<dst>&channel=<src-chan>&dst-chan=<dst-chan>&did=<did>&action=<action>
                $this->send($this->urlDial, $call);
            }
        }
    }

    /**
     * Проверка на внутренний вызов.
     * @param $calls
     * @param $data
     * @param $generalNumber
     * @return void
     */
    private function createCdrCheckInner(&$calls, $data, $generalNumber):void
    {
        $this->logger->writeInfo("{$data['src_num']} -> {$data['dst_num']} ({$this->extensionLength})");

        if(strlen($data['src_num']) <= $this->extensionLength
            && strlen($data['dst_num']) <= $this->extensionLength
            && strlen($generalNumber) <= $this->extensionLength){

            $srcUser = $this->users[$data['src_num']]??'';
            $dstUser = $this->users[$data['dst_num']]??'';

            $param = [
                'uid'              => $data['UNIQUEID'],
                'id'               => $data['linkedid'],
                'date'             => date(\DateTimeInterface::ATOM, strtotime($data['start'])),
                'user'             => $srcUser,
                'client'           => $dstUser,
                'src'              => $data['src_num'],
                'dst'              => $data['dst_num'],
                'src-chan'         => $data['src_chan'],
                'dst-chan'         => '',
                'did'              => '',
                'shotDid'          => '',
                'action'           => 'call',
                'type'             => 'inner',
            ];

            $calls[] = $param;
            if($dstUser !== $srcUser){
                $param['user'] = $dstUser;
                $calls[] = $param;
            }
        }
    }

    /**
     * Проверка на входящий вызов.
     * @param $calls
     * @param $data
     * @param $generalNumber
     * @return bool
     */
    private function createCdrCheckIncoming(&$calls, $data, $generalNumber):bool
    {
        $isIncome = false;
        // Это входящий вызов на внутренний номер сотрудника.
        if (strlen(''.$generalNumber) > $this->extensionLength && !in_array($generalNumber, $this->innerNums, true)) {
            // Это переадресация вызова. (консультационная)
            $calls[] = [
                'uid' => $data['UNIQUEID'],
                'id' => $data['linkedid'],
                'date' => date(\DateTimeInterface::ATOM, strtotime($data['start'])),
                'user' => $this->users[$data['dst_num']] ?? '',
                'client' => $data['src_num'],
                'g-src' => $generalNumber,
                'src' => $data['src_num'],
                'dst' => $data['dst_num'],
                'src-chan' => $data['src_chan'],
                'action' => 'call',
                'dst-chan' => '',
                'shotDid'        => '',
                'didid'          => '',
                'type'           => 'incoming',
            ];
            $isIncome = true;
        }elseif(in_array($data['src_num'], $this->innerNums, true) || in_array($generalNumber, $this->innerNums, true)){
            // Это точно не входящий. Как вариант - внутренний.
            unset($data);
        } elseif (strlen($data['src_num']) > $this->extensionLength && !in_array($data['src_num'], $this->innerNums, true)) {
            // Входящий вызов с номера клиента.
            $calls[] = [
                'uid'              => $data['UNIQUEID'],
                'id'               => $data['linkedid'],
                'date'             => date(\DateTimeInterface::ATOM, strtotime($data['start'])),
                'user'             => $this->users[$data['dst_num']]??'',
                'client'           => $data['src_num'],
                'src'              => $data['src_num'],
                'dst'              => $data['dst_num'],
                'action'           => 'call',
                'src-chan'         => $data['src_chan'],
                'dst-chan'         => '',
                'did'              => $data['did'],
                'shotDid'          => self::getPhoneIndex($data['did']),
                'type'             => 'incoming',
            ];
            $isIncome = true;
        }

        return $isIncome;
    }


    /**
     * Проверка на исходящий вызов.
     * @param $calls
     * @param $data
     * @param $generalNumber
     * @return void
     */
    private function createCdrCheckOutgoing(&$calls, $data, $generalNumber):void
    {
        if (in_array($data['src_num'], $this->innerNums, true)
            && strlen($generalNumber) <= $this->extensionLength
            && strlen($data['dst_num']) > $this->extensionLength
            && !in_array($data['dst_num'], $this->innerNums, true))
        {
            // Это исходящий вызов с внутреннего номера.
            $calls[] = [
                'uid'              => $data['UNIQUEID'],
                'id'               => $data['linkedid'],
                'date'             => date(\DateTimeInterface::ATOM, strtotime($data['start'])),
                'user'             => $this->users[$data['src_num']]??'',
                'client'           => $data['dst_num'],
                'src'              => $data['src_num'],
                'dst'              => $data['dst_num'],
                'src-chan'         => $data['src_chan'],
                'dst-chan'         => '',
                'dst-chan-1'         => '1',
                'action'           => 'call',
                'did'              => '',
                'shotDid'          => '',
                'type'             => 'outgoing',
            ];
        }
    }

    /**
     * Завершение телефонного звонка.
     * @param $data
     * @return void
     */
    private function actionHangupChan($data):void
    {
        $channels = [];
        $channel = $data['agi_channel'];
        if(strpos($channel, 'Local/') === 0){
            return;
        }
        $transferCall = [];
        $data['end'] = date(\DateTimeInterface::ATOM, strtotime($data['end']));
        if(isset($this->calls[$data['linkedid']])){
            foreach ($this->calls[$data['linkedid']] as &$call) {
                if(isset($call['end'])){
                    continue;
                }
                if($channel !== $call['src-chan'] && $channel !== $call['dst-chan']){
                    continue;
                }
                $call['end'] = $data['end'];
                $channels[] = [$call['src-chan'], $call['uid']];
                $channels[] = [$call['dst-chan'], $call['uid']];
                if(isset($call['answer'])){
                    $this->cloneCdr($data, $transferCall, $call);
                }
            }
            unset($call, $transferCall);
        }
        // Считаем каналы с одинаковым UID
        $countChannel = $this->channelCounter[$data['UNIQUEID']]??0;
        $countChannel--;
        if($countChannel>0){
            $this->channelCounter[$data['UNIQUEID']] = $countChannel;
            // Не все каналы с этим ID были завершены.Вероятно это множественная регистрация.
            return;
        }
        foreach ($channels as $channelData){
            [$channel, $uid] = $channelData;
            if(!isset($this->activeChannels[$channel])){
                continue;
            }
            $phone  = $this->activeChannels[$channel];
            $userId = $this->users[$phone]??null;

            if (!$userId && count($this->calls[$data['linkedid']]) === 1 && empty($this->calls[$data['linkedid']][0]['dst-chan'])){
                $userId = '';
            }elseif(empty($userId)){
                continue;
            }
            $call = $this->calls[ $data['linkedid']??''][$data['UNIQUEID']??'']??[];
            $params = [
                'id'      => $data['linkedid'],
                'date'    => $data['end'],
                'uid'     => $uid,
                'user'    => $userId,
                'did'      => $call['did']??'',
                'action'  => 'hangup'
            ];
            if($this->incomingOnly && $call['type'] !== 'incoming'){
                continue;
            }

            if(!empty($userId)){
                // http://127.0.0.1/pbxcore/api/module-http-alert/v1/test?date=<date>&id=<id>&uid=<uid>&did=<did>&user=<user>&action=<action>
                $this->send($this->urlHangup, $params);
            }
        }
    }

    /**
     *
     * @param $data - Данные события завершения вызова.
     * @param $transferCall - Вызов, который переводят.
     * @param $call - Звонок / консультативная переадресация.
     * @return void
     */
    private function cloneCdr($data, &$transferCall, $call):void
    {
        $endTime = date(\DateTimeInterface::ATOM, strtotime($data['end']));
        if(empty($transferCall)){
            $transferCall = $call;
            $transferCall['date']   = $endTime;
            $transferCall['answer'] = $endTime;
            return;
        }
        // Канал того, кто переадресует.
        $channel = $data['agi_channel'];
        if($transferCall['src-chan'] !== $channel){
            $transferCall['dst-chan'] = $call['dst-chan'];
            $transferCall['dst']      = $call['dst'];
        }else{
            $transferCall['src-chan'] = $call['dst-chan'];
            $transferCall['src']      = $call['dst'];
        }
        $transferCall['uid'] = md5($call['uid']. $transferCall['uid']);
        unset($transferCall['end']);
        if(!isset($this->users[$call['dst']])){
            return;
        }
        $this->calls[$data['linkedid']][$transferCall['uid']]= $transferCall;
        $this->activeChannels[$transferCall['src-chan']] = $transferCall['src'];
        $this->activeChannels[$transferCall['dst-chan']] = $transferCall['dst'];

        if($this->incomingOnly && $transferCall['type'] !== 'incoming'){
            return;
        }
        $params = [
            'id'      => $data['linkedid'],
            'date'    => $endTime,
            'uid'     => $transferCall['uid'],
            'user'    => $this->users[$call['dst']],
            'src'     => $transferCall['src'],
            'dst'     => $transferCall['dst'],
            'did'     => $transferCall['did'],
            'action'  => 'call'
        ];
        $this->send($this->urlDial, $params);

        $data = [
            'action'   => 'answer',
            'date'     => $endTime,
            'id'       => $data['linkedid'],
            'user'     => $this->users[$call['dst']],
            'shotUser' => self::getPhoneIndex($this->users[$call['dst']]),
            'uid'      => $transferCall['uid'],
            'did'      => $transferCall['did'],
        ];
        $this->send($this->urlAnswer, $data);
    }

    /**
     * Создание нового канала (dst_chan).
     * @param $data
     * @return void
     */
    private function actionDialCreateChan($data):void{
        $uid = $data['transfer_UNIQUEID']??$data['UNIQUEID'];
        foreach ($this->calls[$data['linkedid']] as &$call){
            if($uid !== $call['uid']){
                continue;
            }
            $call['dst-chan'] = $data['dst_chan'];
            break;
        }
        unset($call);

        // Считаем каналы с одинаковым UID
        $countChannel = $this->channelCounter[$uid]??0;
        $countChannel++;
        $this->channelCounter[$uid] = $countChannel;

        $chan   = str_replace('/','-', $data['dst_chan']);
        $number = explode('-', $chan)[1]??'';
        if(!isset($this->users[$number])){
            // нет такого пользователя.
            return;
        }
        $this->activeChannels[$data['dst_chan']] = $number;

        $eventTime = isset($data['event_time'])?strtotime($data['event_time']):time();
        $call = $this->calls[ $data['linkedid']][$data[$uid]??'']??[];
        $params = [
            'id'      => $data['linkedid'],
            'date'    => date(\DateTimeInterface::ATOM, $eventTime),
            'uid'     => $uid,
            'user'    => $this->users[$number],
            'did'     => $this->calls[ $data['linkedid']][$uid]['did']??'',
            'action'  => 'create-chan'
        ];
        $typeCall = $call['type']??'';
        if($this->incomingOnly && $typeCall !== 'incoming'){
            return;
        }
        if(!empty($params['user'])){
            // http://127.0.0.1/pbxcore/api/module-http-alert/v1/test?date=<date>&id=<id>&uid=<uid>&did=<did>&user=<user>&action=<action>
            $this->send($this->urlCreateChan, $params);
        }
    }

    /**
     * Скрываем карточку звонка для всех агентов, кто пропустил вызов.
     * @param $params
     */
    private function actionDialAnswer($params):void
    {
        $channel = $params['agi_channel'];
        foreach ($this->calls[$params['linkedid']] as &$call){
            if(isset($call['answer'])){
                continue;
            }
            if($channel !== $call['src-chan'] && $channel !== $call['dst-chan']){
                continue;
            }
            $call['answer'] = date(\DateTimeInterface::ATOM, strtotime($params['answer']));

            $callOrigin = $this->calls[$call['id']][$call['uid']];

            $data = [
                'action'   => 'answer',
                'date'     => $call['answer'],
                'id'       => $params['linkedid'],
                'uid'      => $call['uid'],
                'user'     => $callOrigin['user']??'',
                'shotUser' => self::getPhoneIndex($callOrigin['user']??''),
                'did'      => $callOrigin['did']??'',
                'src-chan' => $callOrigin['src-chan']??'',
                'src'      => $callOrigin['src']??'',
                'dst-chan' => $callOrigin['dst-chan']??'',
                'dst'      => $callOrigin['dst']??'',
            ];

            if($this->incomingOnly && $callOrigin['type'] !== 'incoming'){
                continue;
            }
            // http://127.0.0.1/pbxcore/api/module-http-alert/v1/test?date=<date>&id=<id>&uid=<uid>&user=<user>&src=<src>&dst=<dst>&channel=<src-chan>&dstchannel=<dst-chan>&did=<did>&action=<action>
            $this->send($this->urlAnswer, $data);
        }
    }

    /**
     * Завершение попытки звонка на внутренний номер. Контакт не определн. Dial не был вызван.
     * @param $data
     * @return void
     */
    private function actionDialEnd($data):void
    {
        if(stripos($data['src_chan'], 'local') === 0){
            // Dial был не удачен и канал не создан тк нет контактов.
            $num = $this->calls[$data['linkedid']][$data['UNIQUEID']]['dst']??'';
        }else{
            $num = $this->activeChannels[$data['src_chan']];
        }
        if (isset($this->users[$num])) {
            // Это исходящий вызов.
            $USER_ID = $this->users[$num];
        } else {
            return;
        }
        $callOrigin = $this->calls[$data['linkedid']][$data['UNIQUEID']];
        $call = [
            'uid'              => $data['UNIQUEID'],
            'id'               => $data['linkedid'],
            'date'             => date(\DateTimeInterface::ATOM, strtotime($data['start'])),
            'user'             => $USER_ID,
            'src'              => $num,
            'dst'              => '', // Канал назначения не был создан.
            'did'              => $callOrigin['did']??'',
            'action'           => 'end-dial',
        ];

        if($this->incomingOnly && $callOrigin['type'] !== 'incoming'){
            return;
        }
        if(!empty($USER_ID)){
            // http://127.0.0.1/pbxcore/api/module-http-alert/v1/test?date=<date>&id=<id>&uid=<uid>&src=<src>&did=<did>&user=<user>&action=<action>
            $this->send($this->urlDialEnd, $call);
        }
    }

    /**
     * Обработка события завершения телефонного звонка.
     *
     * @param $data
     */
    private function actionCompleteCdr($data):void
    {
        // Это событие приходит только когда все cdr обработаны.
        if (isset($this->users[$data['src_num']])) {
            // Это исходящий вызов.
            $USER_ID = $this->users[$data['src_num']];
        } elseif (isset($this->users[$data['dst_num']])) {
            // Это входящие вызов.
            $USER_ID = $this->users[$data['dst_num']];
        } else {
            return;
        }
        $uid     = $data['UNIQUEID'];
        $endTime = date(\DateTimeInterface::ATOM, strtotime($data['endtime']));
        $start   = date(\DateTimeInterface::ATOM, strtotime($data['start']));
        $ended = false;
        $did = '';
        $type = '';
        foreach ( $this->calls[$data['linkedid']]??[] as $index => $callData){
            if(empty($did)){
                $did = $callData['did'];
            }
            if($callData['src'] === $data['src_num'] && $callData['dst'] === $data['dst_num']
               && $callData['date'] === $start && $callData['end'] === $endTime){
                $uid = $callData['uid'];
                $ended = $callData['complite']??false;
                $type = $callData['type']??'';
                unset($this->calls[$data['linkedid']][$index]);
                continue;
            }
            $this->calls[$data['linkedid']][$index]['complite'] = true;
        }

        $call = [
            'did'              => $did,
            'uid'              => $uid,
            'id'               => $data['linkedid'],
            'date'             => date(\DateTimeInterface::ATOM, strtotime($data['start'])),
            'user'             => $USER_ID,
            'src'              => $data['src_num'],
            'dst'              => $data['dst_num'],
            'g-missed'         => $data['GLOBAL_STATUS'] !== 'ANSWERED',
            'missed'           => $data['disposition'] !== 'ANSWERED',
            'filename'         => $data['recordingfile'],
            'action'           => 'end-call',
        ];

        if( !($this->incomingOnly && $type !== 'incoming') ){
            $this->send($this->urlCompleteCdr, $call);
            if($ended === false){
                $call['action'] = 'end-call-global';
                $this->send($this->urlCompleteCdrGlobal, $call);
            }
        }
        // Чистим мусор.
        foreach ([$data['src_chan']??'', $data['dst_chan']??'', $data['UNIQUEID']??''] as $value){
            if(isset($this->activeChannels[$value])){
                unset($this->activeChannels[$value]);
            }
        }
        if(empty($this->calls[$data['linkedid']])){
            unset($this->calls[$data['linkedid']]);
        }
    }

    /**
     * Выполнение методов worker, запущенного в другом процессе.
     * @param string $url
     * @param array $params
     */
    private function send(string $url, array $params = []):void
    {
        $did = $params['did'] ?? '';
        $baseUrl = trim($this->didData[$did] ?? $this->didData[''] ?? '');
        
        if (!empty($baseUrl) && !empty($url)) {
            $this->logger->writeInfo(["SEND: $did ", $url, $params]);
            
            try {
                // Вызов без возврата результата (fire-and-forget)
                WorkerHTTP::invoke('httpGet', [$baseUrl . '?' . $url, $params], false);
            } catch (\Throwable $e) {
                // Логирование ошибки отправки
                $this->logger->writeError("Failed to send HTTP request: " . $e->getMessage());
            }
        } else {
            $this->logger->writeInfo(["SKIP: $did ", $url, $params]);
        }
    }
}

ListenerAMI::startWorker($argv??[]);