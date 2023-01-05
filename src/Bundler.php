<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use PhpZip\Constants\ZipCompressionLevel;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Core bundler class
 *
 * @author Liqueur de Toile <contact@liqueurdetoile.com>
 * @copyright 2022-present Liqueur de Toile
 * @license GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
 */
class Bundler
{
    /**
     * Bundler basic configuration. It will be always loaded as fallback values if no additional configuration is provided
     * As default, no file are included
     *
     * @var array
     */
    protected $_defaults = [
      'log' => false,
      'loglevel' => 5,
      'debug' => false,
      'clean' => true,
      'basepath' => null,
      'rootpath' => null,
      'config' => [],
      'include' => ['*'],
      'exclude' => [],
      'composer' => [
        'install' => true,
        'dev-dependencies' => false,
        'phpscoper' => false,
      ],
      'output' => 'dist',
      'zip' => 'bundle',
    ];

    /**
     * Caches bundler base path
     *
     * @var string
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected $_basepath;

    /**
     * Stores Bundler configuration
     *
     * @var \Lqdt\WordpressBundler\Config
     */
    protected $_config;

    /**
     * Stores filesystem instance
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $_fs;

    /**
     * Stores Logger instance
     *
     * @var \Laminas\Log\Logger
     */
    protected $_logger;

    /**
     * Stores bundling result code
     *
     * - 0 : No error
     * - 1 : Error during export (missing files)
     * - 2 : Error during composer install
     * - 3 : Error during dependencies scoping
     * - 4 : Error during zipping
     *
     * @var int
     */
    protected $_result = 0;

    /**
     * Caches bundler root path
     *
     * @var string
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected $_rootpath;


    /**
     * Creates a bundler instance
     *
     * Bundler configuration can be provided as a full Config object or as an array
     *
     * If no base path to project root is provided, root project directory will be used. Basepath should point to a
     * valid project directory. The path can be either relative to this class or absolute
     *
     * @param \Lqdt\WordpressBundler\Config|array $config Configuration
     */
    public function __construct($config = [])
    {
        if ($config instanceof Config) {
            $this->_config = $config->setFallbacks($this->_defaults);
        } else {
            $this->_config = new Config($config, $this->_defaults);
        }

        $this->_logger = Logger::get($this->_config->getInt('loglevel'));
        $this->_fs = new FileSystem();
        $this->_loadConfigFile('composer.json', 'extra.bundler', 'defaults');
        $this->getConfig()->merge($config);
    }

    /**
     * Returns the configuration instance of the bundler. Configuration is returned by reference so
     * any changes made to it will be persisted.
     *
     * @return \Lqdt\WordpressBundler\Config
     */
    public function &getConfig(): Config
    {
        return $this->_config;
    }

    public function loadConfig($config, string $registry = 'overrides')
    {
        foreach ($config as $path => $key) {
            if (is_string($path)) {
                $this->_loadConfigFile($path, $key, $registry);
            } else {
                $this->_loadConfigFile($key, null, $registry);
            }
        }

        return $this;
    }

    /**
     * Returns the root path of the project based on configuration
     *
     * An additional path can be provided. it will be parsed relatively to bundler root path
     *
     * @param string|null $path Path to resolve from bundler root path
     * @return string
     */
    public function getRootPath(?string $path = null): string
    {
        if (empty($this->_rootpath)) {
            try {
                $this->setRootPath($this->_config->getString('rootpath'));
            } catch (\TypeError $err) {
                $this->setRootPath();
            }
        }

        return $path === null ?
            $this->_rootpath :
            Resolver::makeAbsolute($path, $this->_rootpath);
    }

    /**
     * Sets the root path for the bundler. If provided path is not absolute, it will be resolved
     * from project root path determined by `composer.json`.
     *
     * @param string $path Path to use as root path
     * @return \Lqdt\WordpressBundler\Bundler
     */
    public function setRootPath(?string $path = null)
    {
        $this->_rootpath = Resolver::getRootPath($path);

        return $this;
    }

    /**
     * Returns bundler base path
     *
     * An additional path can be provided. it will be parsed relatively to bundler root path
     *
     * @param string|null $path Path to resolve from bundler base path
     * @return string
     */
    public function getBasePath(?string $path = null): string
    {
        if (empty($this->_basepath)) {
            try {
                $this->setBasePath($this->_config->getString('basepath'));
            } catch (\TypeError $err) {
                $this->setBasePath();
            }
        }

        return $path === null ?
            $this->_basepath :
            Resolver::makeAbsolute($path, $this->_basepath);
    }

    /**
     * Sets bundler base path. If providing null, base path will be set to project root
     *
     * @param string|null $path Base path
     * @return \Lqdt\WordpressBundler\Bundler
     */
    public function setBasePath(?string $path = null)
    {
        if ($path === null) {
            $this->_basepath = $this->getRootPath();
        } else {
            $this->_basepath = Resolver::makeAbsolute($path, $this->getRootPath());
        }

        return $this;
    }

    /**
     * Returns the bundle result
     *
     * @return int
     */
    public function getResult(): int
    {
        return $this->_result;
    }

    /**
     * Returns the finder with resolved entries based on configuration
     *
     * @return \Lqdt\WordpressBundler\Finder
     */
    public function getFinder(): Finder
    {
        $this->_log(6, sprintf('Paths resolving starting'));

        $finder = new Finder($this->getBasePath());

        try {
            $finder->includeFromFile($this->getRootPath('.wpinclude'));
        } catch (\RuntimeException $err) {
            $this->_log(7, 'No .wpinclude file found at root path');
        }

        try {
            $finder->includeMany($this->_config->getArray('include'));
        } catch (\TypeError $err) {
            $this->_log(7, 'No include directives found in config files');
        }

        try {
            $finder->excludeFromFile($this->getRootPath('.wpexclude'));
        } catch (\RuntimeException $err) {
            $this->_log(7, 'No .wpexclude file found at root path');
        }

        try {
            $finder->excludeMany($this->_config->getArray('exclude'));
        } catch (\TypeError $err) {
            $this->_log(7, 'No exclude directives found in config files');
        }

        $this->_log(5, sprintf('Paths resolving done : %d entries found', count($finder->getEntries())));

        return $finder;
    }

    /**
     * Creates a new bundle
     *
     * @param array $overrides Configuration overrides provided at runtime
     * @return string Path to bundle folder
     */
    public function bundle(array $overrides = []): string
    {
        $this->getConfig()->merge($overrides, 'overrides');

        $this->_log(-1, 'WordpressBundler - Bundling starting');
        $this->_log(-1, '------------------------------------');

        $scoped = (bool)$this->_config->get('composer.phpscoper');
        $zipped = (bool)$this->_config->get('zip');
        $useTmpFolder = $scoped || $zipped;

        // Handles directories
        $output = $this->_handleDirectory(
            $this->_config->getString('output'),
            $this->_config->getBoolean('clean')
        );
        $otmp = $this->_handleDirectory(
            Resolver::makeAbsolute(uniqid('__wpbundler_tmp_'), sys_get_temp_dir()),
            true
        );
        $oscoped = $this->_handleDirectory(
            Resolver::makeAbsolute(uniqid('__wpbundler_scoped_'), sys_get_temp_dir()),
            true
        );

        // Export files and structures
        $target = $this->export($useTmpFolder ? $otmp : $output);

        // Apply php-scoper
        if ($scoped) {
            $target = $this->scope($target, $zipped ? $oscoped : $output);
        }

        // Zip
        if ($zipped) {
            $target = $this->zip($target, Resolver::normalize("{$output}/{$this->_config->getString('zip')}.zip"));
        }

        // Clear temporary folders
        $this->_fs->remove($otmp);
        $this->_fs->remove($oscoped);

        return $target;
    }

    /**
     * Copy entries to a target folder and install the dependencies accordingly to configuration
     *
     * @param  string $to Target path
     * @return string target path
     */
    public function export(string $to): string
    {
        $this->_log(6, sprintf('Copy files'));
        $this->_log(7, sprintf('Target directory is %s', $to));

        $useComposer = $this->_config->getBoolean('composer.install');
        $finder = $this->getFinder();
        // Ensures that destination folder won't be in included entries
        $finder->exclude($this->getConfig()->getString('output'));
        $entries = $finder->getEntries();
        $toRemove = $finder->getEntriesToRemove($to);
        $copied = 0;

        foreach ($entries as $rpath => $fpath) {
            $tpath = Resolver::makeAbsolute($rpath, $to);

            if (is_dir($fpath)) {
                $this->_fs->mirror($fpath, $tpath);
                $copied++;
                $this->_log(7, sprintf(' - %s', $rpath));
            } elseif (is_file($fpath)) {
                $this->_fs->copy($fpath, $tpath);
                $copied++;
                $this->_log(7, sprintf(' - %s', $rpath));
            } else {
                $this->_result = 1;
                $this->_log(4, sprintf('Unable to locate file : %s', $fpath));
            }
        }

        // Clean excluded childs
        $this->_fs->remove($toRemove);
        $this->_log(5, sprintf('%s files and folders copied', $copied));

        if (!$useComposer) {
            $this->_log(6, sprintf('Skipping composer install as requested by configuration'));

            return $to;
        }

        $composer = Resolver::makeAbsolute('composer.json', $to);

        if (!is_file($composer)) {
            $this->_log(4, sprintf('Composer install is requested but no composer.json have been found in %s', $to));

            return $to;
        }

        $this->_log(6, sprintf('Launching composer install'));

        // Run composer install in target folder
        // Use of install ensures that composer.lock will be used to deploy dependencies used versions
        $cmd = "composer install --no-progress --working-dir={$to} --classmap-authoritative";
        $cmd .= $this->getConfig()->getBoolean('composer.dev-dependencies') ? ' --dev' : ' --no-dev';
        $cmd .= $this->getConfig()->getBoolean('debug') ? '' : ' --quiet';

        $result = $this->_exec($cmd);
        if ($result > 0) {
            $this->_result = 2;
            $this->_log(3, sprintf('Unable to execute composer install'));
        } else {
            $this->_log(5, sprintf('Composer dependencies installation done'));
        }

        return $to;
    }

    /**
     * Applies PHP-Scoper
     *
     * @param  string $from From path
     * @param  string $to   To path
     * @return string To path
     */
    public function scope(string $from, string $to): string
    {
        $this->_log(6, 'Start dependencies scoping');

        $scoper = is_file('php-scoper') ? 'php-scoper' : Resolver::makeAbsolute('vendor/bin/php-scoper');
        $config = $this->getRootPath('scoper.inc.php');

        if (!is_file($scoper)) {
            $this->_log(
                2,
                '[WordpressBundler] Dependencies obfuscation is required but unable to locate PHP-Scoper script'
            );

            return $from;
        }

        $cmd = "{$scoper} add-prefix {$from} -o {$to} -f -q" . (is_file($config) ? " -c {$config}" : " --no-config");
        $result = $this->_exec($cmd);

        if ($result > 0) {
            $this->_result = 3;
            $this->_log(3, 'Unable to apply dependency scoping. Php-scoper reports a failure');

            return $from;
        }

        $cmd = "composer dump-autoload --working-dir={$to} --classmap-authoritative";
        $cmd .= $this->getConfig()->getBoolean('debug') ? '' : ' --quiet';
        $result = $this->_exec($cmd);

        if ($result > 0) {
            $this->_result = 3;
            $this->_log(3, 'Unable to dump composer autoload after scoping');

            return $from;
        }

        $this->_log(5, sprintf('Dependencies scoped and autoload dumped'));

        return $to;
    }

    /**
     * Perform zip
     *
     * @param  string $from From path (must be a folder)
     * @param  string $to   To path (must be a valid path/archive.zip)
     * @return string Target path
     */
    public function zip(string $from, string $to): string
    {
        $this->_log(6, sprintf('Zipping files started'));
        try {
            $zip = new ZipFile();
            $zip
                ->addDirRecursive($from)
                ->setCompressionLevel(ZipCompressionLevel::MAXIMUM)
                ->saveAsFile($to)
                ->close();

            $this->_log(5, sprintf('Zip bundle created'));
        } catch (ZipException $err) {
            $this->_result = 4;
            $this->_log(3, $err->getMessage());
        }

        return $to;
    }

    /**
     * Executes a shell command and returns result code
     *
     * If debug is enabled, the command output will be parsed if available
     *
     * @param string $cmd Command to execute
     * @return int
     */
    protected function _exec(string $cmd): int
    {
        $debug = $this->getConfig()->getBoolean('debug');
        exec($cmd, $output, $result);

        if ($result > 0 && $debug && is_array($output)) {
            foreach ($output as $line) {
                $this->_log(7, $line);
            }
        }

        return $result;
    }

    /**
     * Handles creation and/or cleaning of a directory
     *
     * @param  string  $dir   Directory path
     * @param  bool $clean Clean flag
     * @return string  Aboslute path to directory
     */
    protected function _handleDirectory(string $dir, bool $clean): string
    {
        $dir = Resolver::makeAbsolute($dir, $this->getBasePath());

        if (!is_dir($dir)) {
            $this->_fs->mkdir($dir);
        } elseif ($clean === true) {
            $this->_fs->remove($dir);
            $this->_fs->mkdir($dir);
        }

        return $dir;
    }

    protected function _loadComposerConfig(): void
    {
        $config = $this->getConfig();
        $this->_log(6, 'Loading configuration from composer.json');

        try {
            $composer = $this->$this->getRootPath('composer.json');
            $config->load($composer, 'extra.bundler');
            $this->_log(5, 'Configuration loaded from composer.json');
        } catch (\RuntimeException $err) {
            $this->_log(4, $err->getMessage());
        }
    }

    /**
     * Loads a configuration file into config
     *
     * @param string $path Path to file
     * @param string|null $key Key in file
     * @param string $registry Targetted config registry
     * @return void
     */
    protected function _loadConfigFile(string $path, ?string $key, string $registry): void
    {
        $this->_log(6, sprintf('Loading configuration from %s', $path));
        $config = $this->getConfig();
        $path = $this->getRootPath($path);

        try {
            $config->load($path, $key, $registry);
            $this->_log(5, sprintf('Configuration loaded from %s', $path));
        } catch (\RuntimeException $err) {
            $this->_log(4, $err->getMessage());
        }
    }

    /**
     * Handles logging and error management
     *
     * @param int $priority Log priority
     * @param string  $message  Message
     * @return \Lqdt\WordpressBundler\Bundler
     * @throws \RuntimeException If log is an error one
     */
    protected function _log(int $priority, string $message)
    {
        $logEnabled = $this->_config->getBoolean('log');
        switch ($priority) {
            case -1:
                $priority = 5;
                break;
            case 0:
                $message = "\033[1;31m{$message}\033[37m";
                break;
            case 1:
                $message = "\033[1;31m{$message}\033[37m";
                break;
            case 2:
                $message = "\033[1;31m{$message}\033[37m";
                break;
            case 3:
                $message = "\033[0;31m{$message}\033[37m";
                break;
            case 4:
                $message = "\033[1;33m{$message}\033[37m";
                break;
            case 5:
                $message = "\033[1;32m{$message}\033[37m";
                break;
            case 6:
                $message = "\033[0;32m{$message}\033[37m";
                break;
            case 7:
                $message = "\033[1;34m{$message}\033[37m";
                break;
        }

        if ($logEnabled) {
            $this->_logger->log($priority, $message);
            // ob_flush();
        }

        if ($priority <= 3 && $this->_config->getBoolean('debug')) {
            throw new \RuntimeException("[WordpressBundler] Aborting bundling");
        }

        return $this;
    }
}
