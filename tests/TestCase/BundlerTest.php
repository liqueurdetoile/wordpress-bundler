<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\Testcase;

use Lqdt\WordpressBundler\Bundler;
use Lqdt\WordpressBundler\Resolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class BundlerTest extends TestCase
{
    public function remove(string $path): void
    {
        $fs = new Filesystem();

        $fs->remove($path);
    }

    public function testBundlerInit(): void
    {
        $bundler = new Bundler(['rootpath' => 'tests/fixtures/noconfig', 'loglevel' => 3]);
        $dir = $bundler->getBasePath();

        $this->assertTrue(file_exists($dir . '/plugin.php'));
        $this->assertEquals(3, $bundler->getConfig()->get('loglevel'));

        $bundler->getConfig()->set('loglevel', 8);

        $this->assertEquals(8, $bundler->getConfig()->get('loglevel'));
    }

    public function testGetBasePath(): void
    {
        $bundler = new Bundler();
        $this->assertEquals(Resolver::getRootPath(), $bundler->getBasePath());

        $bundler = new Bundler(['basepath' => 'tests/fixtures/noconfig']);
        $this->assertEquals(Resolver::makeAbsolute('tests/fixtures/noconfig', Resolver::getRootPath()), $bundler->getBasePath());

        $bundler = new Bundler(['rootpath' => 'tests/fixtures', 'basepath' => 'noconfig']);
        $this->assertEquals($bundler->getRootPath() . '/noconfig', $bundler->getBasePath());
    }

    public function testLoadingConfigFromComposerJson(): void
    {
        $bundler = new Bundler(['rootpath' => 'tests/fixtures/config']);
        $config = $bundler->getConfig();
        $this->assertSame(['nottobeseen.php'], $config->getArray('exclude'));
        $this->assertFalse($config->getBoolean('zip'));
    }

    public function testLoadingConfigFromCustomFile(): void
    {
        // No need to alter root path here as composer.json path will be found from basepath
        $bundler = new Bundler(['rootpath' => 'tests/fixtures/noconfig']);
        $config = $bundler->getConfig();
        $this->assertEmpty($config->get('exclude'));

        $bundler->loadConfig([Resolver::makeAbsolute('composer.json', Resolver::makeAbsolute('tests/fixtures/config')) => 'extra.bundler']);

        $this->assertSame(['nottobeseen.php'], $config->getArray('exclude'));
        $this->assertFalse($config->getBoolean('zip'));
    }

    public function getEntriesData(): array
    {
        return [
            [
                null, null, [],
            ],
            [
                [], [], [
                    'assets',
                    'inc',
                    'composer.json',
                    'plugin.php',
                ],
            ],
            [
                ['**/*.js'], [], ['assets/script.js'],
            ],
            [
                ['assets'], null, [
                    'assets',
                ],
            ],
            [
                ['*'], ['*.json'], [
                    'assets',
                    'inc',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
            [
                ['*'], ['assets'], [
                    'inc',
                    'composer.json',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
            [
                ['*'], ['*.json', 'assets/index.css'], [
                    'assets',
                    'inc',
                    'nottobeseen.php',
                    'plugin.php',
                ], ['assets/index.css'],
            ],
            [
                ['*'], ['*.json', '**/*.css'], [
                    'assets',
                    'inc',
                    'nottobeseen.php',
                    'plugin.php',
                ], ['assets/index.css'],
            ],
        ];
    }

    /**
     * @dataProvider getEntriesData
     */
    public function testGetEntries(?array $include, ?array $exclude, array $expected, array $removed = []): void
    {
        $bundler = new Bundler(['rootpath' => 'tests/fixtures/config', 'include' => $include, 'exclude' => $exclude]);
        $finder = $bundler->getFinder();
        $entries = $finder->getEntries();
        $entriesToRemove = $finder->getEntriesToRemove($bundler->getBasePath());
        $mapped = [];

        foreach ($expected as $p) {
            $mapped[$p] = Resolver::makeAbsolute($p, $bundler->getBasePath());
        }

        $removed = array_map(function ($p) use ($bundler) {
            return Resolver::makeAbsolute($p, $bundler->getBasePath());
        }, $removed);

        asort($entries);
        asort($entriesToRemove);
        asort($mapped);
        asort($removed);

        $this->assertSame($mapped, $entries);
        $this->assertSame($removed, $entriesToRemove);
    }

    public function testBundleNoConfig(): void
    {
        $from = Resolver::makeAbsolute('tests/fixtures/noconfig');
        $bundler = new Bundler([
            'rootpath' => $from,
            'loglevel' => 3,
        ]);

        $path = $bundler->bundle();
        $this->assertEquals(Resolver::makeAbsolute('tests/fixtures/noconfig/dist/bundle.zip'), $path);
        $this->assertTrue(is_dir($from . '/dist'));
        $this->assertTrue(is_file($from . '/dist/bundle.zip'));
        $this->remove($from . '/dist');
    }

    public function testBundleConfigInComposer(): void
    {
        $from = Resolver::makeAbsolute('tests/fixtures/config');
        $bundler = new Bundler([
            'rootpath' => $from,
            'loglevel' => 3,
        ]);

        $bundler->bundle();
        $this->assertTrue(is_dir($from . '/dist'));
        $this->assertTrue(is_dir($from . '/dist/assets'));
        $this->assertTrue(is_file($from . '/dist/composer.json'));
        $this->assertTrue(is_file($from . '/dist/composer.lock'));
        $this->assertTrue(is_file($from . '/dist/plugin.php'));
        $this->assertTrue(is_file($from . '/dist/assets/index.css'));
        $this->assertTrue(is_file($from . '/dist/assets/script.js'));
        $this->assertFalse(is_file($from . '/dist/nottobeseen.zip'));
        $this->remove($from . '/dist');
    }

    public function testBundlePhpScoper(): void
    {
        $from = Resolver::makeAbsolute('tests/fixtures/scoper');
        $bundler = new Bundler([
            'rootpath' => $from,
            'loglevel' => 7,
        ]);

        $bundler->bundle();
        $php = file_get_contents($from . '/dist/inc/Test.php') ?: '';
        $this->assertTrue((bool)strpos($php, 'Isolated\Adbar\Dot'));
        $this->assertTrue((bool)strpos($php, 'Isolated\NS\tmp'));
        $this->assertTrue((bool)strpos($php, ' get_permalink'));
        $this->remove($from . '/dist');
    }

    public function testBundleScopingWithNoConfig(): void
    {
        $from = Resolver::makeAbsolute('tests/fixtures/noconfig');
        $bundler = new Bundler([
            'composer' => ['phpscoper' => true],
            'rootpath' => $from,
            'log' => false,
            'loglevel' => 5,
        ]);

        $bundler->bundle();
        $this->assertTrue(is_file($from . '/dist/bundle.zip'));
        $this->remove($from . '/dist');
    }
}
