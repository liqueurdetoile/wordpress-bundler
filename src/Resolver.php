<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

use ComposerLocator;
use Symfony\Component\Filesystem\Path;

/**
 * Utility class to transform and resolve paths in current project
 *
 * @author Liqueur de Toile <contact@liqueurdetoile.com>
 * @copyright 2022-present Liqueur de Toile
 * @license GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
 */
class Resolver
{
    /**
     * Stores base path to resolve from
     *
     * @var string|null
     */
    protected static $_rootpath;

    /**
     * Get resolver root path. If no override is provided, it will be fetched from
     * project root. Overriden path should be an absolute path.
     *
     * @param  string|null $basepath Overrides base path if provided
     * @return string
     */
    public static function getRootPath(?string $basepath = null): string
    {
        if ($basepath === null) {
            return self::$_rootpath ?? (self::$_rootpath = self::normalize(ComposerLocator::getRootPath()));
        }

        return self::normalize($basepath);
    }

    /**
     * Makes a path absolute
     * If no base path provided, project root path will be used
     *
     * @param  string      $path     Path
     * @param  string|null $basepath Base path to use
     * @return string  Absolute path
     */
    public static function makeAbsolute(string $path, ?string $basepath = null): string
    {
        return Path::makeAbsolute($path, self::getRootPath($basepath));
    }

    /**
     * Makes a path relative
     * If no base path provided, project basepath will be used
     *
     * @param  string      $path     Path
     * @param  string|null $basepath Base path to use
     * @return string  Absolute path
     */
    public static function makeRelative(string $path, ?string $basepath = null): string
    {
        return Path::makeRelative($path, self::getRootPath($basepath));
    }

    /**
     * Normalize a path
     *
     * @param  string $path Path to normalize
     * @return string Normalized path
     */
    public static function normalize(string $path): string
    {
        return Path::normalize($path);
    }

    /**
     * Resolves a path/glob, optionnally from a given base path for relative ones
     *
     * The input can be either :
     * - an absolute path
     * - a relative path
     *
     * Relative paths will be evaluated from the provided base path as second argument or project root if not provided
     * Glob patterns can be used as path
     *
     * If path does not match any file or folder, an empty array will be returned
     *
     * The key of items are the relative path to basepath and the value is the absolute path
     *
     * Each folder will be explored recursively to get each file
     *
     * @param  string      $path         Path to resolve
     * @param  string|null $basepath     Base path to resolve from
     * @param  string|null $originalpath Original path to resolve from to build valid relative file list
     * @return array<string, string> Resolved path(s) or false in case of error
     */
    public static function resolve(string $path, ?string $basepath = null, ?string $originalpath = null): array
    {
        $basepath = self::getRootPath($basepath);

        if (!is_dir($basepath)) {
            throw new \RuntimeException('[WordpressBundler] Resolver is unable to locate base folder');
        }

        if ($originalpath === null) {
            $originalpath = $basepath;
        }

        $path = self::makeAbsolute($path, $basepath);
        $paths = [];
        $ret =  [];

        if (is_file($path)) {
            $paths[self::makeRelative($path, $originalpath)] = $path;
        } elseif (is_dir($path)) {
            $paths += self::resolve('*', $path, $originalpath);
        } else {
            $gpaths = glob($path) ?: [];
            // Resolve each returned path in order to explore subdirectories
            foreach ($gpaths as $p) {
                $paths += self::resolve($p, $basepath, $originalpath);
            }
        }

        return $paths;
    }

    /**
     * Resolves an array of paths
     *
     * @param  array<string> $paths    Paths to resolve
     * @param  string|null   $basepath Base path to resolve from
     * @return array<string, string> Resolved path(s) or false in case of error
     * @see Resolver::resolve
     */
    public static function resolveMany(array $paths, ?string $basepath = null): array
    {
        $ret = [];

        foreach ($paths as $path) {
            $parsed = self::resolve($path, $basepath);

            $ret += $parsed;
        }

        return $ret;
    }
}
