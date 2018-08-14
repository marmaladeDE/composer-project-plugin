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

use Composer\Composer;
use Composer\Downloader\GitDownloader;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Process\Process;
use function is_array;

class ProjectPlugin implements PluginInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'installRepositories',
        ];
    }

    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public function installRepositories(Event $event)
    {
        $result = 0;

        /** @var GitDownloader $downloader */
        $downloader   = $event->getComposer()->getDownloadManager()->getDownloader('git');
        $repositories = $event->getComposer()->getConfig()->get('project-repositories');
        foreach ($repositories as $path => $repository) {
            $detailedInfo       = is_array($repository);
            $runComposerInstall = false;
            $ref                = 'master';

            if ($detailedInfo) {
                if (array_key_exists('reference', $repository)) {
                    $ref = $repository['reference'];
                }
                $repositoryUrl      = $repository['url'];
                $runComposerInstall = ($repository['composer-install'] ?? false);
            } else {
                $repositoryUrl = (string) $repository;
            }

            $pattern = "/:(?P<vendor>[^\\/]+)\\/(?P<name>.*)\\.git/";
            preg_match($pattern, $repositoryUrl, $matches);

            $package = new Package("{$matches['vendor']}/{$matches['name']}", 'dev-master', $path);
            $package->setType('library');
            $package->setSourceReference($ref);
            $package->setSourceUrl($repositoryUrl);
            $package->setSourceType('git');

            $downloader->doDownload($package, $path, $repositoryUrl);

            if ($runComposerInstall) {
                $process = new Process('composer install --ansi -n', $path, null, null, 0);
                $io      = $event->getIO();
                $process->run(
                    function ($type, $buffer) use ($io) {
                        if (Process::ERR === $type) {
                            $io->writeError($buffer, false);
                        } else {
                            $io->write($buffer, false);
                        }
                    }
                );
            }
        }

        return $result;
    }
}
