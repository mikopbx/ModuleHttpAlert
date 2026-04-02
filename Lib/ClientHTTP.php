<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
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

namespace Modules\ModuleHttpAlert\Lib;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Throwable;

class ClientHTTP
{
    /**
     * Отправка http GET запроса.
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return PBXApiResult
     */
    public static function sendHttpGetRequest(string $url, array $params, array $headers=[]):PBXApiResult{
        if(!empty($params)){
            $url .= "?".http_build_query($params);
        }
        $client  = new GuzzleHttp\Client();
        $options = [
            'timeout'       => 5,
            'http_errors'   => false,
            'headers'       => $headers,
            'verify'        => false,
        ];
        $message = '';
        $resultHttp = null;
        try {
            $resultHttp = $client->request('GET', $url, $options);
            $code       = $resultHttp->getStatusCode();
            Util::sysLogMsg('ModuleHttpAlert', "Send GET ($code): ".$url.http_build_query($params));
        }catch (GuzzleHttp\Exception\ConnectException $e ){
            $message = $e->getMessage();
            Util::sysLogMsg('ModuleHttpAlert', "ConnectException: ".$e->getMessage());
            $code = 0;
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            Util::sysLogMsg('ModuleHttpAlert', 'GuzzleException: '.$e->getMessage());
            $code = 0;
        }
        return self::parseResponse($resultHttp, $message, $code);
    }

    /**
     * Разбор ответа сервера.
     * @param $resultHttp
     * @param $message
     * @param $code
     * @return PBXApiResult
     */
    private static function parseResponse($resultHttp, $message, $code):PBXApiResult
    {
        $res = new PBXApiResult();
        if( isset($resultHttp) && $code === 200){
            $content = mb_substr($resultHttp->getBody()->getContents(), 0, 300);
            $res->data    = [$code, $content, ''];
            $res->success = is_array($res->data);
        }else{
            $res->data    = [$code,'',$message];
        }
        return $res;
    }
}