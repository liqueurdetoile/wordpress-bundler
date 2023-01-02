<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Command;

use Lqdt\WordpressBundler\Bundler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        /** @var array $config */
        $config = $input->getOption('config');
        $bundler = new Bundler(['log' => true]);
        $bundler->bundle([
            'config' => $config,
        ]);

        return $bundler->getResult();
    }
}
