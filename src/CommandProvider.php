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

namespace Marmalade\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Marmalade\Composer\Command\VagrantHalt;
use Marmalade\Composer\Command\VagrantReload;
use Marmalade\Composer\Command\VagrantRsync;
use Marmalade\Composer\Command\VagrantUp;
use function array_merge;
use function dirname;
use function file_exists;

class CommandProvider implements CommandProviderCapability
{
    private $hasVm;

    private $hasDocker;

    public function __construct($args)
    {
        ['composer' => $composer, 'io' => $io, 'plugin' => $plugin] = $args;
        $projectRoot = dirname($composer->getConfig()->get('vendor-dir'));

        $this->hasVm = file_exists("{$projectRoot}/vm/Vagrantfile");

        $this->hasDocker = file_exists("{$projectRoot}/docker-compose.yml");
        $this->hasDocker = $this->hasDocker || file_exists("{$projectRoot}/docker-compose.yaml");
    }

    public function getCommands()
    {
        return array_merge($this->getVmCommands(), $this->getDockerCommands());
    }

    private function getVmCommands()
    {
        return $this->hasVm ? [
            new VagrantUp(),
            new VagrantHalt(),
            new VagrantReload(),
            new VagrantRsync(),
        ] : [];
    }

    private function getDockerCommands()
    {
        return $this->hasDocker ? [
            // TODO: create commands for docker.
        ] : [];
    }
}
