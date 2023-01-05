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
     * Get resolver root path
     *
     * This root path will be used by resolver to build absolute path.
     * If no path is provided as argument, the project root path will be returned.
     * If the provided path is relative, it will be made absolute from the root of the project, otherwise it will be normalized and returned
     *
     * @param  string|null $rootpath Overrides base path if provided
     * @throws \Lqdt\WordpressBundler\Exception\InvalidRootPathResolverException If path doesn't match a valid folder
     * @return string
     */
    public static function getRootPath(?string $rootpath = null): string
    {
        if (empty(self::$_rootpath)) {
            self::$_rootpath = self::normalize(ComposerLocator::getRootPath());
        }

        if ($rootpath === null) {
            return self::$_rootpath;
        }

        $rootpath = Path::makeAbsolute($rootpath, self::$_rootpath);

        return self::normalize($rootpath);
    }

    /**
     * Checks that a path is a subpath of another
     *
     * @param string $path Path to check
     * @param string $basepath Path to compare with
     * @return bool
     */
    public static function isSubPath(string $path, string $basepath): bool
    {
        return Path::isBasePath($basepath, $path);
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
     * @param  string      $path         Path to resolve
     * @param  string|null $basepath     Base path to resolve from
     * @return array<string,string> Resolved path(s) or false in case of error
     */
    public static function resolve(string $path, ?string $basepath = null): array
    {
        $basepath = self::getRootPath($basepath);

        if (!is_dir($basepath)) {
            throw new \RuntimeException('[WordpressBundler] Resolver is unable to locate base folder');
        }

        $path = self::makeAbsolute($path, $basepath);

        if (is_file($path)) {
            return [self::makeRelative($path, $basepath) => $path];
        }

        if (is_dir($path)) {
            return [self::makeRelative($path, $basepath) => $path];
        }

        $paths = glob($path) ?: [];

        return self::resolveMany($paths, $basepath);
    }

    /**
     * Resolves an array of paths
     *
     * @param  array<string> $paths    Patterns to resolve
     * @param  string|null   $basepath Base path to resolve from
     * @return array<string,string>, string> Resolved path(s) or false in case of error
     * @see Resolver::resolve
     */
    public static function resolveMany(array $paths, ?string $basepath = null): array
    {
        $ret = [];

        foreach ($paths as $path) {
            $ret += self::resolve($path, $basepath);
        }

        return $ret;
    }
}
