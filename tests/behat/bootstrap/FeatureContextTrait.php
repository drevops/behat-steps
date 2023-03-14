<?php

/**
 * @file
 * Feature context trait for testing Behat-steps.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 *
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Driver\Selenium2Driver;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\file\Entity\File;

/**
 * Defines application features from the specific context.
 */
trait FeatureContextTrait {

  /**
   * Clean watchdog after feature with an error.
   *
   * @AfterFeature @errorcleanup
   */
  public static function cleanWatchdog(AfterFeatureScope $scope) {
    $database = Database::getConnection();
    if ($database->schema()->tableExists('watchdog')) {
      $database->truncate('watchdog')->execute();
    }
  }

  /**
   * @Then user :name does not exist
   */
  public function userDoesNotExist($name) {
    // We need to check that user was removed from both DB and test variables.
    $users = $this->userLoadMultiple(['name' => $name]);
    $user = reset($users);

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
   * @Given set watchdog error level :level
   * @Given set watchdog error level :level of type :type
   */
  public function setWatchdogErrorDrupal9($level, $type = 'php') {
    \Drupal::logger($type)->log($level, 'test');
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
   * @Given cookie :name does not exist
   */
  public function assertCookieNotExists($name) {
    $cookies = $this->getCookies();

    if (isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" exists but should not.', $name));
    }
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

  /**
   * @When I send test email to :email with
   * @When I send test email to :email with:
   */
  public function sendTestEmail($email, PyStringNode $string) {
    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email',
      $email,
      \Drupal::languageManager()->getDefaultLanguage(),
      ['body' => strval($string)],
      FALSE
    );
  }

  /**
   * @Then :file_name file object exists
   */
  public function fileObjectExist($file_name) {
    $file_name = basename($file_name);
    $fids = $this->fileLoadMultiple(['filename' => $file_name]);
    if (empty($fids)) {
      throw new \Exception(sprintf('"%s" file does not exist in DB, but it should', $file_name));
    }

    $fid = reset($fids);
    $file = File::load($fid);

    if ($file_name !== $file->label()) {
      throw new \Exception(sprintf('"%s" file does not exist in DB, but it should', $file_name));
    }
  }

  /**
   * @Then no :file_name file object exists
   */
  public function noFileObjectExist($file_name) {
    $file_name = basename($file_name);
    $fids = $this->fileLoadMultiple(['filename' => $file_name]);
    if ($fids) {
      throw new \Exception(sprintf('"%s" file does exist in DB, but it should not', $file_name));
    }
  }

}
