<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\Testcase;

use Lqdt\WordpressBundler\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * Path to a fixtured composer.json
     *
     * @var string
     */
    public $config = __DIR__ . DIRECTORY_SEPARATOR . 'composer.json';

    public function setUp(): void
    {
        parent::setUp();

        file_put_contents($this->config, json_encode([
          'bundler' => [
            'clean' => true,
          ],
        ]));
    }

    public function tearDown(): void
    {
        unlink($this->config);

        parent::tearDown();
    }

    public function testStaticRead(): void
    {
        $config = Config::read(null, $this->config);
        $this->assertSame(['bundler' => ['clean' => true]], $config->get());

        $this->expectException(\RuntimeException::class);
        Config::read(null, 'datSillyPath');
    }

    public function testStaticWrite(): void
    {
        Config::write(['bundler' => ['clean' => false]], $this->config);
        $config = Config::read(null, $this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->get());
    }

    public function testStaticReadByKey(): void
    {
        $config = Config::read('bundler', $this->config);
        $this->assertSame(['clean' => true], $config->get());

        $this->expectException(\RuntimeException::class);
        Config::read('datSillyKey', $this->config);
    }

    public function testStaticWriteByKey(): void
    {
        Config::write(['clean' => false], $this->config, 'bundler');
        $config = Config::read(null, $this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->get());
    }

    public function testInstanceRead(): void
    {
        $config = Config::getInstance()->load(null, $this->config);
        $this->assertSame(['bundler' => ['clean' => true]], $config->get());

        $this->expectException(\RuntimeException::class);
        Config::getInstance()->load(null, 'datSillyPath');
    }

    public function testInstanceWrite(): void
    {
        Config::getInstance()
          ->load(null, $this->config)
          ->set('bundler.clean', false)
          ->save();

        $config = Config::read(null, $this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->get());
    }

    public function testInstanceReadByKey(): void
    {
        $config = Config::getInstance()->load('bundler', $this->config);
        $this->assertSame(['clean' => true], $config->get());

        $this->expectException(\RuntimeException::class);
        Config::getInstance()->load('datSillyKey', $this->config);
    }

    public function testInstanceWriteByKey(): void
    {
        Config::getInstance()
          ->load('bundler', $this->config)
          ->set('clean', false)
          ->save();

        $config = Config::read(null, $this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->get());
    }

    public function testInstanceWriteToAnotherKey(): void
    {
        Config::getInstance()
          ->load('bundler', $this->config)
          ->set('clean', false)
          ->save('hacked');

        $config = Config::read(null, $this->config);
        $this->assertSame([
          'bundler' => ['clean' => true],
          'hacked' => ['clean' => false],
        ], $config->get());
    }

    public function testCascade(): void
    {
        $config = new Config();
        $config->setDefault('bundler.clean', true);
        $this->assertTrue($config->get('bundler.clean'));

        $config
          ->setOverride('bundler.clean', false)
          ->setOverride('bundler.zip', 'hacked');
        $this->assertFalse($config->get('bundler.clean'));
        $this->assertEquals('hacked', $config->get('bundler.zip'));

        $config
          ->load()
          ->dropOverride('bundler.clean')
          ->set('bundler.zip', 'bundle');

        $this->assertTrue($config->get('bundler.clean'));
        $this->assertEquals('hacked', $config->get('bundler.zip'));

        $config
          ->dropOverride('bundler.zip')
          ->dropDefault('bundler.clean');

        $this->assertEquals('bundle', $config->get('bundler.zip'));
        $this->expectException(\RuntimeException::class);
        $config->get('bundler.clean');
    }
}
