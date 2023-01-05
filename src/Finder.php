<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler;

/**
 * Finder will stores files to be included and excluded by parsing relative or absolute paths with or without glob patterns in them
 *
 * If a path is explicitly excluded, it cannot be included (it means, you cannot include a file or folder that is also excluded)
 * You can explicitly includes files from an excluded folder or explicitly exclude file/folder from an included folder
 */

class Finder
{
    /**
     * Stores finder base path
     *
     * @var string
     */
    protected $_basepath;

    /**
     * Stores excluded path
     *
     * @var array<string,string>
     */
    protected $_exclude = [];

    /**
     * Stores included path
     *
     * @var array<string,string>
     */
    protected $_include = [];

    /**
     * Stores relative path to remove
     *
     * @var string[]
     */
    protected $_remove = [];

    public function __construct(string $basepath)
    {
        $this->_basepath = $basepath;
    }

    /**
     * Processes a pattern in order to add results to include list
     *
     * @param string $pattern Pattern
     * @return \Lqdt\WordpressBundler\Finder
     */
    public function include(string $pattern): Finder
    {
        $paths = $this->_processPattern($pattern);

        foreach ($paths as $relative => $absolute) {
            // Avoid processing duplicates
            if (array_key_exists($relative, $this->_include)) {
                continue;
            }

            // Avoid including excluded one
            if (array_key_exists($relative, $this->_exclude)) {
                continue;
            }

            $this->_include[$relative] = $absolute;
        }

        return $this;
    }

    /**
     * Processes a pattern in order to add results to exclude list
     *
     * @param string $pattern Pattern
     * @return \Lqdt\WordpressBundler\Finder
     */
    public function exclude(string $pattern): Finder
    {
        $paths = $this->_processPattern($pattern);

        foreach ($paths as $relative => $absolute) {
            // Avoid processing duplicates
            if (array_key_exists($relative, $this->_exclude)) {
                continue;
            }

            if (array_key_exists($relative, $this->_include)) {
                // Clean if already included
                unset($this->_include[$relative]);
            } else {
                /** If there's some included parent of the path,
                 *  we must mark newly excluded as to be removed
                 */
                foreach ($this->_include as $r => $included) {
                    if (Resolver::isSubPath($absolute, $included) && !in_array($relative, $this->_remove)) {
                        $this->_remove[] = $relative;
                    }
                }
            }

            $this->_exclude[$relative] = $absolute;
        }

        return $this;
    }

    public function includeFromFile(string $path): Finder
    {
        $patterns = $this->_processFile($path);

        if (empty($patterns)) {
            throw new \TypeError('File content is empty');
        }

        return $this->includeMany($patterns);
    }

    public function includeMany(array $patterns): Finder
    {
        if (empty($patterns)) {
            throw new \TypeError('No patterns to process in collection');
        }

        foreach ($patterns as $pattern) {
            $this->include($pattern);
        }

        return $this;
    }

    public function excludeFromFile(string $path): Finder
    {
        $patterns = $this->_processFile($path);

        return $this->excludeMany($patterns);
    }

    public function excludeMany(array $patterns): Finder
    {
        foreach ($patterns as $pattern) {
            $this->exclude($pattern);
        }

        return $this;
    }

    /**
     * Returns the entries found
     *
     * @return array<string,string>
     */
    public function getEntries(): array
    {
        return $this->_include;
    }

    /**
     * Returns the entries to be removed in targte bundle
     * The path to the output folder must be passes as argument
     *
     * @return array<string,string>
     */
    public function getEntriesToRemove(string $basepath): array
    {
        return array_map(function ($path) use ($basepath) {
            return Resolver::makeAbsolute($path, $basepath);
        }, $this->_remove);
    }

    /**
     * Reads a file and returns an array of each line
     *
     * @param string $path Path to file
     * @throws \RuntimeException If missing file or read failure
     * @return string[]
     */
    protected function _processFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Unable to access file at %s', $path));
        }

        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to parse file content at %s', $path));
        }

        return $content;
    }

    /**
     * Processes a pattern and returns a list of paths
     *
     * @param string $pattern Pattern
     * @return array
     */
    protected function _processPattern(string $pattern): array
    {
        if ($pattern === '' || $pattern[0] === '#') {
            return [];
        }

        return Resolver::resolve($pattern, $this->_basepath);
    }
}
