<?php
declare(strict_types=1);

namespace Lqdt\Nawak;

use Adbar\Dot;

// @codingStandardsIgnoreStart
class Test
{
    /**
     * Prop test
     *
     * @var \Adbar\Dot
     */
    private $_dot;

    /**
     * Dummy constructor
     */
    public function __construct()
    {
        // Should be mapped
        $this->_dot = new Dot();

        // Should be mapped
        $tmp = \NS\tmp();

        // Should not be mapped
        $this->_link = get_permalink(10);
    }
}
// @codingStandardsIgnoreEnd
