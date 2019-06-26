<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version 0.1
 * @author  Stefan Krenz <krenz@marmalade.de>
 * @link    http://www.marmalade.de
 */

namespace Marmalade\Composer\Command\Vagrant;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Halt extends BaseCommand
{
    protected function configure()
    {
        $this->setName('vm:halt');
        $this->addArgument(
            'machines',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Name(s) of the VMs to shut down.',
            []
        );
        $this->setDescription('Shut down one or more development VMs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $cmd     = array_merge(['vagrant', 'halt', '--color'], (array) $input->getArgument('machines'));
        $process = new Process($cmd, 'vm');
        $process->setTimeout(0);
        $process->run(
            static function ($type, $buffer) use ($output, $formatter) {
                if (Process::ERR === $type) {
                    $buffer = $formatter->formatBlock($buffer, 'error');
                }

                $output->write($buffer);
            }
        );

        return $process->getExitCode();
    }

}