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

namespace Marmalade\Composer\Command;

use Composer\Command\BaseCommand;
use Composer\Util\ProcessExecutor;
use function implode;
use function ob_get_clean;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_combine;
use function array_map;
use function count;
use function explode;
use Symfony\Component\Process\Process;

class VagrantRsync extends BaseCommand
{
    private static $statusKeys = ['time', 'machine', 'type', 'value', 'message'];

    protected function configure()
    {
        $this->setName('vm:rsync');
        $this->setDescription('Start automatic Rsync for one or more development VMs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $executor = new ProcessExecutor($this->getIO());
        $executor->execute('vagrant status --machine-readable', $statusOutput, 'vm');
        $lines = explode("\n", trim($statusOutput));

        $keys     = self::$statusKeys;
        $keyCount = count($keys);
        $status   = array_map(
            function ($element) use ($keys, $keyCount) {
                $values = array_pad(explode(',', $element, 5), $keyCount, '');

                return array_combine($keys, $values);
            },
            $lines
        );

        $rawRunningMachines = array_filter(
            $status,
            function ($element) {
                return $element['type'] === 'state' && $element['value'] === 'running';
            }
        );

        $runningMachines = array_map(
            function ($element) {
                return $element['machine'];
            },
            $rawRunningMachines
        );

        if (0 === count($runningMachines)) {
            $output->writeln('No machines are running!');
            return 0;
        }

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $process = new Process(sprintf('vagrant rsync-auto --color %s', implode(' ', $runningMachines)), 'vm');
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) use ($output, $formatter) {
            if (Process::ERR === $type) {
                $buffer = $formatter->formatBlock($buffer, 'error');
            }

            $output->write($buffer);
        });

        return $process->getExitCode();
    }

}
