<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\TestCase;

use Lqdt\WordpressBundler\Resolver;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function testGetBasePath(): void
    {
        $this->assertEquals(Resolver::normalize(dirname(dirname(__DIR__))), Resolver::getBasePath());
        $this->assertEquals(Resolver::normalize(__DIR__), Resolver::getBasePath(__DIR__));
    }

    public function resolveData(): array
    {
        return [
            ['seemsLikeGlob**/*', []],
            [__FILE__, [
                Resolver::makeRelative(__FILE__) => Resolver::normalize(__FILE__),
              ],
            ],
            [__DIR__, [
                Resolver::makeRelative(__DIR__) => Resolver::normalize(__DIR__),
              ],
            ],
            ['src', [
                'src' => Resolver::makeAbsolute('src'),
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
}
