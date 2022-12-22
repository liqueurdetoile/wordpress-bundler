<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use Adbar\Dot;
use RuntimeException;

/**
 * Utility class to read and write JSON configuration file (like composer.json)
 *
 * It handles a three layers logic for maximum flexibility:
 * - Overrides layer
 * - Regular layer
 * - Defaults layer
 *
 * When looking for a given key in a configuration setup, Config wi first try to find it in overrides, then in reguar and last in defaults
 * When exporting a full config, keys will be merged from defaults to overrides to retain highest priority layer
 *
 * @author Liqueur de Toile <contact@liqueurdetoile.com>
 * @copyright 2022-present Liqueur de Toile
 * @license GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
 */
class Config
{
    /**
     * Stores cached configuration and enabled dot notation access
     *
     * @var \Adbar\Dot|null
     */
    protected $_config;

    /**
     * Stores default configuration that may be used if required key is missing in current configuration
     *
     * @var \Adbar\Dot
     */
    protected $_defaults;

    /**
     * Stores configuration overrides
     *
     * @var \Adbar\Dot
     */
    protected $_overrides;

    /**
     * Stores path to linked configuration file
     *
     * @var string|null
     */
    protected $_path;

    /**
     * Stores key in linked configuration file
     *
     * @var string|null
     */
    protected $_key;

    /**
     * Creates a configurator instance for easier manipulation
     * Defaults configuration value can be provided as argument
     *
     * @param array|\Adbar\Dot $defaults  Default configuration
     * @param array|\Adbar\Dot $overrides Configuration overrides
     */
    final public function __construct($defaults = [], $overrides = [])
    {
        $this->setDefaults($defaults);
        $this->setOverrides($overrides);
    }

    /**
     * Gets the default values
     *
     * @return \Adbar\Dot
     */
    public function getDefaults(): Dot
    {
        return $this->_defaults;
    }

    /**
     * Gets the default values
     *
     * @return \Adbar\Dot
     */
    public function getOverrides(): Dot
    {
        return $this->_overrides;
    }

    /**
     * Sets configuration default values
     *
     * @param array|\Adbar\Dot $defaults Default values
     * @return self
     */
    public function setDefaults($defaults): self
    {
        if (is_array($defaults)) {
            $defaults = new Dot($defaults);
        }

        $this->_defaults = $defaults;

        return $this;
    }

    /**
     * Sets configuration default values
     *
     * @param array|\Adbar\Dot $overrides Overrides values
     * @return self
     */
    public function setOverrides($overrides): self
    {
        if (is_array($overrides)) {
            $overrides = new Dot($overrides);
        }

        $this->_overrides = $overrides;

        return $this;
    }

    /**
     * Load a configuration into this instance
     *
     * If no path is provided, composer.json at the root project will be used
     * An optional key into configuration can be provided to target a part of the configuration file
     *
     * @param  string|null $key  Optional key in configuration to read from
     * @param  string|null $path Path to JSON configuration file. Defaults to composer.json at root project
     * @return self
     */
    public function load(?string $key = null, ?string $path = null): self
    {
        $this->_config = self::_read($key, $path);
        $this->_path = $path;
        $this->_key = $key;

        return $this;
    }

    /**
     * Get whole a configuration or a targetted key. Dotted keys are allowed to access nested values.
     *
     * If no key is passed, the whole configuration is returned
     *
     * @param  string|null $key Key to fetch
     * @return mixed
     * @throws \RuntimeException If missing key
     */
    public function get(?string $key = null)
    {
        $config = $this->_config ?? new Dot();

        if ($key === null) {
            $ret = new Dot($this->_defaults);
            return $ret
              ->mergeRecursiveDistinct($config)
              ->mergeRecursiveDistinct($this->_overrides)
              ->all();
        }

        if ($this->_overrides->has($key)) {
            return $this->_overrides->get($key);
        }

        if ($config->has($key)) {
            return $config->get($key);
        }

        if ($this->_defaults->has($key)) {
            return $this->_defaults->get($key);
        }

        throw new RuntimeException(sprintf('[WordpressBundler] Missing key "%s" in configuration', $key));
    }

    /**
     * Fetch a value by key and check that it is a boolean
     *
     * @param  string $key Key
     * @return boolean
     */
    public function getBoolean(string $key): bool
    {
        $v = $this->get($key);

        if (!is_bool($v)) {
            throw new RuntimeException(
                sprintf('Wrong configuration for key %s. Expecting a boolean and got %s', $key, gettype($v))
            );
        }

        return $v;
    }

    /**
     * Fetch a value by key and check that it is a string
     *
     * @param  string $key Key
     * @return string
     */
    public function getString(string $key): string
    {
        $v = $this->get($key);

        if (!is_string($v)) {
            throw new RuntimeException(
                sprintf('Wrong configuration for key %s. Expecting a string and got %s', $key, gettype($v))
            );
        }

        return $v;
    }

    /**
     * Sets a value by key in configuration.  Dotted keys are allowed to set up nested values. If the key does not exist, it will be created
     * To persist updates to configuration file, a subsequent call to `Config::save` must be done.
     *
     * @param  string $key   Key to set
     * @param  mixed  $value Value
     * @return self
     */
    public function set(string $key, $value): self
    {
        if ($this->_config === null) {
            throw new RuntimeException('[WordpressBundler] No configuration file loaded');
        }

        $this->_config = $this->_config->set($key, $value);

        return $this;
    }

    /**
     * Sets a value by key in defaults.
     *
     * @param  string $key   Key to set
     * @param  mixed  $value Value
     * @return self
     */
    public function setDefault(string $key, $value): self
    {
        $this->_defaults = $this->_defaults->set($key, $value);

        return $this;
    }

    /**
     * Sets a value by key in overrides.
     *
     * @param  string $key   Key to set
     * @param  mixed  $value Value
     * @return self
     */
    public function setOverride(string $key, $value): self
    {
        $this->_overrides = $this->_overrides->set($key, $value);

        return $this;
    }

    /**
     * Removes a default key
     *
     * @param  string $key Key to remove
     * @return self
     */
    public function dropDefault(string $key): self
    {
        $this->_defaults = $this->_defaults->delete($key);

        return $this;
    }

    /**
     * Removes an overriden key
     *
     * @param  string $key Key to remove
     * @return self
     */
    public function dropOverride(string $key): self
    {
        $this->_overrides = $this->_overrides->delete($key);

        return $this;
    }

    /**
     * Saves stored configuration at path
     *
     * If no path or `null` is provided, currently loaded configuration file will be used
     * If no key or `null` is provided, currently loaded key will be used
     *
     * @param  string|null $key  Optional key in configuration to read from
     * @param  string|null $path Path to JSON configuration file. Defaults to composer.json at root project
     * @return self
     */
    public function save(?string $key = null, ?string $path = null): self
    {
        if ($this->_config === null) {
            throw new RuntimeException('[WordpressBundler] No configuration file loaded');
        }

        self::write($this->_config, $path ?? $this->_path, $key ?? $this->_key);

        return $this;
    }

    /**
     * Returns a config instance
     *
     * @return \Lqdt\WordpressBundler\Config
     */
    public static function getInstance(): Config
    {
        return new static();
    }

    /**
     * Reads a configuration in a JSON file
     *
     * If no path is provided, composer.json at the root project will be used
     * An optional key into configuration can be provided to target a part of the configuration file
     *
     * @param  string|null $key  Optional key in configuration to read from
     * @param  string|null $path Path to JSON configuration file. Defaults to composer.json at root project
     * @return \Adbar\Dot
     */
    public static function read(?string $key = null, ?string $path = null): Dot
    {
        return self::_read($key, $path);
    }

    /**
     * Saves a configuration as JSON. Overwites current configuration or creates a new file if needed configuration.
     *
     * If no path is provided, composer.json at the root project will be used
     * An optional key into configuration can be provided to target a part of the configuration file
     *
     * @param array|\Adbar\Dot $config Configuration to save
     * @param string           $path   Target file.  Defaults to composer.json at root project
     * @param string           $key    Key in target file
     * @return string Path to target file
     */
    public static function write($config, ?string $path = null, ?string $key = null): string
    {
        $path = Resolver::normalize($path ?? Resolver::getBasePath() . DIRECTORY_SEPARATOR . 'composer.json');

        $source = is_file($path) ? self::_read(null, $path) : new Dot();

        if ($key != null) {
            $source->set($key, $config);
        } else {
            $source = new Dot($config);
        }

        file_put_contents($path, json_encode($source->all(), JSON_PRETTY_PRINT), LOCK_EX);

        return $path;
    }

    /**
     * Performs configuration file reading and alters path so it's pointing to the file
     *
     * @param  string|null $key  Optional key in configuration to read from
     * @param  string|null $path Path to JSON configuration file. Defaults to composer.json at root project
     * @return \Adbar\Dot
     * @throws \RuntimeException If unable to read configuration file
     */
    protected static function _read(?string $key, ?string &$path): Dot
    {
        $path = Resolver::getBasePath($path);

        if (is_dir($path)) {
            $path = Resolver::normalize($path . '/composer.json');
        }

        if (!is_file($path)) {
            throw new RuntimeException('[WordpressBundler] Provided configuration file does not exists');
        }

        $content = file_get_contents($path);

        if (!$content) {
            throw new RuntimeException('[WordpressBundler] Unable to read configuration file');
        }

        $content = new Dot(json_decode($content, true, 512, JSON_THROW_ON_ERROR));

        if ($key !== null) {
            if (!$content->has($key)) {
                throw new RuntimeException('[WordpressBundler] Unable to locate requested key in configuration file');
            }

            $content = new Dot($content->get($key));
        }

        return $content;
    }
}
