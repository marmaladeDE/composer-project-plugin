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

namespace Marmalade\Composer\Helper;

use Composer\Util\ProcessExecutor;

class Git
{
    private $executor;

    public function __construct(ProcessExecutor $executor)
    {
        $this->executor = $executor;
    }

    public function removeRemote($path, $name, &$output = null)
    {
        return $this->executor->execute("git remote remove {$name}", $output, $path);
    }

    public function addRemote($path, $name, $url, &$output = null)
    {
        return $this->executor->execute("git remote add {$name} {$url}", $output, $path);
    }

    public function fetchAll($path, &$output = null)
    {
        return $this->executor->execute('git fetch --all', $output, $path);
    }

    public function pull($path, &$output = null)
    {
        return $this->executor->execute('git pull', $output, $path);
    }
}
