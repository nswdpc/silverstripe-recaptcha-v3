<?php

namespace NSWDPC\SpamProtection;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Simple log handling
 */
class Logger
{
    public static function log(string $message, string $level = "DEBUG"): void
    {
        Injector::inst()->get(LoggerInterface::class)->log($level, $message);
    }
}
