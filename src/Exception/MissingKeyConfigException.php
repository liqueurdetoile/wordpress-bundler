<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Exception;

class MissingKeyConfigException extends \Exception
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('[WordpressBundler] - Key %s is missing or null in configuration', $key));
    }
}
