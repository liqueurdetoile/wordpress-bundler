<?php
declare(strict_types=1);

namespace Lqdt\WordpressBundler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    protected static $defaultDescription = 'Create a new bundle';

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setHelp(
                'Create a new bundle based on stored configuration. ' .
                'Config can be overriden by providing additional parameters'
            );
    }

    /**
     * Command executor
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  Input interface
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output interface
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $output->writeln('AH ah');

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
