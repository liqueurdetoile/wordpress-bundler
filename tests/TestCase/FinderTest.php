<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\TestCase;

use Lqdt\WordpressBundler\Finder;
use Lqdt\WordpressBundler\Resolver;
use PHPUnit\Framework\TestCase;

class FinderTest extends TestCase
{
    public function testInclude(): void
    {
        $finder = new Finder(Resolver::getRootPath());

        $finder->include('README.MD');
        $this->assertSame(['README.MD' => Resolver::makeAbsolute('README.MD')], $finder->getEntries());
    }

    public function testExclude(): void
    {
        $finder = new Finder(Resolver::getRootPath());

        $finder->include('*');
        $finder->exclude('README.MD');
        $this->assertFalse(array_key_exists('README.MD', $finder->getEntries()));
    }

    public function testIncludeParentExcludeChild(): void
    {
        $finder = new Finder(Resolver::getRootPath());
        $dist = Resolver::makeAbsolute('dist');

        $finder->include('*');
        $finder->exclude('src/Config.php');
        $this->assertTrue(array_key_exists('src', $finder->getEntries()));
        $this->assertEquals(Resolver::makeAbsolute('src/Config.php', $dist), $finder->getEntriesToRemove($dist)[0]);
    }

    public function testExcludeParentIncludeChild(): void
    {
        $finder = new Finder(Resolver::getRootPath());
        $dist = Resolver::makeAbsolute('dist');

        $finder->include('*');
        $finder->exclude('src');
        $finder->include('src/Config.php');
        $entries = $finder->getEntries();
        $this->assertFalse(array_key_exists('src', $entries));
        $this->assertTrue(array_key_exists('src/Config.php', $entries));
    }

    public function testFromFile(): void
    {
        $root = Resolver::makeAbsolute('tests/fixtures/resolver');
        $include = Resolver::makeAbsolute('.wpinclude', $root);
        $exclude = Resolver::makeAbsolute('.wpexclude', $root);
        $finder = new Finder($root);

        $finder->includeFromFile($include);

        $this->assertEquals(2, count($finder->getEntries()));

        $finder->excludeFromFile($exclude);

        $this->assertEquals(1, count($finder->getEntries()));
        $this->assertTrue(array_key_exists('main.php', $finder->getEntries()));
    }
}
