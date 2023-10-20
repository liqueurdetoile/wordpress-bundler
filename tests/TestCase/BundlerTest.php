<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\Testcase;

use Lqdt\WordpressBundler\Bundler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class BundlerTest extends TestCase
{
    public function remove(string $path): void
    {
        $fs = new Filesystem();

        $fs->remove($path);
    }

    public function testBundlerInit(): void
    {
        $bundler = new Bundler(['basepath' => 'tests/fixtures/noconfig', 'loglevel' => 0]);
        $dir = $bundler->getBasePath();

        $this->assertTrue(file_exists($dir . '/plugin.php'));
        $this->assertEquals(false, $bundler->getConfig()->get('composer.install'));
    }

    public function testGetBasePath(): void
    {
        $home = dirname(dirname(__DIR__));
        $bundler = new Bundler(['log' => false]);

        $this->assertEquals(Path::normalize($home), $bundler->getBasePath());

        $bundler = new Bundler(['basepath' => 'tests/fixtures/noconfig', 'log' => false]);
        $this->assertEquals(Path::makeAbsolute('tests/fixtures/noconfig', $home), $bundler->getBasePath());
    }

    public function testGetTempDir(): void
    {
        $bundler = new Bundler(['log' => false]);
        $tmp = $bundler->getTempDir();

        $this->assertEquals(Path::normalize(sys_get_temp_dir()), Path::getDirectory($tmp));

        $bundler->setConfig(['tmpdir' => '/path/to/tmp']);
        $tmp = $bundler->getTempDir();

        $this->assertEquals('/path/to/tmp', Path::getDirectory($tmp));
    }

    public function testLoadingConfigFromComposerJson(): void
    {
        $bundler = new Bundler(['basepath' => 'tests/fixtures/config']);
        $config = $bundler->getConfig();
        $this->assertSame(['nottobeseen.php'], $config->get('finder.exclude'));
        $this->assertFalse($config->get('zip'));
    }

    public function testLoadingConfigFromCustomFile(): void
    {
        $target = Path::makeAbsolute('tests/fixtures/configfile/_wpbundler.php', dirname(dirname(__DIR__)));
        $bundler = new Bundler(['basepath' => 'tests/fixtures/noconfig', 'log' => false]);
        $config = $bundler->getConfig();
        $this->assertEmpty($config->get('finder.exclude'));

        $bundler->loadConfigFile($target);

        $config = $bundler->getConfig();
        $this->assertSame(['nottobeseen.php'], $config->get('finder.exclude'));
        $this->assertFalse($config->get('zip'));
    }

    public function testSavingConfigFile(): void
    {
        $target = Path::makeAbsolute('tests/fixtures/configfile/_wpbundler.json', dirname(dirname(__DIR__)));
        $bundler = new Bundler(['basepath' => 'tests/fixtures/noconfig', 'log' => false]);
        $config = $bundler->getConfig();
        $config->set('loglevel', 2);
        $bundler->setConfig($config);
        $bundler->saveConfigFile($target);

        $this->assertFileExists($target);

        /** @var \stdClass $config */
        $config = json_decode(file_get_contents($target) ?: '');

        $this->assertEquals(2, $config->loglevel);

        unlink($target);
    }

    public function getEntriesData(): array
    {
        return [
            [
                [
                    "assets" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets",
                    "composer.json" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/composer.json",
                    "inc" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/inc",
                    "nottobeseen.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/nottobeseen.php",
                    "plugin.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/plugin.php",
                ],
            ],
            [
                [
                    'assets/index.css' => 'F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets/index.css',
                    'assets/script.js' => 'F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets/script.js',
                    "composer.json" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/composer.json",
                    'inc/class-test.php' => 'F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/inc/class-test.php',
                    'inc/sub' => 'F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/inc/sub',
                    "nottobeseen.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/nottobeseen.php",
                    "plugin.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/plugin.php",
                ], [
                    'finder' => [
                        'depth' => '< 2',
                    ],
                ],
            ],
            [
                [
                    "assets" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets",
                    "composer.json" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/composer.json",
                    "inc" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/inc",
                    "nottobeseen.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/nottobeseen.php",
                    "plugin.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/plugin.php",
                ], [
                    'finder' => [
                        'exclude' => [],
                    ],
                ],
            ],
            [
                [
                    "assets" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets",
                    "composer.json" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/composer.json",
                    "inc" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/inc",
                ], [
                    'finder' => [
                        'exclude' => ['*.php'],
                    ],
                ],
            ],
            [
                [
                    'assets/index.css' => 'F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets/index.css',
                    'assets/script.js' => 'F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/assets/script.js',
                    "composer.json" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/composer.json",
                ], [
                    'finder' => [
                        'exclude' => ['*.php'],
                        'depth' => -1,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider getEntriesData
     */
    public function testGetEntries(array $expected, array $config = []): void
    {
        $bundler = (new Bundler([
            'basepath' => 'tests/fixtures/noconfig',
            'log' => false,
        ]))->setConfig($config);

        $entries = $bundler->getEntries();

        $this->assertSame($expected, $entries);
    }

    public function testGetEntriesWithWpIgnore(): void
    {
        $bundler = new Bundler([
            'basepath' => 'tests/fixtures/wpignore',
            'log' => false,
        ]);

        $entries = $bundler->getEntries();

        $this->assertSame([
            "assets" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/wpignore/assets",
            "composer.json" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/wpignore/composer.json",
            "inc" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/wpignore/inc",
            "plugin.php" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/wpignore/plugin.php",
        ], $entries);
    }

    public function testCopy(): void
    {
        $bundler = new Bundler([
            'basepath' => 'tests/fixtures/noconfig',
            'log' => false,
        ]);
        $to = 'dist';

        $entries = $bundler->getEntries();
        $entries['silly'] = '/silly';
        $results = $bundler->copy($entries, $to);

        $this->assertSame([
            "to" => "F:/Projets/wordpress/tools/toolkit/tests/fixtures/noconfig/dist",
            "dirs" => 2,
            "files" => 3,
            "failures" => 1,
            "success" => 5,
            "processed" => 6,
            "failed" => [
              "silly" => "/silly",
            ],
        ], $results);

        $this->remove($results['to']);
    }

    public function testBundleNoConfig(): void
    {
        $bundler = new Bundler([
            'basepath' => 'tests/fixtures/noconfig',
            'log' => false,
            'loglevel' => 7,
        ]);

        $basepath = $bundler->getBasePath();
        $zip = $bundler->bundle();
        $this->assertEquals(Path::makeAbsolute('dist/bundle.zip', $basepath), $zip);
        $this->assertTrue(is_file($zip));
        $this->remove(Path::makeAbsolute('dist', $basepath));
    }

    public function testBundleConfigInComposer(): void
    {
        $bundler = new Bundler([
            'basepath' => 'tests/fixtures/config',
            'output' => 'silly',
            'log' => false,
            'loglevel' => 7,
        ]);

        $basepath = $bundler->getBasePath();
        $path = $bundler->bundle();
        $this->assertEquals(Path::makeAbsolute('silly', $basepath), $path);
        $this->assertTrue(is_dir(Path::makeAbsolute('silly', $basepath)));
        $this->assertTrue(is_dir(Path::makeAbsolute('silly/assets', $basepath)));
        $this->assertTrue(is_dir(Path::makeAbsolute('silly/inc', $basepath)));
        $this->assertTrue(is_dir(Path::makeAbsolute('silly/inc/sub', $basepath)));
        $this->assertTrue(is_file(Path::makeAbsolute('silly/composer.json', $basepath)));
        $this->assertTrue(is_file(Path::makeAbsolute('silly/plugin.php', $basepath)));
        $this->assertFalse(is_file(Path::makeAbsolute('silly/nottobeseen.php', $basepath)));
        $this->assertTrue(is_file(Path::makeAbsolute('silly/assets/index.css', $basepath)));
        $this->assertTrue(is_file(Path::makeAbsolute('silly/assets/script.js', $basepath)));
        $this->assertTrue(is_file(Path::makeAbsolute('silly/inc/class.php', $basepath)));
        $this->assertTrue(is_file(Path::makeAbsolute('silly/inc/sub/class.php', $basepath)));
        $this->remove(Path::makeAbsolute('silly', $basepath));
    }

    public function testBundlePhpScoper(): void
    {
        $bundler = new Bundler([
            'basepath' => 'tests/fixtures/scoper',
            'log' => false,
            'loglevel' => 7,
        ]);

        $basepath = $bundler->getBasePath();
        $bundler->bundle();
        $php = file_get_contents(Path::makeAbsolute('dist/inc/Test.php', $basepath)) ?: '';
        $this->assertTrue((bool)strpos($php, 'Isolated\Adbar\Dot'));
        $this->assertTrue((bool)strpos($php, 'Isolated\NS\tmp'));
        $this->assertTrue((bool)strpos($php, ' get_permalink'));
        $this->remove(Path::makeAbsolute('dist', $basepath));
    }
}
