<?php

use Composer\Composer;

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
use Marmalade\Composer\Command\Vagrant;
use Marmalade\Composer\Command\DockerCompose;
use function array_merge;
use function dirname;
use function file_exists;

class CommandProvider implements CommandProviderCapability
{
    private $hasVm;

    private $hasDocker;

    public function __construct($args)
    {
        /** @var Composer $composer **/
        ['composer' => $composer] = $args;
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
            new Vagrant\Up(),
            new Vagrant\Halt(),
            new Vagrant\Reload(),
            new Vagrant\Rsync(),
        ] : [];
    }

    private function getDockerCommands()
    {
        return $this->hasDocker ? [
            new DockerCompose\Up(),
            new DockerCompose\Down(),
            new DockerCompose\Restart(),
        ] : [];
    }
}
