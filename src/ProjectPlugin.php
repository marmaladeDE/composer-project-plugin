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
use function dirname;
use Marmalade\Composer\Helper\Git;
use function ob_get_clean;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;
use function file_exists;
use function is_array;
use function preg_match;

class ProjectPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_CREATE_PROJECT_CMD => ['installRepositories', 'setupDockerCompose'],
        ];
    }

    /**
     * @return array|string[]
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }

    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @param Event $event
     *
     * @return int
     */
    public function installRepositories(Event $event): int
    {
        $repositories = $event->getComposer()->getConfig()->get('project-repositories');

        if (!is_array($repositories)) {
            return 0;
        }

        $result = 0;

        /** @var GitDownloader $downloader */
        $downloader = $event->getComposer()->getDownloadManager()->getDownloader('git');
        $io         = $event->getIO();
        $gitHelper  = new Git(new ProcessExecutor($io));

        foreach ($repositories as $path => $repository) {
            $detailedInfo = is_array($repository);
            $ref          = 'master';

            if ($detailedInfo) {
                if (array_key_exists('reference', $repository)) {
                    $ref = $repository['reference'];
                }
                $repositoryUrl = $repository['url'];
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

            if ($repository['run-composer'] ?? true) {
                $this->installComposerDependencies($io, $path);
            }

            if ($repository['run-npm'] ?? true) {
                $this->installNpmDependencies($io, $path);
            }

            ProcessExecutor::setTimeout($executorTimeout);
        }

        return $result;
    }

    /**
     * @param IOInterface $io
     * @param             $path
     */
    protected function installComposerDependencies(IOInterface $io, $path): void
    {
        if (file_exists("{$path}/composer.json")) {
            $io->write('Found <comment>composer.json</comment>, executing <info>composer install</info>.');
            $process = new Process(['composer', 'install', '--ansi', '-n'], $path, null, null, 0);
            $process->run(
                static function ($type, $buffer) use ($io) {
                    if (Process::ERR === $type) {
                        $io->writeError($buffer, false);
                    } else {
                        $io->write($buffer, false);
                    }
                }
            );
        }
    }

    /**
     * @param IOInterface $io
     * @param             $path
     */
    protected function installNpmDependencies(IOInterface $io, $path): void
    {
        if (file_exists("{$path}/package.json")) {
            $io->write('Found <comment>package.json</comment>, executing <info>npm install</info>.');
            $checkProc = new Process(['npm', 'help']);
            $checkProc->mustRun();
            $npmCommand = preg_match('/\s+ci,/', $checkProc->getOutput()) ? 'ci' : 'install';

            $process = new Process(['npm', $npmCommand, '--ansi', '-n'], $path, null, null, 0);
            $process->run(
                static function ($type, $buffer) use ($io) {
                    if (Process::ERR === $type) {
                        $io->writeError($buffer, false);
                    } else {
                        $io->write($buffer, false);
                    }
                }
            );
        }
    }

    /**
     * @param Event $event
     *
     * @return int
     */
    public function setupDockerCompose(Event $event): int
    {
        if (!file_exists('docker-compose.yml')) {
            return 0;
        }

        $io = $event->getIO();

        $path = dirname($event->getComposer()->getConfig()->get('vendor-dir'));

        $os = strtolower(php_uname('s'));
        $osSpecificComposeFile = "{$path}/docker-compose.{$os}.yml";
        if (file_exists($osSpecificComposeFile)) {
            $targetFile = "{$path}/docker-compose.override.yml";
            $io->write(
                "Copy <comment>{$osSpecificComposeFile}</comment> to <comment>{$targetFile}</comment>"
            );
            copy($osSpecificComposeFile, $targetFile);
        }

        if (file_exists("{$path}/.env.dist") && !file_exists("{$path}/.env")) {
            $dotEnv = new Dotenv();
            $dotEnv->load("{$path}/.env");

            $fp = fopen("{$path}/.env", 'wb+');
            foreach (explode(',', $_ENV['SYMFONY_DOTENV_VARS']) as $var) {
                fwrite($fp, "{$var}={$_ENV[$var]}\n");
            }
            fclose($fp);
        }

        return 0;
    }
}
