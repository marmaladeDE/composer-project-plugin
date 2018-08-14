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
use Composer\Util\ProcessExecutor;
use Marmalade\Composer\Helper\Git;
use Symfony\Component\Process\Process;
use function is_array;
use function realpath;

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

        $io = $event->getIO();

        $gitHelper = new Git(new ProcessExecutor($io));

        $io->write('Cloning defined project repositories.');

        foreach ($repositories as $path => $repository) {
            $realPath           = realpath($path);
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

            $io->write("Cloning <success>{$repositoryUrl} ({$ref})</success> into <success>{$realPath}</success>.");
            $downloader->doDownload($package, $path, $repositoryUrl);

            if ($runComposerInstall) {
                $process = new Process('composer install --ansi -n', $path, null, null, 0);
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

            $executorTimeout = ProcessExecutor::getTimeout();
            ProcessExecutor::setTimeout(0);

            $io->write('Removing <success>composer</success> remote.');
            $gitHelper->removeRemote($realPath, 'composer');
            if (isset($repository['remotes']) && is_array($repository['remotes'])) {
                $io->write('Adding configured remotes.');
                foreach ($repository['remotes'] as $name => $url) {
                    $io->write("Adding remote <success>{$name}</success> with url <success>{$url}</success>.");
                    $gitHelper->addRemote($realPath, $name, $url);
                }
            }

            ProcessExecutor::setTimeout($executorTimeout);
        }

        return $result;
    }
}
