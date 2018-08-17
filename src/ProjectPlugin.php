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
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;
use function file_exists;
use Marmalade\Composer\Helper\Git;
use Symfony\Component\Process\Process;
use function is_array;
use function realpath;

class ProjectPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'installRepositories',
        ];
    }

    public function getCapabilities()
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
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

        foreach ($repositories as $path => $repository) {
            $detailedInfo       = is_array($repository);
            $ref                = 'master';

            if ($detailedInfo) {
                if (array_key_exists('reference', $repository)) {
                    $ref = $repository['reference'];
                }
                $repositoryUrl      = $repository['url'];
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

            $io->write("Cloning <info>{$repositoryUrl}</info> (<comment>{$ref}</comment>) into <info>{$path}</info>.");
            $downloader->doDownload($package, $path, $repositoryUrl);

            $io->write('Updating repository.');
            $gitHelper->fetchAll($path);
            $gitHelper->pull($path);

            $executorTimeout = ProcessExecutor::getTimeout();
            ProcessExecutor::setTimeout(0);

            $io->write("Removing remote <comment>composer</comment> from <info>{$path}</info>.");
            $gitHelper->removeRemote($path, 'composer');
            if (isset($repository['remotes']) && is_array($repository['remotes'])) {
                foreach ($repository['remotes'] as $name => $url) {
                    $io->write("Adding remote <comment>{$name}</comment> with url <info>{$url}</info>.");
                    $gitHelper->addRemote($path, $name, $url);
                }
            }

            $runComposer = $repository['run-composer'] ?? true;
            if ($runComposer && file_exists("{$path}/composer.json")) {
                $io->write('Found <comment>composer.json</comment>, executing <info>composer install</info>.');
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

            $runNpm = $repository['run-npm'] ?? true;
            if ($runNpm && file_exists("{$path}/package.json")) {
                $io->write('Found <comment>package.json</comment>, executing <info>npm install</info>.');
                $process = new Process('npm install --ansi -n', $path, null, null, 0);
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

            ProcessExecutor::setTimeout($executorTimeout);
        }

        return $result;
    }
}
