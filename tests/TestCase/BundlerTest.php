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
        $bundler = new Bundler(['basepath' => 'fixtures/noconfig', 'loglevel' => 3]);
        $dir = $bundler->getBasePath();

        $this->assertTrue(file_exists($dir . '/plugin.php'));
        $this->assertEquals(3, $bundler->getConfig()->get('loglevel'));

        $bundler->getConfig()->set('loglevel', 8);

        $this->assertEquals(8, $bundler->getConfig()->get('loglevel'));
    }

    public function testGetComposerPath(): void
    {
        $bundler = new Bundler(['loglevel' => 3]);
        $this->assertEquals(Resolver::makeAbsolute('composer.json', Resolver::getRootPath()), $bundler->getComposerPath());

        $bundler = new Bundler(['rootpath' => 'fixtures/noconfig', 'loglevel' => 3]);
        $this->assertEquals(Resolver::makeAbsolute('composer.json', $bundler->getRootPath()), $bundler->getComposerPath());
        $this->assertNotEquals(Resolver::makeAbsolute('composer.json', Resolver::getRootPath()), $bundler->getComposerPath());
    }

    public function testGetBasePath(): void
    {
        $bundler = new Bundler(['loglevel' => 3]);
        $this->assertEquals(Resolver::getRootPath(), $bundler->getBasePath());

        $bundler = new Bundler(['loglevel' => 3]);
        $this->assertEquals(Resolver::makeAbsolute(Resolver::getRootPath()  . '/README.MD'), $bundler->getBasePath('README.MD'));

        $bundler = new Bundler(['rootpath' => 'fixtures/noconfig', 'loglevel' => 3]);
        $this->assertEquals($bundler->getRootPath(), $bundler->getBasePath());
        $this->assertNotEquals(Resolver::getRootPath(), $bundler->getBasePath());
    }

    public function testLoadingConfigFromComposerJson(): void
    {
        $bundler = new Bundler(['rootpath' => 'fixtures/config', 'loglevel' => 3]);
        $config = $bundler->getConfig();
        $this->assertSame(['nottobeseen.php'], $config->getArray('exclude'));
        $this->assertFalse($config->getBoolean('zip'));
    }

    public function testLoadingConfigFromCustomFile(): void
    {
        // No need to alter root path here as composer.json path will be found from basepath
        $bundler = new Bundler(['basepath' => 'fixtures/config', 'loglevel' => 3, 'config' => ['composer.json' => 'extra.bundler']]);
        $config = $bundler->getConfig();
        $this->assertSame(['nottobeseen.php'], $config->getArray('exclude'));
        $this->assertFalse($config->getBoolean('zip'));
    }

    public function testParseGitIgnore(): void
    {
        $bundler = new Bundler();
        $ignored = $bundler->parseGitIgnore();
        $this->assertNotEmpty($ignored);

        $bundler = new Bundler(['gitignore' => false]);
        $ignored = $bundler->parseGitIgnore();
        $this->assertEmpty($ignored);
    }

    public function testParseWpIgnore(): void
    {
        $path = Resolver::makeAbsolute('.wpignore');
        file_put_contents($path, 'README.MD');

        $bundler = new Bundler();
        $ignored = $bundler->parseWpIgnore();
        $this->assertNotEmpty($ignored);

        $bundler = new Bundler(['wpignore' => false]);
        $ignored = $bundler->parseWpIgnore();
        $this->assertEmpty($ignored);

        unlink($path);
    }

    public function getEntriesData(): array
    {
        return [
            [
                null, null, [
                    'assets/index.css',
                    'assets/script.js',
                    'inc/class-test.php',
                    'composer.json',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
            [
                ['**/*.js'], [], ['assets/script.js'],
            ],
            [
                ['assets'], null, [
                    'assets/index.css',
                    'assets/script.js',
                ],
            ],
            [
                null, ['*.json'], [
                    'assets/index.css',
                    'assets/script.js',
                    'inc/class-test.php',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
            [
                null, ['assets'], [
                    'inc/class-test.php',
                    'composer.json',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
            [
                null, ['*.json', 'assets/index.css'], [
                    'assets/script.js',
                    'inc/class-test.php',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
            [
                null, ['*.json', '**/*.css'], [
                    'assets/script.js',
                    'inc/class-test.php',
                    'nottobeseen.php',
                    'plugin.php',
                ],
            ],
        ];
    }

    /**
     * @dataProvider getEntriesData
     */
    public function testGetEntries(?array $include, ?array $exclude, array $expected): void
    {
        $bundler = new Bundler(['basepath' => 'fixtures/config', 'include' => $include, 'exclude' => $exclude, 'gitignore' => false, 'wpignore' => false, 'loglevel' => 3]);
        $entries = $bundler->getEntries();
        $mapped = [];
        foreach ($expected as $file) {
            $mapped[$file] = Resolver::makeAbsolute($file, $bundler->getBasePath());
        }

        asort($mapped);
        asort($entries);

        $this->assertSame($mapped, $entries);
    }

    public function testBundleNoConfig(): void
    {
        $from = Resolver::makeAbsolute('fixtures/noconfig');
        $bundler = new Bundler([
            'rootpath' => $from,
            'loglevel' => 3,
        ]);

        $path = $bundler->bundle();
        $this->assertEquals(Resolver::makeAbsolute('fixtures/noconfig/dist/bundle.zip'), $path);
        $this->assertTrue(is_dir($from . '/dist'));
        $this->assertTrue(is_file($from . '/dist/bundle.zip'));
        $this->remove($from . '/dist');
    }

    public function testBundleConfigInComposer(): void
    {
        $from = Resolver::makeAbsolute('fixtures/config');
        $bundler = new Bundler([
            'rootpath' => $from,
            'loglevel' => 7,
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
        $from = Resolver::makeAbsolute('fixtures/scoper');
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
        $from = Resolver::makeAbsolute('fixtures/noconfig');
        $bundler = new Bundler([
            'phpscoper' => true,
            'rootpath' => $from,
            'loglevel' => 7,
        ]);

        $bundler->bundle();
        $this->assertTrue(is_file($from . '/dist/bundle.zip'));
        $this->remove($from . '/dist');
    }
}
