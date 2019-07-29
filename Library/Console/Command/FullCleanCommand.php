<?php

/*
 * This file is part of the Zephir.
 *
 * (c) Zephir Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Zephir\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zephir\Exception\FileSystemException;
use function Zephir\is_windows;

/**
 * Zephir\Console\Command\FullCleanCommand.
 *
 * Cleans any object files created by the extension (including files generated by phpize).
 */
final class FullCleanCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('fullclean')
            ->setDescription('Cleans any object files created by the extension (including files generated by phpize)')
            ->setHelp(sprintf('%s.', $this->getDescription()));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('clean');
        $arguments = ['command' => 'install'];

        $io = new SymfonyStyle($input, $output);

        /*
         * TODO: Do we need a batch file for Windows like "clean" as used below?
         * TODO: The 'clean' file contains duplicated commands
         */
        try {
            if (0 === $command->run(new ArrayInput($arguments), $output)) {
                if (is_windows()) {
                    system('cd ext && phpize --clean');
                } else {
                    system('cd ext && phpize --clean > /dev/null');
                    system('cd ext && ./clean > /dev/null');
                }
            }
        } catch (FileSystemException $e) {
            $io->error(
                sprintf(
                    "For reasons beyond Zephir's control, a filesystem error has occurred. ".
                    'Please note: On Linux/Unix systems the current user must have the delete and execute '.
                    'permissions on the internal cache directory,  For more information see chmod(1) and chown(1). '.
                    'System error was: %s',
                    $e->getMessage()
                )
            );

            return 1;
        }

        return 0;
    }
}