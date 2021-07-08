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
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Driver\Selenium2Driver;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use IntegratedExperts\BehatSteps\D8\BigPipeTrait;
use IntegratedExperts\BehatSteps\D8\ContentTrait;
use IntegratedExperts\BehatSteps\D8\DraggableViewsTrait;
use IntegratedExperts\BehatSteps\D8\EmailTrait;
use IntegratedExperts\BehatSteps\D8\FileDownloadTrait;
use IntegratedExperts\BehatSteps\D8\FileTrait;
use IntegratedExperts\BehatSteps\D8\MediaTrait;
use IntegratedExperts\BehatSteps\D8\MenuTrait;
use IntegratedExperts\BehatSteps\D8\OverrideTrait;
use IntegratedExperts\BehatSteps\D8\ParagraphsTrait;
use IntegratedExperts\BehatSteps\D8\RoleTrait;
use IntegratedExperts\BehatSteps\D8\TaxonomyTrait;
use IntegratedExperts\BehatSteps\D8\TestmodeTrait;
use IntegratedExperts\BehatSteps\D8\UserTrait;
use IntegratedExperts\BehatSteps\D8\WatchdogTrait;
use IntegratedExperts\BehatSteps\D8\WebformTrait;
use IntegratedExperts\BehatSteps\D8\WysiwygTrait;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;
use IntegratedExperts\BehatSteps\SelectTrait;
use IntegratedExperts\BehatSteps\WaitTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContextD8 extends DrupalContext {

  use BigPipeTrait;
  use ContentTrait;
  use DraggableViewsTrait;
  use EmailTrait;
  use FieldTrait;
  use FileDownloadTrait;
  use FileTrait;
  use LinkTrait;
  use MediaTrait;
  use MenuTrait;
  use OverrideTrait;
  use ParagraphsTrait;
  use PathTrait;
  use ResponseTrait;
  use RoleTrait;
  use SelectTrait;
  use TaxonomyTrait;
  use TestmodeTrait;
  use UserTrait;
  use WatchdogTrait;
  use WebformTrait;
  use WaitTrait;
  use WysiwygTrait;

  /**
   * @Then user :name does not exists
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
    $database = Database::getConnection();
    if ($database->schema()->tableExists('watchdog')) {
      $database->truncate('watchdog')->execute();
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
   * Helper to load multiple files with specified conditions.
   *
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of file ids.
   */
  protected function fileLoadMultiple(array $conditions = []) {
    $query = \Drupal::entityQuery('file');
    $query->addMetaData('account', User::load(1));
    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
