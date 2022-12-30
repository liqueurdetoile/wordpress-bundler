<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use PhpZip\Constants\ZipCompressionLevel;
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
      'loglevel' => 5,
      'debug' => false,
      'clean' => true,
      'basepath' => null,
      'rootpath' => null,
      'config' => [],
      'gitignore' => true,
      'wpignore' => true,
      'wpinclude' => true,
      'keep' => [
        'dependencies' => true,
        'dev-dependencies' => false,
      ],
      'include' => null,
      'exclude' => null,
      'output' => 'dist',
      'phpscoper' => false,
      'zip' => 'bundle',
    ];

    /**
     * Caches bundler base path
     *
     * @var string
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
     * @var \Lqdt\WordpressBundler\Logger
     */
    protected $_logger;

    /**
     * Caches bundler root path
     *
     * @var string
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

        $this->_logger = Logger::get($this->_config->get('loglevel'));
        $this->_fs = new FileSystem();
        $this->_loadConfig();
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
            $this->setRootPath($this->_config->get('rootpath') ?? Resolver::getRootPath());
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
    public function setRootPath(string $path)
    {
        if ($this->_fs->isAbsolutePath($path)) {
            $this->_rootpath = $path;
        } else {
            $this->_rootpath = Resolver::makeAbsolute($path);
        }

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
            $this->setBasePath($this->_config->get('basepath') ?? $this->getRootPath());
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
    public function setBasePath(?string $path)
    {
        if ($path === null) {
            $this->_basepath = $this->getRootPath();
        } elseif ($this->_fs->isAbsolutePath($path)) {
            $this->_basepath = $path;
        } else {
            $this->_basepath = Resolver::makeAbsolute($path, $this->getRootPath());
        }

        return $this;
    }

    /**
     * Returns the absolute path to composer.json file
     *
     * @return string
     */
    public function getComposerPath(): string
    {
        return $this->getRootPath('composer.json');
    }

    /**
     * Parse and fetch files to ignore from .gitignore
     *
     * If a list is provided, files will be appended to it
     *
     * @param array $exclude Exclude list
     * @return array
     */
    public function parseGitIgnore(array $exclude = []): array
    {
        if (!$this->_config->getBoolean('gitignore')) {
            $this->_log(7, 'Skipping .gitignore to exclude files');

            return $exclude;
        }

        $this->_log(7, 'Parsing .gitignore and adding matching files to exclude list');

        $gitignore = Resolver::normalize(Resolver::getRootPath() . '/.gitignore');

        return $exclude + $this->_getMatchesFromGitFile($gitignore);
    }

    /**
     * Parse and fetch files to ignore from .wpignore
     *
     * If a list is provided, files will be appended to it
     *
     * @param array $exclude Exclude list
     * @return array
     */
    public function parseWpIgnore(array $exclude = []): array
    {
        if (!$this->_config->getBoolean('wpignore')) {
            $this->_log(7, 'Skipping .wpignore to exclude files');

            return $exclude;
        }

        $this->_log(7, 'Parsing .wpignore and adding matching files to exclude list');

        $wpignore = Resolver::normalize(Resolver::getRootPath() . '/.wpignore');

        return $exclude + $this->_getMatchesFromGitFile($wpignore);
    }

    /**
     * Parse and fetch files to ignore from .wpignore
     *
     * If a list is provided, files will be appended to it
     *
     * @param array $exclude Exclude list
     * @return array
     */
    public function parseWpInclude(array $include = []): array
    {
        if (!$this->_config->getBoolean('wpinclude')) {
            $this->_log(7, 'Skipping .wpinclude to include files');

            return $include;
        }

        $this->_log(7, 'Parsing .wpinclude and adding matching files to include list');

        $wpinclude = Resolver::normalize(Resolver::getRootPath() . '/.wpinclude');

        return $include + $this->_getMatchesFromGitFile($wpinclude);
    }


    /**
     * Returns the entries to be copied based on configuration
     *
     * Returned entries will be split between files and folders keys
     *
     * @return array<string>
     */
    public function getEntries(): array
    {
        try {
            $include = $this->_config->getArray('include');
        } catch (\TypeError $err) {
            $include = ['*'];
        }

        try {
            $exclude = $this->_config->getArray('exclude');
        } catch (\TypeError $err) {
            $exclude = [];
        }

        $exclude = $this->parseGitIgnore($exclude);
        $exclude = $this->parseWpIgnore($exclude);
        $include = $this->parseWpInclude($include);

        $excluded = Resolver::resolveMany($exclude, $this->getBasePath());
        $included = Resolver::resolveMany($include, $this->getBasePath());
        $entries = [];

        foreach ($included as $k => $entry) {
            if (in_array($entry, $excluded)) {
                continue;
            }

            $entries[$k] = $entry;
        }

        return $entries;
    }

    /**
     * Creates a new bundle
     *
     * @param array $overrides Configuration overrides provided at runtime
     * @return string Path to bundle folder
     */
    public function bundle(array $overrides = []): string
    {
        $this->_config->setOverrides($overrides);
        $scoped = (bool)$this->_config->get('phpscoper');
        $zipped = (bool)$this->_config->get('zip');
        $useTmpFolder = $scoped || $zipped;

        // Handles directories
        $output = $this->_handleDirectory($this->_config->getString('output'), $this->_config->getBoolean('clean'));
        $otmp = $this->_handleDirectory(Resolver::makeAbsolute('__wpbundler_tmp', sys_get_temp_dir()), true);
        $oscoped = $this->_handleDirectory(Resolver::makeAbsolute('__wpbundler_scoped', sys_get_temp_dir()), true);

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
        $useComposer = $this->_config->getBoolean('keep.dependencies') ||
            $this->_config->getBoolean('keep.dev-dependencies');
        $entries = $this->getEntries();

        $this->_log(7, sprintf('Copy files to %s', $to));

        foreach ($entries as $rpath => $fpath) {
            $tpath = Resolver::makeAbsolute($rpath, $to);

            if (is_file($fpath)) {
                $this->_fs->copy($fpath, $tpath);
                $this->_log(7, sprintf(' - %s => %s', $fpath, $tpath));
            } else {
                $this->_log(4, sprintf('Unable to locate file : %s', $fpath));
            }
        }

        $this->_log(6, sprintf('%s files copied to %s', count($entries), $to));

        if (!$useComposer) {
            $this->_log(7, sprintf('Skipping composer install as requested by configuration'));

            return $to;
        }

        $composer = Resolver::makeAbsolute('composer.json', $to);

        if (!is_file($composer)) {
            $this->_log(4, sprintf('Composer install is requested but no composer.json have been found in %s', $to));

            return $to;
        }

        $this->_log(7, sprintf('Launching composer install'));

        // Update composer.json based on config
        $content = (array)json_decode(file_get_contents($composer) ?: "{}", true, 512, JSON_THROW_ON_ERROR);
        $content = $this->_handleComposerDependencies($content);
        file_put_contents($composer, json_encode($content, JSON_PRETTY_PRINT));

        // Run composer install in target folder
        exec("composer install --no-progress --working-dir={$to} --classmap-authoritative --quiet");

        $this->_log(6, sprintf('Composer dependencies installation done'));

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
        $this->_log(7, 'Start dependencies scoping');

        $scoper = Resolver::makeAbsolute('vendor/bin/php-scoper');
        $config = $this->getRootPath('scoper.inc.php');

        if (!is_file($scoper)) {
            $this->_log(2, '[WordpressBundler] Dependencies obfuscation is required but unable to locate PHP-Scoper script');

            return $from;
        }

        if (exec("{$scoper} add-prefix {$from} -o {$to} -f" . (is_file($config) ? " -c {$config}" : " --no-config")) === false) {
            $this->_log(3, 'Unable to apply dependency scoping. Php-scoper reports a failure');

            return $from;
        } else {
            if (exec("composer dump-autoload --working-dir={$to} --classmap-authoritative --quiet") === false) {
                $this->_log(3, 'Unable dump composer autoload after hosting');

                return $from;
            }
        }

        $this->_log(6, sprintf('Dependencies scoped'));

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
        $this->_log(7, sprintf('Zipping started'));
        $zip = new ZipFile();
        $zip
          ->addDirRecursive($from)
          ->setCompressionLevel(ZipCompressionLevel::MAXIMUM)
          ->saveAsFile($to)
          ->close();

          $this->_log(6, sprintf('Zip bundle created'));
        return $to;
    }

    /**
     * Processes a gitignore like file to filter allowed paths
     *
     * @param string $path Path to file
     * @return array
     */
    protected function _getMatchesFromGitFile(string $path): array
    {
        $matches = [];
        $basepath = $this->getBasePath();

        if (!is_file($path)) {
            $this->_logger->log(4, sprintf('Missing expected file %s', $path));

            return $matches;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            $this->_logger->log(4, sprintf('Unable to parse content of %s', $path));

            return $matches;
        }

        $ruleset = \TOGoS_GitIgnore_Ruleset::loadFromString($content);
        $finder = new \TOGoS_GitIgnore_FileFinder([
            'ruleset' => $ruleset,
            'invertRulesetResult' => false,
            'includeDirectories' => true,
            'defaultResult' => false,
            'callback' => function ($f, $result) use ($basepath, &$matches) {
                if ($result === true) {
                    $matches[$f] = Resolver::makeAbsolute($f, $basepath);
                }
            },
        ]);

        $finder->findFiles($basepath);

        return $matches;
    }

    /**
     * Process composer.json content and updates dependencies and dev dependencies based on configuration
     *
     * @param  array $content Composer.json content
     * @return array          Updated content
     */
    protected function _handleComposerDependencies(array $content): array
    {
        if (!$this->_config->get('keep.dependencies')) {
            unset($content['require']);
        }

        if (!$this->_config->get('keep.dev-dependencies')) {
            unset($content['require-dev']);
        }

        return $content;
    }

    /**
     * Handles creation and/or cleaning of a directory
     *
     * @param  string  $dir   Directory path
     * @param  boolean $clean Clean flag
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

    /**
     * Attempts to load root composer.json `extra.bundler` section and, if provided, additional configuration files
     *
     * @return \Lqdt\WordpressBundler\Bundler
     */
    protected function _loadConfig()
    {
        $this->_log(7, 'Looking for configuration file(s)');
        $loaded = [];

        try {
            $composer = $this->getComposerPath();
            $this->_config->load($composer, 'extra.bundler');
            $loaded[] = $composer . ':extra.bundler';
        } catch (\RuntimeException $err) {
            $this->_log(4, $err->getMessage());
        }

        $files = $this->_config->getArray('config');

        foreach ($files as $path => $key) {
            $path = Resolver::makeAbsolute($path, $this->getBasePath());
            try {
                $this->_config->load($path, $key, 'overrides');
                $loaded[] = "{$path}:{$key}";
            } catch (\RuntimeException $err) {
                $this->_log(3, $err->getMessage());
            }
        }

        foreach ($loaded as $file) {
            $this->_log(7, sprintf('Loaded configuration from %s', $file));
        }

        $this->_log(6, sprintf('Configuration files found : %d', count($loaded)));

        return $this;
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
        switch ($priority) {
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

        $this->_logger->log($priority, $message);

        if ($priority <= 3 && $this->_config->getBoolean('debug')) {
            throw new \RuntimeException("[WordpressBundler] Aborting bundling");
        }

        return $this;
    }
}
