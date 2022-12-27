<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use Adbar\Dot;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Utility class to manage configurations.
 *
 * It handles three layers of logic for maximum flexibility:
 *
 * - Fallbacks layer
 * - Defaults layer
 * - Overrides layer
 *
 * When looking for a given key in a configuration setup, Config will first try to find it in overrides, then in defaults and finally in fallbacks
 * When exporting a full config, keys will be merged from defaults to overrides to retain highest priority layer value
 *
 * Config also embeds import and export of configurations from JSON files
 *
 * @author Liqueur de Toile <contact@liqueurdetoile.com>
 * @copyright 2022-present Liqueur de Toile
 * @license GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
 */
class Config
{
    /**
     * Stores default configuration and enabled dot notation access
     *
     * @var \Adbar\Dot
     */
    protected $_defaults;

    /**
     * Stores fallback configuration that may be used if required key is missing in overrides and default configuration
     *
     * @var \Adbar\Dot
     */
    protected $_fallbacks;

    /**
     * Stores configuration overrides that have priority on default and fallback settings
     *
     * @var \Adbar\Dot
     */
    protected $_overrides;

    /**
     * Stores path to last loaded configuration file
     *
     * @var string|null
     */
    protected $_path;

    /**
     * Stores key used with last loaded configuration file. Key can be a dotted path
     *
     * @var string|null
     */
    protected $_key;

    /**
     * Creates a configurator instance for easier manipulation as it exposes convenient getter and setters
     * Different configurations can be provided as arguments
     *
     * @param array|\Adbar\Dot $defaults  Default full configuration
     * @param array|\Adbar\Dot $fallbacks Fallback configuration
     * @param array|\Adbar\Dot $overrides Configuration overrides
     */
    final public function __construct($defaults = [], $fallbacks = [], $overrides = [])
    {
        $this
            ->setDefaults($defaults)
            ->setFallbacks($fallbacks)
            ->setOverrides($overrides);
    }

    /**
     * Returns a configuration instance
     *
     * @param array|\Adbar\Dot $defaults  Default full configuration
     * @param array|\Adbar\Dot $fallbacks Fallback configuration
     * @param array|\Adbar\Dot $overrides Configuration overrides
     * @return \Lqdt\WordpressBundler\Config
     */
    public static function getInstance($defaults = [], $fallbacks = [], $overrides = []): Config
    {
        return new static($defaults, $fallbacks, $overrides);
    }

    /**
     * Validates and normaizes a path to a file. If no path is provided, validation
     * will returns the path to the root composer.json in project
     *
     * @param string|null $path  Path to validate
     * @param boolean     $throw If true, a missing file will throw a RuntimeException
     * @throws \RuntimeException If no file can be found at normalized target path
     * @return string
     */
    public static function validateFilePath(?string $path = null, bool $throw = true): string
    {
        $basepath = Resolver::getBasePath($path);

        if (is_dir($basepath)) {
            $basepath = Resolver::normalize($basepath . '/composer.json');
        }

        if (!is_file($basepath) && $throw) {
            throw new RuntimeException(sprintf('[WordpressBundler] No configuration file found at %s', $basepath));
        }

        return $basepath;
    }

    /**
     * Reads content of a JSON file
     *
     * If no path is provided, composer.json at the root project will be used
     * An optional key into configuration can be provided to target a part of the configuration file
     *
     * @param  string|null $path  Path to JSON configuration file. Defaults to composer.json at root project
     * @param  string|null $key   Optional key in configuration to read from
     * @param  boolean     $throw If `true`, any error in accessing file content will raise an exception. Otherwise an empty Dot object is returned
     * @return \Adbar\Dot
     */
    public static function read(?string $path = null, ?string $key = null, bool $throw = true): Dot
    {
        $path = self::validateFilePath($path);
        $content = file_get_contents($path);

        if ($content === false) {
            if (!$throw) {
                return new Dot();
            }

            throw new RuntimeException(
                sprintf('[WordpressBundler] Unable to read configuration file at path %s', $path)
            );
        }

        try {
            $content = new Dot(json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException $err) {
            if (!$throw) {
                return new Dot();
            }

            throw new RuntimeException(
                sprintf('[WordpressBundler] Unable to parse as JSON configuration file at path %s', $path)
            );
        }

        if ($key !== null) {
            if (!$content->has($key)) {
                if (!$throw) {
                    return new Dot();
                }

                throw new RuntimeException('[WordpressBundler] Unable to locate requested key in configuration file');
            }

            $content = new Dot($content->get($key));
        }

        return $content;
    }

    /**
     * Saves a configuration as JSON. Creates a new file or overwrites content unless merge parameter is set to true
     *
     * If no path is provided, composer.json at the root project will be used
     * An optional key into configuration can be provided to target a part of the configuration file
     *
     * @param array|\Adbar\Dot $config Configuration to save
     * @param string           $path   Target file.  Defaults to composer.json at root project
     * @param string           $key    Key in target file
     * @param boolean          $merge  If true, the configuration will be merged into configuration
     * @return string Path to target file
     */
    public static function write($config, ?string $path = null, ?string $key = null, bool $merge = false): string
    {
        $path = self::validateFilePath($path);
        $source = self::read($path, $key, false);
        $config = new Dot($config);
        $source = $merge ?
            $source->mergeRecursiveDistinct($config) :
            $config;
        $data = $key === null ? $source : (new Dot())->set($key, $source);


        file_put_contents($path, json_encode($data->all(), JSON_PRETTY_PRINT), LOCK_EX);

        return $path;
    }

    /**
     * Returns the fallbacks registry
     *
     * @return \Adbar\Dot
     */
    public function &getFallbacks(): Dot
    {
        return $this->_fallbacks;
    }

    /**
     * Sets configuration fallbacks registry
     *
     * @param array|\Adbar\Dot $fallbacks Fallback values
     * @return self
     */
    public function setFallbacks($fallbacks)
    {
        $this->_fallbacks = new Dot($fallbacks);

        return $this;
    }


    /**
     * Returns the defaults registry
     *
     * @return \Adbar\Dot
     */
    public function &getDefaults(): Dot
    {
        return $this->_defaults;
    }

    /**
     * Sets configuration defaults registry
     *
     * @param array|\Adbar\Dot $defaults Default values
     * @return self
     */
    public function setDefaults($defaults): self
    {
        $this->_defaults = new Dot($defaults);

        return $this;
    }

    /**
     * Gets the overrides registry
     *
     * @return \Adbar\Dot
     */
    public function &getOverrides(): Dot
    {
        return $this->_overrides;
    }

    /**
     * Sets configuration overrides
     *
     * @param array|\Adbar\Dot $overrides Overrides values
     * @return self
     */
    public function setOverrides($overrides): self
    {
        $this->_overrides = new Dot($overrides);

        return $this;
    }

    /**
     * Returns the current whole config as a registry by merging the fallbacks, default and overrides registry based on highest priority
     *
     * @return \Adbar\Dot
     */
    public function getConfig(): Dot
    {
        $config = new Dot($this->_fallbacks);

        return $config
            ->mergeRecursiveDistinct($this->_defaults)
            ->mergeRecursiveDistinct($this->_overrides);
    }

    /**
     * Load configuration from a json file into this instance
     *
     * If no path is provided, composer.json at the root project will be used
     * An optional key into configuration can be provided to target a path into the configuration file. Dotted notation to target nested keys is allowed.
     *
     * A specific registry may be targetted of needed.
     *
     * @param  string|null $path     Path to JSON configuration file. Defaults to composer.json at root project
     * @param  string|null $key      Optional key in configuration to read from
     * @param  string      $registry Configuration registry to load configuration into. Can be any between fallbacks, overrides and defaults
     * @return self
     */
    public function load(?string $path = null, ?string $key = null, string $registry = 'defaults'): self
    {
        $path = self::validateFilePath($path);
        $method = 'set' . ucFirst($registry);
        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException(
                sprintf(
                    '[WordpressBundler] Target config registry is not valid. ' .
                    'Expecting defaults, fallbacks or overrides and get %s',
                    $registry
                )
            );
        }
        $this->{$method}(self::read($path, $key));
        $this->_path = $path;
        $this->_key = $key;

        return $this;
    }

    /**
     * Saves a configuration registry in a file as JSON
     *
     * If no path or `null` is provided, latest loaded configuration file will be used
     * If no key or `null` is provided, latest used key will be used
     *
     * @param  string      $registry Configuration registry to save. Can be any between fallbacks, overrides, defaults and config
     * @param  string|null $path     Path to JSON configuration file. Defaults to composer.json at root project
     * @param  string|null $key      Optional key in configuration to read from
     * @param boolean     $merge    If true, the configuration will be merged into configuration already present in file
     * @return self
     */
    public function save(
        string $registry = 'defaults',
        ?string $path = null,
        ?string $key = null,
        bool $merge = false
    ): self {
        $method = 'get' . ucFirst($registry);
        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException(
                sprintf(
                    '[WordpressBundler] Source config registry is not valid. ' .
                    'Expecting defaults, fallbacks, overrides or config and get %s',
                    $registry
                )
            );
        }

        self::write($this->{$method}(), $path ?? $this->_path, $key ?? $this->_key, $merge);

        return $this;
    }

    /**
     * Get whole a configuration or a targetted key. Dotted keys are allowed to access nested values.
     *
     * If no key is passed, the whole configuration is returned as an array
     *
     * @param  string|null $key Key to fetch
     * @return mixed
     * @throws \RuntimeException If missing key
     */
    public function get(?string $key = null)
    {
        $config = $this->getConfig();

        if ($key === null) {
            return $config->all();
        }

        if ($config->has($key)) {
            return $config->get($key);
        }

        throw new RuntimeException(sprintf('[WordpressBundler] Missing key "%s" in configuration', $key));
    }

    /**
     * Fetches a value in config and ensures that correct type is returned through a validator
     *
     * @param string   $key       Key to fetch
     * @param string   $type      Expected data type as string
     * @param callable $validator Validator callback
     * @throws \TypeError   If returned value is
     * @return mixed
     */
    public function getWithType(string $key, string $type, callable $validator)
    {
        $v = $this->get($key);

        if (!$validator($v)) {
            throw new \TypeError(
                sprintf('Wrong value type for key %s. Expecting a %s and got %s', $key, $type, gettype($v))
            );
        }

        return $v;
    }

    /**
     * Fetch a value by key and check that it is a boolean
     *
     * @param  string $key Key
     * @return boolean
     */
    public function getBoolean(string $key): bool
    {
        return $this->getWithType($key, 'boolean', 'is_bool');
    }

    /**
     * Fetch a value by key and check that it is a string
     *
     * @param  string $key Key
     * @return string
     */
    public function getString(string $key): string
    {
        return $this->getWithType($key, 'string', 'is_string');
    }

    /**
     * Sets a value by key in default configuration.  Dotted keys are allowed to set up nested values. If the key does not exist, it will be created
     * To persist updates to configuration file, a subsequent call to `Config::save` must be done.
     *
     * @param  string $key      Key to set
     * @param  mixed  $value    Value
     * @param string $registry Targetted registry.  Can be any between fallbacks, overrides, defaults
     * @return self
     */
    public function set(string $key, $value, string $registry = 'defaults'): self
    {
        switch ($registry) {
            case 'fallbacks':
                $this->_fallbacks->set($key, $value);
                break;
            case 'defaults':
                $this->_defaults->set($key, $value);
                break;
            case 'overrides':
                $this->_overrides->set($key, $value);
                break;
            default:
                throw new InvalidArgumentException(
                    sprintf(
                        '[WordpressBundler] Target config registry for dropping key is not valid. ' .
                        'Expecting defaults, fallback or overrides and get %s',
                        $registry
                    )
                );
        }

        return $this;
    }

    /**
     * Removes one or many keys in one or all registries
     *
     * @param string|integer|array $key      Key to remove
     * @param string               $registry Targetted registry or all registries with `config` value.  Can be any between fallbacks, overrides, defaults and config
     * @return self
     */
    public function delete($key, string $registry = 'defaults'): self
    {
        switch ($registry) {
            case 'fallbacks':
                $this->_fallbacks->delete($key);
                break;
            case 'defaults':
                $this->_defaults->delete($key);
                break;
            case 'overrides':
                $this->_overrides->delete($key);
                break;
            case 'config':
                $this->_fallbacks->delete($key);
                $this->_defaults->delete($key);
                $this->_overrides->delete($key);
                break;
            default:
                throw new InvalidArgumentException(
                    sprintf(
                        '[WordpressBundler] Target config registry for dropping key is not valid. ' .
                        'Expecting defaults, fallbacks, overrides or config and get %s',
                        $registry
                    )
                );
        }

        return $this;
    }
}
