<?php
declare(strict_types=1);

namespace Lqdt\WordpressToolkit;

class Addon
{
    /**
     * Path resolver
     *
     * @var \Lqdt\WordpressToolkit\Resolver
     */
    protected $_resolver;

    /**
     * Construct addon
     *
     * @param string $path Root path of the addon
     */
    public function __construct(string $path)
    {
        $this->_resolver = new Resolver($path);
    }

    /**
     * Returns the path resolver instance
     *
     * @return \Lqdt\WordpressToolkit\Resolver Path resolver
     */
    public function getResolver(): Resolver
    {
        return $this->_resolver;
    }
}
