<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleHttpAlert\Lib\RestAPI\Controllers;

use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;

class ApiController extends ModulesControllerBase
{
    /**
     * Удаление клиентов CRM системы.
     * curl -X GET 'http://127.0.0.1/pbxcore/api/module-http-alert/v1/test?action=test&data=test1'
     * @return void
     */
    public function test():void
    {
        try {
            $q = $this->request->getQuery();
            unset($q['_url']);
            $result = json_encode($q, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $filename = '/storage/usbdisk1/mikopbx/log/ModuleHttpAlert/query.log';
            if(!file_exists($filename)){
                file_put_contents($filename, PHP_EOL);
            }
            file_put_contents($filename, $result.PHP_EOL, FILE_APPEND);
            echo 'num-'.time();
        }catch (\JsonException $e){
            echo 'error data';
        }
        $this->response->sendRaw();
    }

}