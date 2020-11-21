<?php

/**
 * @file
 * Feature context for testing Behat-steps traits for Drupal 8.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 *
 * However, consumer sites can use this file as an example of traits inclusion.
 * The usage of these traits can be seen in *.feature files.
 */

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Mink\Driver\Selenium2Driver;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\D8\BigPipeTrait;
use IntegratedExperts\BehatSteps\D8\ContentTrait;
use IntegratedExperts\BehatSteps\D8\UserTrait;
use IntegratedExperts\BehatSteps\D8\WatchdogTrait;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContextD8 extends DrupalContext {

  use BigPipeTrait;
  use ContentTrait;
  use FieldTrait;
  use LinkTrait;
  use PathTrait;
  use ResponseTrait;
  use UserTrait;
  use WatchdogTrait;

  /**
   * @Then user :name does not exists
   */
  public function userDoesNotExist($name) {
    // We need to check that user was removed from both DB and test variables.
    $user = user_load($name);

    if ($user) {
      throw new \Exception(sprintf('User "%s" exists in DB but should not', $name));
    }

    try {
      $this->getUserManager()->getUser($name);
    }
    catch (\Exception $exception) {
      return;
    }

    throw new \Exception(sprintf('User "%s" does not exist in DB, but still exists in test variables', $name));
  }

  /**
   * @Given set Drupal8 watchdog error level :level
   */
  public function setWatchdogErrorDrupal8($level) {
    \Drupal::logger('php')->log($level, 'test');
  }

  /**
   * Clean watchdog after feature with an error.
   *
   * @AfterFeature @errorcleanup
   */
  public static function cleanWatchdog(AfterFeatureScope $scope) {
    if (db_table_exists('watchdog')) {
      db_truncate('watchdog')->execute();
    }
  }

  /**
   * @Given cookie :name exists
   */
  public function assertCookieExists($name) {
    $cookies = $this->getCookies();

    if (!isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" does not exist.', $name));
    }
  }

  /**
   * @Given cookie :name does not exist
   */
  public function assertCookieNotExists($name) {
    $cookies = $this->getCookies();

    if (isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" exists but should not.', $name));
    }
  }

  /**
   * Get a list of cookies.
   */
  protected function getCookies() {
    $cookie_list = [];

    /** @var Behat\Mink\Driver\BrowserKitDriver $driver */
    $driver = $this->getSession()->getDriver();
    if ($driver instanceof Selenium2Driver) {
      $cookies = $driver->getWebDriverSession()->getAllCookies();
      foreach ($cookies as $cookie) {
        $cookie_list[$cookie['name']] = $cookie['value'];
      }
    }
    else {
      $cookie_list = $driver->getClient()->getCookieJar()->allValues($driver->getCurrentUrl());
    }

    return $cookie_list;
  }

  /**
   * @Given I install a :name module
   */
  public function installModule($name) {
    /** @var \Drupal\Core\Extension\ModuleHandler $module_handler */
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists($name)) {
      return;
    }

    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');

    try {
      $result = $module_installer->install([$name]);
    }
    catch (MissingDependencyException $exception) {
      throw new \Exception(sprintf('Unable to install a module "%s": %s.', $name, $exception->getMessage()));
    }

    if (!$result) {
      throw new \Exception(sprintf('Unable to install a module "%s".', $name));
    }
  }

  /**
   * @Given I uninstall a :name module
   */
  public function uninstallModule($name) {
    /** @var \Drupal\Core\Extension\ModuleHandler $module_handler */
    $module_handler = \Drupal::service('module_handler');
    if (!$module_handler->moduleExists($name)) {
      throw new \RuntimeException(sprintf('Module "%s" does not exist.', $name));
    }

    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');

    $result = $module_installer->uninstall([$name]);

    if (!$result) {
      throw new \Exception(sprintf('Unable to uninstall a module "%s".', $name));
    }
  }

}
