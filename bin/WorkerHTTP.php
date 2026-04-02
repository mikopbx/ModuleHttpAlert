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

use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleHttpAlert\Lib\ClientHTTP;
use Modules\ModuleHttpAlert\Lib\Logger;

class WorkerHTTP extends WorkerBase
{
    /**
     * Максимальное количество запросов в секунду
     */
    private const MAX_REQUESTS_PER_SECOND = 7;

    /**
     * Белый список разрешённых методов для вызова через Beanstalk
     */
    private const ALLOWED_METHODS = ['httpGet'];

    public int $countReq = 0;
    public float $counterStartTime = 0;
    private Logger $logger;

    /**
     * Handles the received signal.
     *
     * @param int $signal The signal to handle.
     *
     * @return void
     */
    public function signalHandler(int $signal): void
    {
        if (isset($this->logger)) {
            $this->logger->writeInfo("Received signal {$signal}, shutting down...");
        }
        parent::signalHandler($signal);
        cli_set_process_title('SHUTDOWN_' . cli_get_process_title());
    }

    /**
     * Старт работы листнера.
     *
     * @param array $argv
     */
    public function start(array $argv): void
    {
        $this->logger = new Logger('WorkerHTTP', 'ModuleHttpAlert');
        $this->logger->writeInfo('Starting HTTP Worker...');

        $beanstalk = new BeanstalkClient(self::class);
        $beanstalk->subscribe(self::class, [$this, 'onEvents']);
        $beanstalk->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);
        while ($this->needRestart === false) {
            $beanstalk->wait();
        }
    }

    /**
     * Получение запросов на идентификацию номера телефона.
     * @param $tube
     * @return void
     */
    public function onEvents($tube): void
    {
        try {
            $data = json_decode($tube->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->writeError("JSON decode error: " . $e->getMessage());
            return;
        }

        // Проверка структуры данных
        if (!is_array($data) || !isset($data['function'])) {
            $this->logger->writeError("Invalid request structure");
            return;
        }

        $funcName = $data['function'];

        // Проверка белого списка методов
        if (!in_array($funcName, self::ALLOWED_METHODS, true)) {
            $this->logger->writeError("Method not allowed: {$funcName}");
            return;
        }

        // Проверка аргументов
        $args = $data['args'] ?? [];
        if (!is_array($args)) {
            $this->logger->writeError("Args must be an array");
            return;
        }

        $res_data = '';
        try {
            $this->needSleep();
            $res_data = $this->$funcName(...$args);
            $res_data = serialize($res_data);
        } catch (\Throwable $e) {
            $this->logger->writeError("Method execution error [{$funcName}]: " . $e->getMessage());
            $res_data = serialize(null);
        }

        $tube->reply($res_data);
    }

    /**
     * Отправка HTTP запроса на внешний сервер.
     * @param mixed $url
     * @param mixed $params
     * @return PBXApiResult
     */
    public function httpGet($url, $params): PBXApiResult
    {
        // Валидация URL
        if (empty($url) || !is_string($url)) {
            $this->logger->writeError("Empty or invalid URL");
            return new PBXApiResult();
        }

        // Валидация params
        if (!is_array($params)) {
            $this->logger->writeError("Params must be an array");
            return new PBXApiResult();
        }

        // Замена плейсхолдеров с валидацией
        foreach ($params as $key => $value) {
            // Пропускаем некорректные ключи
            if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
                $this->logger->writeWarning("Invalid param key: {$key}");
                continue;
            }

            // Пропускаем не скалярные значения
            if (!is_scalar($value)) {
                $this->logger->writeWarning("Invalid param value for key: {$key}");
                continue;
            }

            $url = str_replace("<{$key}>", rawurlencode((string) $value), $url);
        }

        // Валидация финального URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->logger->writeError("Invalid final URL: {$url}");
            return new PBXApiResult();
        }
        return ClientHTTP::sendHttpGetRequest($url, []);
    }

    /**
     * Выполнение методов worker, запущенного в другом процессе.
     * @param string $function
     * @param array $args
     * @param bool $retVal
     * @return array|bool|mixed
     */
    public static function invoke(string $function, array $args = [], bool $retVal = true)
    {
        $req = [
            'action'   => 'invoke',
            'function' => $function,
            'args'     => $args
        ];
        $client = new BeanstalkClient(self::class);
        try {
            if ($retVal) {
                $req['need-ret'] = true;
                $result = $client->request(json_encode($req, JSON_THROW_ON_ERROR), 5);

                // Безопасная десериализация с проверкой
                if ($result === false || $result === '') {
                    throw new \Exception('Empty response from worker');
                }

                $object = @unserialize($result, ['allowed_classes' => [PBXApiResult::class]]);
                if ($object === false && $result !== 'b:0;') {
                    throw new \Exception('Unserialize failed');
                }

                // Проверка что получен корректный объект
                if (!($object instanceof PBXApiResult)) {
                    throw new \Exception('Invalid response type: ' . gettype($object));
                }

                return $object;
            } else {
                $client->publish(json_encode($req, JSON_THROW_ON_ERROR));
                return true;
            }
        } catch (\Throwable $e) {
            // Создаём объект с ошибкой для возврата
            $errorResult = new PBXApiResult();
            $errorResult->success = false;
            $errorResult->messages[] = [
                'level' => 'error',
                'message' => 'Worker invoke failed: ' . $e->getMessage()
            ];
            return $errorResult;
        }
    }

    /**
     * Ограничение: максимум 7 запросов в секунду. Принудительное ожидание.
     * @return void
     */
    public function needSleep(): void
    {
        $nowTime = microtime(true);
        $deltaTime = $nowTime - $this->counterStartTime;

        // Сброс счётчика если прошла более 1 секунды
        if ($deltaTime >= 1.0) {
            $this->countReq = 0;
            $this->counterStartTime = $nowTime;
            $deltaTime = 0;
        }

        $this->countReq++;

        // Если превысили лимит — ждём до конца секундного окна
        if ($this->countReq > self::MAX_REQUESTS_PER_SECOND) {
            $sleepTime = (1.0 - $deltaTime) * 1000000;
            if ($sleepTime > 0) {
                usleep((int) $sleepTime);
            }
            // Сброс после ожидания
            $this->countReq = 0;
            $this->counterStartTime = microtime(true);
        }
    }
}

if (isset($argv) && count($argv) !== 1) {
    WorkerHTTP::startWorker($argv ?? []);
}
