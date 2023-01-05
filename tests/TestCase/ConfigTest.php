<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Test\Testcase;

use Adbar\Dot;
use Lqdt\WordpressBundler\Config;
use Lqdt\WordpressBundler\Exception\MissingKeyConfigException;
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
        $config = Config::read($this->config);

        $this->assertSame(['bundler' => ['clean' => true]], $config->all());

        $this->expectException(\RuntimeException::class);
        Config::read('datSillyPath');
    }

    public function testStaticReadRootComposer(): void
    {
        $config = Config::read();
        $this->assertEquals('lqdt/wordpress-bundler', $config->get('name'));

        $this->expectException(\RuntimeException::class);
        Config::read('datSillyPath');
    }

    public function testStaticWrite(): void
    {
        Config::write(['bundler' => ['clean' => false]], $this->config);
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->all());
    }

    public function testStaticWriteWithDot(): void
    {
        Config::write(new Dot(['bundler' => ['clean' => false]]), $this->config);
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->all());
    }

    public function testStaticWriteNoMerge(): void
    {
        Config::write(['bundler' => ['log' => 6]], $this->config);
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['log' => 6]], $config->all());
    }

    public function testStaticWriteMerge(): void
    {
        Config::write(['bundler' => ['log' => 6]], $this->config, null, true);
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => true, 'log' => 6]], $config->all());
    }

    public function testStaticReadByKey(): void
    {
        $config = Config::read($this->config, 'bundler');
        $this->assertSame(['clean' => true], $config->all());

        $this->expectException(\RuntimeException::class);
        Config::read('datSillyKey', $this->config);
    }

    public function testStaticWriteByKey(): void
    {
        Config::write(['clean' => false], $this->config, 'bundler');
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->all());
    }

    public function testStaticWriteByKeyWithDot(): void
    {
        Config::write(new Dot(['clean' => false]), $this->config, 'bundler');
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->all());
    }

    public function testStaticWriteByKeyWithDotAndMerge(): void
    {
        Config::write(new Dot(['log' => 6]), $this->config, 'bundler', true);
        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => true, 'log' => 6]], $config->all());
    }

    public function testInstanceRead(): void
    {
        $config = Config::getInstance()->load($this->config);
        $this->assertSame(['bundler' => ['clean' => true]], $config->all());

        $this->expectException(\RuntimeException::class);
        Config::getInstance()->load('datSillyPath');
    }

    public function testInstanceWrite(): void
    {
        $instance = Config::getInstance()
          ->load($this->config)
          ->set('bundler.clean', false)
          ->save();

        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->all());

        $instance->getOverrides()->set('bundler.clean', true);
    }

    public function testInstanceReadByKey(): void
    {
        $config = Config::getInstance()->load($this->config, 'bundler');
        $this->assertSame(['clean' => true], $config->all());

        $this->expectException(\RuntimeException::class);
        Config::getInstance()->load('datSillyKey', $this->config);
    }

    public function testInstanceWriteByKey(): void
    {
        Config::getInstance()
          ->load($this->config, 'bundler')
          ->set('clean', false)
          ->save();

        $config = Config::read($this->config);
        $this->assertSame(['bundler' => ['clean' => false]], $config->all());
    }

    public function testInstanceWriteToAnotherKey(): void
    {
        Config::getInstance()
          ->load($this->config, 'bundler')
          ->set('clean', false)
          ->save('defaults', null, 'hacked', true);

        $config = Config::read($this->config);
        $this->assertSame([
          'hacked' => ['clean' => false],
        ], $config->all());
    }

    public function testGetMissingAndFallback(): void
    {
        $this->assertEquals(
            'fallback',
            Config::getInstance()
            ->load($this->config)
            ->get('bundler.missing', 'fallback')
        );
    }

    public function testGetMissingAndThrow(): void
    {
        $this->expectException(MissingKeyConfigException::class);

        Config::getInstance()
            ->load($this->config)
            ->getOrFail('bundler.missing');
    }

    public function testGetBoolean(): void
    {
        $config = Config::getInstance()->load($this->config)->set('test', 'test');
        $this->assertTrue($config->getBoolean('bundler.clean'));
        $this->expectException(\TypeError::class);
        $config->getBoolean('test');
    }

    public function testGetString(): void
    {
        $config = Config::getInstance()->load($this->config)->set('test', 'test');
        $this->assertEquals('test', $config->getString('test'));
        $this->expectException(\TypeError::class);
        $config->getString('bundler.clean');
    }

    public function testCascade(): void
    {
        $config = new Config();

        $config->set('bundler.clean', true);
        $this->assertTrue($config->get('bundler.clean'));

        $config
          ->set('bundler.clean', false, 'overrides')
          ->getOverrides()->set('bundler.zip', 'hacked');

        $this->assertFalse($config->get('bundler.clean'));
        $this->assertEquals('hacked', $config->get('bundler.zip'));

        $config
          ->delete('bundler.clean', 'overrides')
          ->set('bundler.zip', 'bundle');

        $this->assertTrue($config->get('bundler.clean'));
        $this->assertEquals('hacked', $config->get('bundler.zip'));

        $config
          ->delete('bundler.zip', 'overrides')
          ->delete('bundler.clean');

        $this->assertEquals('bundle', $config->get('bundler.zip'));
        $this->expectException(MissingKeyConfigException::class);
        $config->getOrFail('bundler.clean');
    }

    public function testArrayCascade(): void
    {
        $config = new Config();

        $config->setFallbacks(['include' => ['*']]);
        $this->assertSame(['*'], $config->get('include'));

        $config->set('include', []);
        $this->assertSame(['*'], $config->get('include'));

        $config->set('include', null);
        $this->assertSame(null, $config->get('include'));

        $config->set('include', ['item']);
        $this->assertSame(['item'], $config->get('include'));

        $config->set('include', ['plus'], 'overrides');
        $this->assertSame(['plus'], $config->get('include'));
    }

    public function testMerge()
    {
        $config = new Config();

        $config->setDefaults([
            'clean' => true,
            'bundle' => 'test',
            'include' => [
                '*',
            ],
        ]);

        $config->merge([
            'clean' => false,
            'include' => [
                'test.php',
            ],
        ]);

        $this->assertSame([
            'clean' => false,
            'bundle' => 'test',
            'include' => [
                'test.php',
            ],
        ], $config->all());
    }
}
