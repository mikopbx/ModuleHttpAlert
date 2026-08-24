<?php

declare(strict_types=1);

namespace Modules\ModuleHttpAlert\Lib;

use MikoPBX\Core\Asterisk\AsteriskManager;
use Throwable;

final class CdrEventDecoder
{
    public static function decode(string $encoded): ?array
    {
        if ($encoded === '') {
            return null;
        }

        try {
            if (method_exists(AsteriskManager::class, 'decodeCdrData')) {
                $json = AsteriskManager::decodeCdrData($encoded);
            } elseif (strpos($encoded, 'GZ:') === 0) {
                $compressed = base64_decode(substr($encoded, 3), true);
                $json = is_string($compressed) ? gzdecode($compressed) : false;
            } else {
                $json = base64_decode($encoded, true);
            }
            if (!is_string($json) || $json === '') {
                return null;
            }
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
