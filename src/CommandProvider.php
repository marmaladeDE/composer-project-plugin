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

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [
            new VagrantUp(),
            new VagrantHalt(),
            new VagrantReload(),
            new VagrantRsync(),
        ];
    }

}
