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

namespace Marmalade\Composer\Command\DockerCompose;

use function array_merge;
use Composer\Command\BaseCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Up extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('dc:up')
            ->setDescription('Starts one or more docker-compose services.')
            ->addArgument(
                'machines',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Name(s) of the services to restart.',
                []
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $cmd = sprintf('docker-compose up -d %s', implode(' ', $input->getArgument('machines')));
        $process = new Process($cmd);
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
