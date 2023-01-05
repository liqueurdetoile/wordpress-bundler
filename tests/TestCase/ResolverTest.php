<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\TestCase;

use Lqdt\WordpressBundler\Resolver;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function testGetRootPath(): void
    {
        $this->assertEquals(Resolver::normalize(dirname(dirname(__DIR__))), Resolver::getRootPath());
        $this->assertEquals(Resolver::normalize(__DIR__), Resolver::getRootPath(__DIR__));
    }

    public function makeAbsoluteData(): array
    {
        $root = Resolver::getRootPath();

        return [
            [__FILE__, Resolver::normalize(__FILE__)],
            ['src', $root . '/src'],
            ['src/../src', $root . '/src'],
        ];
    }


    /**
     * @dataProvider makeAbsoluteData
     */
    public function testMakeAbsolute(string $input, string $expected): void
    {
        $this->assertEquals($expected, Resolver::makeAbsolute($input));
    }

    public function resolveData(): array
    {
        return [
            ['seemsLikeGlob**/*', []],
            [__FILE__, [
                Resolver::makeRelative(__FILE__) => Resolver::normalize(__FILE__),
              ],
            ],
            ['tests/fixtures/resolver/*.*', [
                'tests/fixtures/resolver/main.js' => Resolver::makeAbsolute('tests/fixtures/resolver/main.js'),
                'tests/fixtures/resolver/main.php' => Resolver::makeAbsolute('tests/fixtures/resolver/main.php'),
              ],
            ],
            ['tests/fixtures/resolver/*', [
                'tests/fixtures/resolver/main.js' => Resolver::makeAbsolute('tests/fixtures/resolver/main.js'),
                'tests/fixtures/resolver/main.php' => Resolver::makeAbsolute('tests/fixtures/resolver/main.php'),
                'tests/fixtures/resolver/sub' => Resolver::makeAbsolute('tests/fixtures/resolver/sub'),
              ],
            ],
        ];
    }

    /**
     * @param string      $in       Path
     * @param array|false $expected Output
     * @dataProvider resolveData
     */
    public function testResolve(string $in, $expected): void
    {
        $this->assertSame($expected, Resolver::resolve($in));
    }

    public function testIsSubPath(): void
    {
        $this->assertTrue(Resolver::isSubPath('/tests/file.txt', '/tests'));
    }
}
