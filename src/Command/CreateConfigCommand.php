<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Command;

use Lqdt\WordpressBundler\Bundler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create config console command
 *
 * @author Liqueur de Toile <contact@liqueurdetoile.com>
 * @copyright 2022-present Liqueur de Toile
 * @license GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
 */
class CreateConfigCommand extends Command
{
    /**
     * Command name
     *
     * @var null|string
     */
    protected static $defaultName = 'init';

    /**
     * Command description
     *
     * @var null|string
     */
    protected static $defaultDescription = 'Generates a default configuration in target file or composer.json.';

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setHelp(
                'Generates a default configuration in target file or composer.json.' . PHP_EOL .
                'Config can optionally be stored in a key of the target file' . PHP_EOL .
                'If file already exists, it wil be overwritten.' .
                ' If no target file is provided, key extra.wpbundler in project composer.json will be used'
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_OPTIONAL,
                'Target file. Relative paths will be resolved from project root folder.' .
                ' Supported format : PHP, JSON, INI, YAML (with PECL extension installed)',
            )
            ->addOption(
                'key',
                'k',
                InputOption::VALUE_OPTIONAL,
                'Nest configuration in a key of the target file data. Deep key wan be provided in dotted notation',
            )
            ->addOption(
                'dry',
                null,
                null,
                'Dry run will perform full bundle to ensure that all is OK but will remove it at the end'
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
        /** @var string|null $target */
        $target = $input->getOption('target');
        /** @var string|null $key */
        $key = $input->getOption('key');

        if ($target === null) {
            $target = 'composer.json';
            $key = 'extra.wpbundler';
        }

        $bundler = new Bundler();

        return $bundler->saveConfigFile($target, $key) ? 0 : 1;
    }
}
