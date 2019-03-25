<?php

declare(strict_types = 1);

namespace EcEuropa\Toolkit\TaskRunner\Commands;

use OpenEuropa\TaskRunner\Commands\AbstractCommands;
use OpenEuropa\TaskRunner\Tasks as TaskRunnerTasks;
use Symfony\Component\Console\Input\InputOption;

/**
 * Provides commands to build a site for development and a production artifact.
 */
class BuildCommands extends AbstractCommands {

  use TaskRunnerTasks\CollectionFactory\loadTasks;

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFile() {
    return __DIR__ . '/../../../config/commands/build.yml';
  }

  /**
   * Build the distribution package.
   *
   * This will create the distribution package intended to be deployed.
   * The folder structure will match the following:
   *
   * - /dist
   * - /dist/composer.json
   * - /dist/composer.lock
   * - /dist/web
   * - /dist/vendor
   * - /dist/config
   *
   * @param array $options
   *   Command options.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   Collection builder.
   *
   * @command toolkit:build-dist
   *
   * @aliases tbd
   *
   * @option root Drupal root.
   */
  public function buildDist(array $options = [
    'root' => InputOption::VALUE_REQUIRED,
  ]) {
    $tasks = [];

    // Reset dist folder and copy required files.
    $tasks[] = $this->taskFilesystemStack()
      ->remove('./dist')
      ->mkdir('./dist')
      ->copy('./composer.json', './dist/composer.json')
      ->copy('./composer.lock', './dist/composer.lock');

    // Copy site configuration.
    $tasks[] = $this->taskCopyDir(['./config' => './dist/config']);

    // Run production-friendly "composer install" packages and setup the site.
    $tasks[] = $this->taskExecStack()
      ->stopOnFail()
      ->exec('composer install --no-dev --optimize-autoloader --working-dir=dist')
      ->exec('./vendor/bin/run drupal:settings-setup --root=' . $options['root']);

    // Collect and execute list of commands set on local runner.yml.
    $commands = $this->getConfig()->get("toolkit.build.dist.commands");
    $tasks[] = $this->taskCollectionFactory($commands);

    // Build and return task collection.
    return $this->collectionBuilder()->addTaskList($tasks);
  }

  /**
   * Build site for local development.
   *
   * @param array $options
   *   Command options.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   Collection builder.
   *
   * @command toolkit:build-dev
   *
   * @aliases tbd
   *
   * @option root Drupal root.
   */
  public function buildDev(array $options = [
    'root' => InputOption::VALUE_REQUIRED,
  ]) {
    $tasks = [];

    // Run site setup.
    $tasks[] = $this->taskExecStack()
      ->stopOnFail()
      ->exec('./vendor/bin/run drupal:settings-setup --root=' . $options['root']);

    // Collect and execute list of commands set on local runner.yml.
    $commands = $this->getConfig()->get("toolkit.build.dev.commands");
    $tasks[] = $this->taskCollectionFactory($commands);

    // Build and return task collection.
    return $this->collectionBuilder()->addTaskList($tasks);
  }

}
