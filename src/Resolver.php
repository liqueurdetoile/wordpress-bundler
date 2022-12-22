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
    protected static $_basepath;

    /**
     * Get resolver base path and caches project root
     *
     * @param  string|null $basepath Overrides base path if provided
     * @return string
     */
    public static function getBasePath(?string $basepath = null): string
    {
        if ($basepath === null) {
            return self::$_basepath ?? (self::$_basepath = self::normalize(ComposerLocator::getRootPath()));
        }

        return self::normalize($basepath);
    }

    /**
     * Makes a path absolute
     * If no base path provided, project basepath will be used
     *
     * @param  string      $path     Path
     * @param  string|null $basepath Base path to use
     * @return string  Absolute path
     */
    public static function makeAbsolute(string $path, ?string $basepath = null): string
    {
        return Path::makeAbsolute($path, self::getBasePath($basepath));
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
        return Path::makeRelative($path, self::getBasePath($basepath));
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
     * @param  string      $path     Path to resolve
     * @param  string|null $basepath Base path to resolve from
     * @return array<string, string> Resolved path(s) or false in case of error
     */
    public static function resolve(string $path, ?string $basepath = null): array
    {
        $basepath = self::getBasePath($basepath);

        if (!is_dir($basepath)) {
            throw new \RuntimeException('[WordpressBundler] Resolver is unable to locate base folder');
        }

        $path = self::makeAbsolute($path, $basepath);
        $ret =  [];

        if (is_file($path) || is_dir($path)) {
            $paths = [$path];
        } else {
            $paths = glob($path) ?: [];
        }

        foreach ($paths as $path) {
            $ret[self::makeRelative($path, $basepath)] = $path;
        }

        return $ret;
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
