<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Exception;

class InvalidRootPathResolverException extends \Exception
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('[WordpressBundler] - Provided root path is not valid : %s', $path));
    }
}
