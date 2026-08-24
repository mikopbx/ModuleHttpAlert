<?php

declare(strict_types=1);

namespace MikoPBX\Core\Asterisk {
    class AsteriskManager
    {
        public static function decodeCdrData(string $encoded): string
        {
            if (str_starts_with($encoded, 'GZ:')) {
                $compressed = base64_decode(substr($encoded, 3), true);
                return is_string($compressed) ? (string)gzdecode($compressed) : '';
            }

            $decoded = base64_decode($encoded, true);
            return is_string($decoded) ? $decoded : '';
        }
    }
}

namespace Modules\ModuleHttpAlert\Tests {
    use Modules\ModuleHttpAlert\Lib\CdrEventDecoder;

    $decoderPath = dirname(__DIR__) . '/Lib/CdrEventDecoder.php';
    if (!is_file($decoderPath)) {
        fwrite(STDERR, "FAIL: CdrEventDecoder is not implemented.\n");
        exit(1);
    }
    require_once $decoderPath;

    function assertDecoderSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
            );
        }
    }

    function cdrPayload(array $data, bool $gzip = false): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return $gzip ? 'GZ:' . base64_encode(gzencode($json, 9)) : base64_encode($json);
    }

    function testPlainAndGzipPayloadsDecodeToTheSameEvent(): void
    {
        $expected = [
            'action' => 'hangup_update_cdr',
            'linkedid' => '1787600000.42',
            'src_num' => '101',
            'dst_num' => '74951234567',
        ];

        assertDecoderSame($expected, CdrEventDecoder::decode(cdrPayload($expected)), 'plain payload');
        assertDecoderSame($expected, CdrEventDecoder::decode(cdrPayload($expected, true)), 'gzip payload');
    }

    function testInvalidPayloadIsRejected(): void
    {
        assertDecoderSame(null, CdrEventDecoder::decode('not-valid-base64'), 'invalid payload');
        assertDecoderSame(null, CdrEventDecoder::decode(''), 'empty payload');
    }

    $tests = [
        'testPlainAndGzipPayloadsDecodeToTheSameEvent',
        'testInvalidPayloadIsRejected',
    ];
    foreach ($tests as $test) {
        $qualified = __NAMESPACE__ . '\\' . $test;
        $qualified();
        echo "PASS {$test}\n";
    }
}
