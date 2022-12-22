<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use Laminas\Log\Formatter\Simple;
use Laminas\Log\Logger as BaseLogger;
use Laminas\Log\Writer\Stream;

class Logger
{
    /**
     * Logger instance
     *
     * @var \Laminas\Log\Logger
     */
    protected static $logger;

    /**
     * Returns the logger singleton
     *
     * @param integer $priority Priority for filtering
     * @return \Laminas\Log\Logger
     */
    public static function get(int $priority = 4): BaseLogger
    {
        if (self::$logger === null) {
            $writer = new Stream('php://output');
            $formatter = new Simple('%priorityName%: %message%' . PHP_EOL);
            $writer->setFormatter($formatter);

            self::$logger = new BaseLogger();
            self::$logger->addWriter($writer);
        }

        return self::$logger;
    }
}
