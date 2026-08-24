<?php

declare(strict_types=1);

namespace MikoPBX\Core\Asterisk {
    class AsteriskManager
    {
    }
}

namespace Modules\ModuleHttpAlert\Tests\LegacyCore {
    use Modules\ModuleHttpAlert\Lib\CdrEventDecoder;

    require_once dirname(__DIR__) . '/Lib/CdrEventDecoder.php';

    $expected = [
        'action' => 'dial_answer',
        'linkedid' => '1787600000.84',
    ];
    $json = json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $payloads = [
        base64_encode($json),
        'GZ:' . base64_encode(gzencode($json, 9)),
    ];

    foreach ($payloads as $payload) {
        $actual = CdrEventDecoder::decode($payload);
        if ($actual !== $expected) {
            fwrite(
                STDERR,
                'FAIL: legacy Core fallback expected ' . var_export($expected, true)
                    . ', got ' . var_export($actual, true) . PHP_EOL
            );
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: decoder supports plain and gzip payloads without the Core helper.\n");
}
