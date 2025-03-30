<?php

namespace ProntoPaga;

class ProntoPagaLogger
{
    const LOG_DIR = __DIR__ . '/../logs';
    const LOG_FILE = self::LOG_DIR . '/prontopaga.log';

    public static function info(string $message, array $context = [])
    {
        if (!ProntoPagaConfig::DEBUG) {
            return;
        }
        self::writeLog('INFO', $message, $context);
    }

    public static function error(string $message, array $context = [])
    {
        self::writeLog('ERROR', $message, $context);
    }

    protected static function writeLog(string $level, string $message, array $context = [])
    {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] [$level] $message";

        if (!empty($context)) {
            $entry .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $entry .= PHP_EOL;

        file_put_contents(self::LOG_FILE, $entry, FILE_APPEND);
    }
}
