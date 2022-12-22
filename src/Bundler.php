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
     * Bundler default configuration
     *
     * Default configuration should be fine for most cases
     * as it bundles a standalone package
     *
     * @var array
     */
    protected $_defaults = [
      'debug' => false,
      'clean' => true,
      'keep' => [
        'dependencies' => true,
        'dev-dependencies' => false,
      ],
      'include' => [
        '*.json',
        '*.php',
        '*.css',
        'assets',
        'inc',
        'languages',
        'template-parts',
      ],
      'exclude' => [],
      'output' => 'dist',
      'php-scoper' => false,
      'zip' => 'bundle',
    ];

    /**
     * Stores Bundler configuration
     *
     * @var \Lqdt\WordpressBundler\Config
     */
    protected $_config;

    /**
     * Stores bundling basepath
     *
     * @var string|null
     */
    protected $_basepath;

    /**
     * Creates a bundler instance
     *
     * If no path to project root is provided, root project directory will be used. Basepath should point to a
     * valid project directory
     * The path can be either relative to this class or absolute
     *
     * Custom configuration can   also be provided. In that case, configuration in `composer.json` will be overriden.
     *
     * @param array       $overrides Configuration overrides
     * @param string|null $basepath  Path to folder holding composer.json with bundler config
     */
    public function __construct(array $overrides = [], ?string $basepath = null)
    {
        $this->_config = new Config($this->_defaults, $overrides);
        $this->_basepath = $basepath;

        try {
            $this->_config->load('extra.bundler', $basepath);
        } catch (\RuntimeException $err) {
            // Logger::get()->notice('No configuration found. Using defaults.');
          // Do nothing as we will use default values and/or overrides in that case
        }
    }

    /**
     * Creates a new bundle
     *
     * @return string Path to bundle folder
     */
    public function bundle(): string
    {
        $fs = new FileSystem();

        $useTmpFolder = $this->_config->get('php-scoper') || $this->_config->get('zip');

        // Handles directories
        $output = $this->_handleDirectory(
            $this->_config->getString('output'),
            $this->_config->getBoolean('clean'),
            $fs
        );
        $tmp = $this->_handleDirectory(Resolver::makeAbsolute('__wpbundler_tmp', sys_get_temp_dir()), true, $fs);
        $scoped = $this->_handleDirectory(Resolver::makeAbsolute('__wpbundler_scoped', sys_get_temp_dir()), true, $fs);

        // Export files and structures
        $target = $this->export($useTmpFolder ? $tmp : $output, $fs);

        // Apply php-scoper
        if ($this->_config->get('php-scoper')) {
            $target = $this->scope($target, $this->_config->get('zip') ? $scoped : $output);
        }

        // @todo Copy wordpress informations

        // Zip
        if ($this->_config->get('zip')) {
            $target = $this->zip($target, Resolver::normalize("{$output}/{$this->_config->get('zip')}.zip"));
        }

        // Clear temporary folders
        $fs->remove($scoped);
        $fs->remove($tmp);

        return $target;
    }

    /**
     * Copy entries to a target folder and install the dependencies accordingly to configuration
     *
     * @param  string                                   $to Target path
     * @param \Symfony\Component\Filesystem\Filesystem $fs Filesystem instance
     * @return string target path
     */
    public function export(string $to, Filesystem $fs): string
    {
        $entries = $this->getEntries();

        // Ensure composer.json will be imported if available
        $composer = Resolver::makeAbsolute('composer.json', $this->_basepath);
        $entries[Resolver::makeRelative('composer.json', $this->_basepath)] = $composer;

        foreach ($entries as $rpath => $fpath) {
            $tpath = Resolver::makeAbsolute($rpath, $to);

            if (is_dir($fpath)) {
                $fs->mirror($fpath, Resolver::makeAbsolute($rpath, $to));
                continue;
            }

            if (is_file($fpath)) {
                $fs->copy($fpath, Resolver::makeAbsolute($rpath, $to));
            }
        }

        // No composer available
        if (!is_file($composer)) {
            return $to;
        }

        // Update composer.json based on config
        $composer = Resolver::makeAbsolute('composer.json', $to);
        $content = (array)json_decode(file_get_contents($composer) ?: "{}", true, 512, JSON_THROW_ON_ERROR);
        $content = $this->_handleComposerDependencies($content);
        file_put_contents($composer, json_encode($content, JSON_PRETTY_PRINT));

        // Run composer install in target folder
        exec("composer install --no-progress --working-dir={$to} --classmap-authoritative --quiet");

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
        $scoper = Resolver::makeAbsolute('vendor/bin/php-scoper', $this->_basepath);
        if (!is_file($scoper)) {
            $scoper = Resolver::makeAbsolute('vendor/bin/php-scoper');
            if (!is_file($scoper)) {
                throw new \RuntimeException(
                    '[WordpressBundler] Dependencies scoping is required but unable to locate PHP-Scoper bin'
                );
            }
        }

        $config = Resolver::makeAbsolute('scoper.inc.php', $this->_basepath);
        $cmd = "{$scoper} add-prefix {$from} -o {$to} -f";
        $cmd .= is_file($config) ? " -c {$config}" : " --no-config";

        exec($cmd);
        exec("composer dump-autoload --working-dir={$to} --classmap-authoritative --quiet");

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
        $zip = new ZipFile();
        $zip
          ->addDirRecursive($from)
          ->setCompressionLevel(ZipCompressionLevel::MAXIMUM)
          ->saveAsFile($to)
          ->close();

        return $to;
    }

    /**
     * Returns the entries to be copied based on configuration
     *
     * Returnds entries will be split between files and folders keys
     *
     * @return array<string>
     */
    public function getEntries(): array
    {
        $excluded = Resolver::resolveMany($this->_config->get('exclude'), $this->_basepath);
        $included = Resolver::resolveMany($this->_config->get('include'), $this->_basepath);
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
     * Process composr.json content and updates dependencies and dev dependencies based on configuration
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
     * Handles creation or cleaning of a directory
     *
     * @param  string                                   $dir   Directory path
     * @param  boolean                                  $clean Clean flag
     * @param \Symfony\Component\Filesystem\Filesystem $fs    Filesystem instance
     * @return string  Aboslute path to directory
     */
    protected function _handleDirectory(string $dir, bool $clean, Filesystem $fs): string
    {
        $dir = Resolver::makeAbsolute($dir, $this->_basepath);

        if (!is_dir($dir)) {
            $fs->mkdir($dir);
        } elseif ($clean === true) {
            $fs->remove($dir);
            $fs->mkdir($dir);
        }

        return $dir;
    }
}
