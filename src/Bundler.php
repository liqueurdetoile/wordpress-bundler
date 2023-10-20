<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use Adbar\Dot;
use ComposerLocator;
use Laminas\Config\Exception\RuntimeException;
use Laminas\Config\Factory;
use Laminas\Log\Filter\Priority;
use Laminas\Log\Formatter\Simple;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;
use PhpZip\Constants\ZipCompressionLevel;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
     * Bundler default configuration
     *
     * @var array
     */
    protected $_defaults = [
        'log' => true,
        'loglevel' => 5,
        'basepath' => null,
        'finder' => [
            'exclude' => [],
            'depth' => 0,
            'ignoreDotFiles' => true,
            'ignoreVCS' => true,
            'ignoreVCSIgnored' => true,
        ],
        'composer' => [
            'install' => false,
            'dev-dependencies' => false,
            'phpscoper' => false,
        ],
        'tmpdir' => null,
        'output' => 'dist',
        'clean' => true,
        'zip' => 'bundle.zip',
    ];

    /**
     * Stores Bundler configuration
     *
     * @var \Adbar\Dot
     */
    protected $_config;

    /**
     * Finder instance
     *
     * @var \Symfony\Component\Finder\Finder|null
     */
    protected $_finder = null;

    /**
     * Stores filesystem instance
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $_fs;

    /**
     * Stores Logger instance
     *
     * @var \Laminas\Log\Logger|null
     */
    protected $_logger = null;

    /**
     * Caches bundler base path
     *
     * @var string|null
     */
    protected $_basepath = null;


    /**
     * Caches bundler path for temporary files
     *
     * @var string|null
     */
    protected $_tmpdir = null;

    /**
     * Stores created temporary folders to ensure cleaning
     *
     * @var array<string>
     */
    protected $_usedTmpDirs = [];


    /**
     * Creates a bundler instance
     *
     * Bundler configuration can be provided as a full Config object or as an array
     *
     * If no base path to project root is provided, project directory will be used. Basepath should point to a
     * valid project directory. The path can be either relative to this class or absolute
     *
     * @param array $config Configuration
     */
    public function __construct($config = [])
    {
        $this->_config = new Dot($this->_defaults);
        $this->_config->mergeRecursiveDistinct($config);
        $this->loadConfigFile('composer.json', 'extra.wpbundler');
        $this->_fs = new FileSystem();
    }

    public function getVersion(): string
    {
        $composer = $this->_getAbsolutePath('composer.json', dirname(__DIR__));
        /** @var array{version:string} $data */
        $data = Factory::fromFile($composer);

        return $data['version'];
    }

    /**
     * Get bundler normalized absolute base path from where each relative path will be resolved next
     *
     * - If no basepath value is set in config, it will return project directory absolute path (home of `composer.json`)
     * - If a relative path is set in config, it will return resolved path from project directory
     * - If an absolute path is provided, it will be returned as is
     *
     * @return string
     */
    public function getBasePath(): string
    {
        if (empty($this->_basepath)) {
            /** @var string|null $basepath */
            $basepath = $this->getConfig()->get('basepath');
            $this->_basepath = $this->_getAbsolutePath($basepath, ComposerLocator::getRootPath());

            if (!is_dir($this->_basepath)) {
                throw new \RuntimeException('[WordpressBundler] Base path is not a valid directory');
            }
        }

        return $this->_basepath;
    }

    /**
     * The function `getTempDir()` returns the absolute path of the temporary directory used for bundling, using the
     * configured temporary directory in options or the system's default temporary directory if not specified.
     *
     * @return string the value of the private property `_tmpdir`, which is a string representing the
     * temporary directory path.
     */
    public function getTempDir(): string
    {
        if (!$this->_tmpdir) {
            /** @var string $tmpdir */
            $tmpdir = $this->getConfig()->get('tmpdir') ?? sys_get_temp_dir();
            $this->_tmpdir = Path::makeAbsolute(uniqid('__wpbundler_tmp_', true), $tmpdir);
        }

        return $this->_tmpdir;
    }

    /**
     * The method returns a logger object, creating it if it doesn't already exist.
     *
     * Logger is initialized with available configuration when first called.
     *
     * Logger is returned by reference, therefore it can be tweaked after its creation.
     *
     * @return \Laminas\Log\Logger an instance of the Logger.
     * @link https://docs.laminas.dev/laminas-log
     */
    public function &getLogger(): Logger
    {
        if (empty($this->_logger)) {
            /** @var int $priority */
            $priority = $this->getConfig()->get('loglevel') ?? 5;
            $writer = new Stream('php://output');
            $formatter = new Simple('%message%');
            $filter = new Priority($priority);
            $writer->setFormatter($formatter);
            $writer->addFilter($filter);
            $logger = new Logger();
            $logger->addWriter($writer);

            $this->_logger = $logger;
        }

        return $this->_logger;
    }

    /**
     * Returns the configuration of the bundler
     *
     * If manually updated, `Bundler::_reloadConfig`
     *
     * @return \Adbar\Dot
     */
    public function getConfig(): Dot
    {
        return new Dot($this->_config);
    }

    /**
     * Merge or replace current bundler config
     *
     * @param array|\Adbar\Dot $config New configuration
     * @param bool $merge If true, new config will be merged, otherwise it will replace current one
     * @return self
     */
    public function setConfig($config, bool $merge = true): self
    {
        $config = new Dot($config);

        if ($merge) {
            $this->_config->mergeRecursiveDistinct($config);
        } else {
            $this->_config = $config;
        }

        $this->_reloadConfig();

        return $this;
    }

    /**
     * Loads a configuration file into config
     *
     * @param string $path Path to file
     * @param string|null $key Key in file if object given (like JSON for instance)
     * @return void
     */
    public function loadConfigFile(string $path, ?string $key = null): void
    {
        $path = $this->_getAbsolutePath($path);
        $this->_log(7, sprintf('Loading configuration from %s', $path));

        try {
            $loaded = new Dot(Factory::fromFile($path));

            if ($key && !is_array($loaded->get($key))) {
                throw new RuntimeException(sprintf(
                    'Unable to locate key %s in %s',
                    $key,
                    $path
                ));
            }

            /** @var array $config */
            $config = $key ? $loaded->get($key) : $loaded;
            $this->_config->mergeRecursiveDistinct($config);
            $this->_reloadConfig();

            $this->_log(7, sprintf('Configuration loaded from %s', $path));
        } catch (RuntimeException $err) {
            $this->_log(7, sprintf('Unable to load configuration file : %s', $err->getMessage()));
        }
    }

    /**
     * The function saves a configuration file at the specified path, with an optional key to specify a
     * nested place in target.
     *
     * @param $path The `path` parameter is a string that represents the file path where the
     * configuration file will be saved.
     * @param $key The `key` parameter is an optional parameter that represents the key under which the
     * configuration data will be saved in the config file. If the `key` parameter is provided, the
     * configuration data will be saved under that key in the config file. If the `key` parameter is
     * not provided or is
     * @return bool a boolean value. It returns true if the configuration file is successfully saved,
     * and false if there is an error or exception during the process.
     */
    public function saveConfigFile(string $path, ?string $key = null): bool
    {
        $path = $this->_getAbsolutePath($path);

        try {
            if ($key) {
                try {
                    $config = new Dot(Factory::fromFile($path));
                } catch (RuntimeException $err) {
                    $config = new Dot();
                }

                $config->set($key, $this->getConfig());
            } else {
                $config = $this->getConfig();
            }

            Factory::toFile($path, $config->all());

            return true;
        } catch (RuntimeException $err) {
            $this->_log(3, $err->getMessage());
            return false;
        }
    }

    /**
     * The function `getFinder()` returns a Finder object that is configured based on the include and
     * exclude patterns specified in the configuration.
     *
     * @return \Symfony\Component\Finder\Finder an instance of the Finder class.
     */
    public function getFinder(): Finder
    {
        if (empty($this->_finder)) {
            $config = $this->getConfig();
            /** @var array<int|string>|int|string $depth */
            $depth = $config->get('finder.depth', 0);
            /** @var array<int,string> $exclude */
            $exclude = $config->get('finder.exclude') ?? [];
            /** @var array<int,string> $excludedPatterns */
            $excludedPatterns = array_merge($exclude, $this->_readFileAsArray('.wpignore'));
            $finder = new Finder();

            $finder
                ->ignoreDotFiles((bool)$config->get('finder.ignoreDotFiles', true))
                ->ignoreVCS((bool)$config->get('finder.ignoreVCS', true))
                ->ignoreVCSIgnored((bool)$config->get('finder.ignoreVCSIgnored', true))
                ->notPath($excludedPatterns)
                ->notName($excludedPatterns)
                ->in($this->getBasePath());

            if ($depth !== -1) {
                $finder->depth($depth);
            }

            $this->_finder = $finder;
        }

        return $this->_finder;
    }

    /**
     * The function sets the Finder object for the current instance.
     *
     * @param \Symfony\Component\Finder\Finder $finder Finder instance to replace current one
     */
    public function setFinder(Finder $finder): self
    {
        $this->_finder = $finder;

        return $this;
    }

    /**
     * The logHeader function logs the version of WordpressBundler and a separator line.
     */
    public function logHeader(): void
    {
        $version = $this->getVersion();
        $this->_log(5, PHP_EOL . 'WordpressBundler - v' . $version);
        $this->_log(5, '-------------------------');
    }

    /**
     * Returns an array of paths to copy based on configuration
     *
     * @return array<string,string>
     */
    public function getEntries(): array
    {
        $finder = $this->getFinder();
        /** @var array<int|string>|int|string $depth */
        $depth = $this->getConfig()->get('finder.depth') ?? 0;
        $iterator = $depth === -1 ? $finder->files()->getIterator() : $finder->getIterator();
        $entries = [];

        foreach ($iterator as $file) {
            $path = $file->getRealPath();

            if ($path === false) {
                continue;
            }

            $relative = $this->_getRelativePath($path);
            $parent = Path::getDirectory($relative);

            // Remove parent directory if present in list
            if (array_key_exists($parent, $entries)) {
                unset($entries[$parent]);
            }

            $entries[$relative] = Path::normalize($path);
        }

        return $entries;
    }

    /**
     * The function copies an array of entries to a specified destination, keeping track of the number
     * of directories, files, successes, failures, and processed entries.
     *
     * @param array<string,string> $entries An array containing the entries to be copied. Each entry consists of a
     * relative path as the key and the absolute path as the value. The entries can be either
     * directories or files.
     * @param string $to The "to" parameter is a string that represents the destination directory where
     * the entries will be copied to. IF relative, it will be resolved from bundler base path
     * @return array an array with the following keys and values:
     */
    public function copy(array $entries, string $to): array
    {
        $to = $this->_getAbsolutePath($to);
        $results = [
            'to' => $to,
            'dirs' => 0,
            'files' => 0,
            'failures' => 0,
            'success' => 0,
            'processed' => 0,
            'failed' => [],
        ];

        foreach ($entries as $relative => $absolute) {
            $target = $this->_getAbsolutePath($relative, $to);

            // Adds a security level to avoid processing output content if case of misconfiguration
            if (Path::isBasePath($to, $absolute)) {
                $results['failures']++;
                $results['processed']++;
                $results['failed'][$relative] = $absolute;
                continue;
            }

            if (is_dir($absolute)) {
                $this->_fs->mirror($absolute, $target);
                $results['dirs']++;
                $results['success']++;
                $results['processed']++;
                continue;
            }

            if (is_file($absolute)) {
                $this->_fs->copy($absolute, $target);
                $results['files']++;
                $results['success']++;
                $results['processed']++;
                continue;
            }

            $results['failures']++;
            $results['processed']++;
            $results['failed'][$relative] = $absolute;
        }

        return $results;
    }

    /**
     * The function installs composer dependencies in a specified working directory.
     *
     * @param string $workingdir The `workingdir` parameter is a string that represents the directory
     * where the composer.json file is located and where the composer install command will be executed.
     */
    public function install(string $workingdir): void
    {
        $composer = $this->_getAbsolutePath('composer.json', $workingdir);

        if (!is_file($composer)) {
            $this->_log(2, sprintf(
                'Composer install is requested but no composer.json have been found in %s',
                $workingdir
            ));
        }

        // Run composer install in target folder
        // Use of install ensures that composer.lock will be used to deploy dependencies used versions
        /** @var bool $dev */
        $dev = (bool)$this->getConfig()->get('composer.dev-dependencies');
        $log = (bool)$this->getConfig()->get('log');
        $cmd = "composer install --no-progress --working-dir={$workingdir} --classmap-authoritative";
        $cmd .= $dev ? ' --dev' : ' --no-dev';
        $cmd .= $log ? '' : ' --quiet';

        $result = $this->_exec($cmd);

        if ($result > 0) {
            $this->_log(2, sprintf('Unable to execute composer install at %s working dir', $workingdir));
        }
    }

    /**
     * Applies PHP-Scoper
     *
     * @param  string $from From path
     * @param  string $to   To path
     */
    public function scope(string $from, string $to): void
    {
        $log = (bool)$this->getConfig()->get('log');

        // Find out binary from main project package
        $scoper = is_file('php-scoper') ?
            'php-scoper' :
            Path::makeAbsolute('vendor/bin/php-scoper', ComposerLocator::getRootPath());

        // Handle error
        if (!is_file($scoper)) {
            $this->_log(
                2,
                'Unable to locate PHP-scoper to perform dependencies obfuscation'
            );

            return;
        }

        // Search for configuration in bundler base path then build and execute command
        $config = $this->_getAbsolutePath('scoper.inc.php');
        $hasConfig = is_file($config);
        $this->_log(6, $hasConfig ?
            sprintf('Loading %s configuration file for PHP-Scoper configuration', $config) :
            'No configuration file found. Reverting to default config');
        $cmd = "{$scoper} add-prefix -o {$to} -q -f " . (is_file($config) ? "-c {$config} " : "--no-config ") . $from;
        $result = $this->_exec($cmd);

        if ($result > 0) {
            $this->_log(2, 'Unable to apply dependency scoping. Php-scoper reports a failure');

            return;
        }

        $cmd = "composer dump-autoload --working-dir={$to} --classmap-authoritative";
        $cmd .= $log ? '' : ' --quiet';
        $result = $this->_exec($cmd);

        if ($result > 0) {
            $this->_log(4, 'Unable to dump composer autoload after scoping');

            return;
        }

        // $this->_log(5, sprintf('Dependencies scoped and autoload dumped'));
    }

    /**
     * Perform zip
     *
     * @param  string $from From path (must be a folder)
     * @param  string $to   To path (must be a valid path/archive.zip)
     */
    public function zip(string $from, string $to): void
    {
        if (is_file($to)) {
            unlink($to);
        }

        try {
            $zip = new ZipFile();
            $zip
                ->addDirRecursive($from)
                ->setCompressionLevel(ZipCompressionLevel::MAXIMUM)
                ->saveAsFile($to)
                ->close();
        } catch (ZipException $err) {
            $this->_log(3, $err->getMessage());
        }
    }

    /**
     * Creates a new bundle
     *
     * @return string Path to bundle folder
     */
    public function bundle(): string
    {
        $start = microtime(true);

        $this->logHeader();
        $config = $this->getConfig();
        $installed = (bool)$config->get('composer.install');
        $scoped = $installed && (bool)$config->get('composer.phpscoper');
        $zipped = (bool)$config->get('zip');
        /** @var string $output */
        $output = $config->get('output') ?? 'dist';
        $output = $this->_getAbsolutePath($output);
        $this->_handleDirectory($output);

        // Finding entries
        $this->_log(5, '- Extracting entries to be bundled');
        $entries = $this->getEntries();
        $this->_log(6, sprintf('  %d entries found', count($entries)));

        // Copying entries
        $to = $installed || $zipped ? $this->getTempDir() : $output;
        $this->_log(5, '- Preparing data');
        $this->_log(7, sprintf('  Copying entries to %s', $to));
        if ($to !== $output) {
            $this->_handleDirectory($to, true);
            $this->_usedTmpDirs[] = $to;
        }

        /** @var array{processed:int,dirs:int,files:int,failures:int,failed:array<string>} $results */
        $results = $this->copy($entries, $to);
        $this->_log(6, sprintf('  %d entries processed', $results['processed']));
        $this->_log(7, sprintf('  %d directories processed', $results['dirs']));
        $this->_log(7, sprintf('  %d files processed', $results['files']));
        if ($results['failures']) {
            $this->_log(4, sprintf('  Failed to copy %d entries', $results['failures']));
            foreach ($results['failed'] as $failure) {
                $this->_log(6, sprintf('  Entry failed : %s', $failure));
            }
        }

        // Composer install
        if ($installed) {
            $this->_log(5, '- Running composer install');
            $this->install($to);
            $this->_log(6, '  Install successful');
        }

        // Php-Scoper
        if ($scoped) {
            $this->_log(5, '- Obfuscating dependencies namespace');
            $from = $to;
            $to = $zipped ? $this->getTempDir() : $output;
            if ($to !== $output) {
                $this->_handleDirectory($to, true);
                $this->_usedTmpDirs[] = $to;
            }
            $this->scope($from, $to);
            $this->_log(6, '  Dependencies namespaces obfuscated');
        }

        // Zip
        if ($zipped) {
            $this->_log(5, '- Zipping bundle');
            $from = $to;
            /** @var string $zipname */
            $zipname = $config->get('zip');
            $zipname = Path::changeExtension($zipname, 'zip');
            $output = $this->_getAbsolutePath($zipname, $output);
            $this->zip($from, $output);
            $this->_log(6, '  Zip file created');
        }

        $this->_log(5, '- Cleaning temporary data');
        $removed = $this->_clearTmpDirs();
        $this->_log(6, '  Temporary data cleaned');
        $this->_log(7, $removed ?
            sprintf('  %d temporary folder(s) removed', $removed) :
            '  No temporary folders to removed');

        $end = microtime(true);

        $this->_log(5, sprintf('- Done. Your bundle is available at %s', $output));
        $this->_log(6, sprintf('  Bundle took %fs', $end - $start));

        return $output;
    }

    /**
     * The function clears temporary directories that have been used.
     *
     * @return int Number of removed directories
     */
    protected function _clearTmpDirs(): int
    {
        $removed = 0;

        foreach ($this->_usedTmpDirs as $dir) {
            if (is_dir($dir)) {
                $removed++;
                $this->_fs->remove($dir);
            }
        }

        return $removed;
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
        exec($cmd, $output, $result);

        if ($result > 0) {
            /** @var string $line */
            foreach ($output as $line) {
                $this->_log(7, $line);
            }
        }

        return $result;
    }

    /**
     * The function handles a directory by creating it if it doesn't exist, and optionally cleaning it
     * if it does.
     *
     * @param string $dir The `dir` parameter is a string that represents the directory path. It is the
     * directory that needs to be handled or created if it doesn't exist.
     * @param bool $clean A boolean flag indicating whether to clean the directory before handling it.
     * If set to true, the directory will be removed and recreated. If set to false, the bundler config
     * will be used to decide if directory should be cleaned or not.
     * @return string the directory path as a string.
     */
    protected function _handleDirectory(string $dir, bool $clean = false): string
    {
        /** @var bool $clean */
        $clean = $clean || ($this->getConfig()->get('clean') ?? true);
        $dir = $this->_getAbsolutePath($dir);

        if (!is_dir($dir)) {
            $this->_fs->mkdir($dir);
        } elseif ($clean === true) {
            $this->_fs->remove($dir);
            $this->_fs->mkdir($dir);
        }

        return $dir;
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
        $logger = $this->getLogger();
        $logEnabled = (bool)$this->getConfig()->get('log');

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
                $message = "\033[0;26m{$message}\033[37m";
                break;
            case 7:
                $message = "\033[1;34m{$message}\033[37m";
                break;
        }

        if ($logEnabled) {
            $logger->log($priority, $message);
        }

        if ($priority <= 3) {
            $logger->log(2, "Closing bundler due to error(s)");

            if (!$logEnabled) {
                $logger->log(2, "You can try to enable log at level 7 for debugging");
            }

            $this->_clearTmpDirs();

            throw new \RuntimeException("Closing bundler due to error(s)");
        }

        return $this;
    }

    /**
     * The function returns the absolute path of a given path, making it absolute if it is relative to
     * base path.
     *
     * @param $path File or directory path
     * @param $basepath The `base path` to use. If not provided, bundler base path will be used
     * @return string the absolute path of the given path.
     */
    protected function _getAbsolutePath(?string $path, ?string $basepath = null): string
    {
        $basepath = $basepath ?: $this->getBasePath();
        $path = $path ?: $basepath;

        if (Path::isRelative($path)) {
            $path = Path::makeAbsolute($path, $basepath);
        }

        return Path::canonicalize($path);
    }

    /**
     * The function returns the relative path of a given path from the given basepath.
     * If no base path is provided, bundler base path will be used.
     * If path is already relative, it will be returned unchanged.
     *
     * @param $path File or directory path
     * @param $basepath The `base path` to use. If not provided, bundler base path will be used
     * @return string the absolute path of the given path.
     */
    protected function _getRelativePath(string $path, ?string $basepath = null): string
    {
        $basepath = $basepath ?: $this->getBasePath();

        if (Path::isRelative($path)) {
            return $path;
        }

        return Path::canonicalize(Path::makeRelative($path, $basepath));
    }

    /**
     * The function reads a file and returns its contents as an array, excluding empty lines and lines
     * starting with '#' character.
     *
     * @param string $filepath The `filepath` parameter is a string that represents the path to the file
     * that needs to be read. If relative, it will be resolved from bundler basepath
     * @return array an array.
     */
    protected function _readFileAsArray(string $filepath): array
    {
        $filepath = $this->_getAbsolutePath($filepath);
        /** phpcs:disable */
        $lines = @file($filepath, FILE_SKIP_EMPTY_LINES);
        /** phpcs:enable */

        if ($lines === false) {
            return [];
        }

        return array_filter($lines, function ($line) {
            return $line[0] !== '#';
        });
    }

    /**
     * The function _reloadConfig() resets cached data based on config in order to take any change into account.
     *
     * @return self
     */
    protected function _reloadConfig(): self
    {
        $this->_logger = null;
        $this->_finder = null;
        $this->_basepath = null;
        $this->_tmpdir = null;

        return $this;
    }
}
