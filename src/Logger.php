<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use Laminas\Log\Filter\Priority;
use Laminas\Log\Formatter\Simple;
use Laminas\Log\Logger as BaseLogger;
use Laminas\Log\Writer\Stream;

class Logger
{
    /**
     * Returns a logger singleton with defined priority
     *
     * @param int $priority Priority for filtering
     * @return \Laminas\Log\Logger
     */
    public static function get(int $priority = 4): BaseLogger
    {
        $writer = new Stream('php://output');
        $formatter = new Simple('%message%');
        $filter = new Priority($priority);
        $writer->setFormatter($formatter);
        $writer->addFilter($filter);
        $logger = new BaseLogger();
        $logger->addWriter($writer);
        return $logger;
    }
}
