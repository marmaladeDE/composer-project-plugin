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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VagrantRsync extends BaseCommand
{
    protected function configure()
    {
        $this->setName('v:rsync');
        $this->setDescription('Start automatic Rsync for one or more development VMs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('v:rsync');
    }

}
