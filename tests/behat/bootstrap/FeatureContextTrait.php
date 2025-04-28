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

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Driver\Selenium2Driver;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\file\Entity\File;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Defines application features from the specific context.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
trait FeatureContextTrait {

  /**
   * Clean watchdog after feature with an error.
   *
   * @AfterFeature @errorcleanup
   */
  public static function testClearWatchdog(AfterFeatureScope $scope): void {
    $database = Database::getConnection();
    if ($database->schema()->tableExists('watchdog')) {
      $database->truncate('watchdog')->execute();
    }
  }

  /**
   * @Then user :name should exist
   */
  public function testUserExists(string $name): void {
    // We need to check that user exists in both DB and test variables.
    $users = $this->userLoadMultiple(['name' => $name]);
    $user = reset($users);

    if (!$user) {
      throw new \Exception(sprintf('User "%s" does not exist in DB.', $name));
    }

    try {
      $this->getUserManager()->getUser($name);
    }
    catch (\Exception) {
      throw new \Exception(sprintf('User "%s" exists in DB, but does not exist in test variables', $name));
    }
  }

  /**
   * @Then user :name should not exist
   */
  public function testUserNotExists(string $name): void {
    // We need to check that user was removed from both DB and test variables.
    $users = $this->userLoadMultiple(['name' => $name]);
    $user = reset($users);

    if ($user) {
      throw new \Exception(sprintf('User "%s" exists in DB, but should not.', $name));
    }

    try {
      $this->getUserManager()->getUser($name);
    }
    catch (\Exception) {
      return;
    }

    throw new \Exception(sprintf('User "%s" does not exist in DB, but exists in test variables', $name));
  }

  /**
   * @Given set watchdog error level :level
   * @Given set watchdog error level :level of type :type
   */
  public function testSetWatchdogError(string $level, string $type = 'php'): void {
    \Drupal::logger($type)->log($level, 'test');
  }

  /**
   * @Given cookie :name exists
   */
  public function testAssertCookieExists(string $name): void {
    $cookies = $this->testGetAllCookies();

    if (!isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" does not exist.', $name));
    }
  }

  /**
   * @Given cookie :name does not exist
   */
  public function testAssertCookieNotExists(string $name): void {
    $cookies = $this->testGetAllCookies();

    if (isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" exists but should not.', $name));
    }
  }

  /**
   * @Given I install a :name module
   */
  public function testInstallModule(string $name): void {
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
    catch (MissingDependencyException $missingDependencyException) {
      throw new \Exception(sprintf('Unable to install a module "%s": %s.', $name, $missingDependencyException->getMessage()), $missingDependencyException->getCode(), $missingDependencyException);
    }

    if (!$result) {
      throw new \Exception(sprintf('Unable to install a module "%s".', $name));
    }
  }

  /**
   * @Given I uninstall a :name module
   */
  public function testUninstallModule(string $name): void {
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
  public function testSendEmail(string $email, PyStringNode $string): void {
    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      ['body' => strval($string)],
      NULL
    );
  }

  /**
   * Send a test email with an attachment.
   *
   * @When I send test email to :email with subject :subject and attachment :attachment and body:
   */
  public function testSendEmailWithAttachment(string $email, string $subject, string $attachment, PyStringNode $body): void {
    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email_with_attachment',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => $subject,
        'body' => strval($body),
        'attachments' => [
          ['filename' => $attachment, 'content' => 'Attachment content for ' . $attachment],
        ],
      ],
      NULL
    );
  }

  /**
   * @Then :file_name file object exists
   */
  public function testAssertFileObjectExists(string $file_name): void {
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
  public function testAssertFileObjectNotExists(string $file_name): void {
    $file_name = basename($file_name);
    $fids = $this->fileLoadMultiple(['filename' => $file_name]);
    if ($fids) {
      throw new \Exception(sprintf('"%s" file does exist in DB, but it should not', $file_name));
    }
  }

  /**
   * @Then :entity_type entity exists with UUID :uuid
   */
  public function testAssertEntityExistsByUuid(string $entity_type, string $uuid): void {
    $entity = \Drupal::service('entity.repository')->loadEntityByUuid($entity_type, $uuid);

    if (!$entity) {
      throw new \Exception(sprintf('Entity of type "%s" does not exist in DB with UUID "%s", but it should', $entity_type, $uuid));
    }
  }

  /**
   * Get a list of cookies.
   *
   * @return array<string, string>
   *   List of cookies.
   */
  protected function testGetAllCookies(): array {
    $cookie_list = [];

    $driver = $this->getSession()->getDriver();
    if ($driver instanceof Selenium2Driver) {
      $cookies = $driver->getWebDriverSession()->getAllCookies();
      foreach ($cookies as $cookie) {
        $cookie_list[$cookie['name']] = $cookie['value'];
      }
    }
    else {
      /** @var \Behat\Mink\Driver\BrowserKitDriver $driver */
      $cookie_list = $driver->getClient()->getCookieJar()->allValues($driver->getCurrentUrl());
    }

    return $cookie_list;
  }

  /**
   * @Given I set a test cookie with name :name and value :value
   */
  public function testSetCookie(string $name, string $value): void {
    $session = $this->getSession();

    $driver = $session->getDriver();

    // WebDriver-based drivers like Selenium2Driver.
    if (method_exists($driver, 'getWebDriverSession')) {
      $driver->getWebDriverSession()->setCookie([
        'name' => $name,
        'value' => rawurlencode($value),
        'secure' => FALSE,
      ]);
    }

    // BrowserKit-based drivers like GoutteDriver.
    if (method_exists($driver, 'getClient')) {
      $cookie_jar = $driver->getClient()->getCookieJar();
      $cookie = new Cookie($name, rawurlencode($value));
      $cookie_jar->set($cookie);
    }
  }

}
