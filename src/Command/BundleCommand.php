<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Command;

use Lqdt\WordpressBundler\Bundler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Bundle console command
 *
 * @author Liqueur de Toile <contact@liqueurdetoile.com>
 * @copyright 2022-present Liqueur de Toile
 * @license GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
 */
class BundleCommand extends Command
{
    /**
     * Command name
     *
     * @var null|string
     */
    protected static $defaultName = 'bundle';

    /**
     * Command description
     *
     * @var null|string
     */
    protected static $defaultDescription = 'Creates a new bundle';

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setHelp('Creates a new bundle')
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Additional(s) configuration file(s) to load'
            )
            ->addOption(
                'log',
                'l',
                InputOption::VALUE_REQUIRED,
                'Set log level (from 0, only crit to 7 full debug)',
                5
            )
            ->addOption(
                'dry',
                null,
                null,
                'Dry run will perform full bundle to ensure that all is OK but will remove it at the end'
            )
            ->addOption(
                'show-entries-only',
                null,
                null,
                'Bundler will only list entries to be bundled based on its configuration'
            );
    }

    /**
     * Command executor
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  Input interface
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output interface
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $configs */
        $configs = $input->getOption('config');
        /** @var string $loglevel */
        $loglevel = $input->getOption('log');
        /** @var bool $dry */
        $dry = $input->getOption('dry');
        /** @var bool $entries */
        $entries = $input->getOption('show-entries-only');

        $config = $loglevel > 0 ? [
            'log' => true,
            'loglevel' => $loglevel,
        ] : [];

        $bundler = new Bundler($config);

        foreach ($configs as $config) {
            $bundler->loadConfigFile($config);
        }

        if ($entries) {
            return $this->showEntriesOnly($bundler);
        }

        $output = $bundler->bundle();

        if ($dry) {
            $fs = new Filesystem();
            $fs->remove(is_file($output) ? Path::getDirectory($output) : $output);
        }

        return 0;
    }

    /**
     * The function "pad" takes a string and an indent value, and returns the string padded with spaces
     * on the left side to match the specified indent.
     *
     * @param string $str The "str" parameter is a string that you want to pad with spaces on the left
     * side.
     * @param int $indent The "indent" parameter is an integer that specifies the number of spaces to
     * add before the string. It determines the amount of indentation for the string.
     * @return string a string.
     */
    protected function pad(string $str, int $indent): string
    {
        return str_pad('' . $str, strlen($str) + $indent + 0, ' ', STR_PAD_LEFT);
    }

    protected function showEntriesOnly(Bundler $bundler): int
    {
        $bundler
            ->setConfig([
                'log' => true,
                'loglevel' => 5,
            ])
            ->logHeader();

        $entries = $bundler->getEntries();
        $indent = 2;
        $base = $bundler->getBasePath() . '/';
        $current = $base;
        echo sprintf('%s' . PHP_EOL, $current);

        foreach ($entries as $entry) {
            $parent = Path::getDirectory($entry) . '/';
            if ($parent !== $current) {
                if (Path::isBasePath($current, $parent)) {
                    $parsed = str_replace($current, '', $parent);
                    $current = $parent;
                    echo sprintf('%s' . PHP_EOL, $this->pad($parsed, $indent));
                    $indent += 2;
                } else {
                    $parsed = str_replace($base, '', $parent);
                    $parts = explode('/', $parsed);
                    $current = $parent;
                    $indent = 0;
                    foreach ($parts as $folder) {
                        $indent += 2;
                        if ($folder) {
                            echo sprintf('%s' . PHP_EOL, $this->pad($folder . '/', $indent));
                        }
                    }
                }
            }
            $entry = str_replace($current, '', $entry);
            echo sprintf((is_dir($entry) ? '%s/**/*' : '%s') . PHP_EOL, $this->pad($entry, $indent));
        }

        return 0;
    }
}
