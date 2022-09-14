<?php
declare(strict_types=1);

namespace Lqdt\WordpressToolkit;


class Resolver
{
  /**
   * Stores configuration for paths
   *
   * These paths can be used to quicly retrieve dedicated path
   *
   * @var array<string, string>
   */
    protected $_config = [
      'blocks' => 'assets' . DIRECTORY_SEPARATOR . 'blocks',
      'build' => 'assets' . DIRECTORY_SEPARATOR . 'js',
      'i18n' => 'languages',
      'styles' => 'assets' . DIRECTORY_SEPARATOR . 'css',
    ];

    /**
     * Stores base path to resolve from
     *
     * @var string
     */
    protected $_path;

    /**
     * Initializes resolver with rppt path
     *
     * @param string $path Root path to resolve from
     */
    public function __construct(string $path)
    {
        $this->_path = $path;
        $this->_loadConfiguration();
    }

    /**
     * Updates current resolver configuration by overriding it with provided keys
     *
     * @param array $config Configuration
     * @return self
     */
    public function setConfiguration(array $config): self
    {
        $this->_config = $config + $this->_config;

        return $this;
    }

    /**
     * Loads configuration from extension.json at base path if one is available
     *
     * @return void
     */
    protected function _loadConfiguration(): void
    {
        try {
            $config = \file_get_contents($this->_path . \DIRECTORY_SEPARATOR . 'extension.json');
            $condig = \json_decode($config, true);
            $this->setConfiguration($config);
        } catch (Error $err) {
            // Catch anything to silently fail
        }
    }
}
