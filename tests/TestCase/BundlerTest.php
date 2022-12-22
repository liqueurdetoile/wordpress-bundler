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

    public function testBundleNoConfig(): void
    {
        $from = Resolver::makeAbsolute('fixtures/noconfig');
        $bundler = new Bundler([], $from);

        $path = $bundler->bundle();
        $this->assertEquals(Resolver::makeAbsolute('fixtures/noconfig/dist/bundle.zip'), $path);
        $this->assertTrue(is_dir($from . '/dist'));
        $this->assertTrue(is_file($from . '/dist/bundle.zip'));
        $this->remove($from . '/dist');
    }

    public function testBundleNoConfigOverride(): void
    {
        $from = Resolver::makeAbsolute('fixtures/noconfig');
        $bundler = new Bundler(['output' => 'build', 'zip' => 'myplugin'], $from);

        $bundler->bundle();
        $this->assertTrue(is_dir($from . '/build'));
        $this->assertTrue(is_file($from . '/build/myplugin.zip'));
        $this->remove($from . '/build');
    }

    public function testBundleConfig(): void
    {
        $from = Resolver::makeAbsolute('fixtures/config');
        $bundler = new Bundler([], $from);

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

    public function testBundleScoper(): void
    {
        $from = Resolver::makeAbsolute('fixtures/scoper');
        $bundler = new Bundler([], $from);

        $bundler->bundle();
        $php = file_get_contents($from . '/dist/inc/Test.php') ?: '';
        $this->assertTrue((bool)strpos($php, 'Isolated\Adbar\Dot'));
        $this->assertTrue((bool)strpos($php, 'Isolated\NS\tmp'));
        $this->assertTrue((bool)strpos($php, ' get_permalink'));
        $this->remove($from . '/dist');
    }
}
