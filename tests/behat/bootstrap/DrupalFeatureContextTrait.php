<?php

/**
 * @file
 * Feature context trait for testing the Drupal half of Behat-steps.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 */

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterFeature;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\file\Entity\File;

/**
 * Defines application features from the specific context.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\DrupalRawContext
 */
trait DrupalFeatureContextTrait {

  /**
   * Clean watchdog after feature with an error.
   */
  #[AfterFeature('@test-errorcleanup')]
  public static function testClearWatchdog(AfterFeatureScope $scope): void {
    $database = Database::getConnection();
    if ($database->schema()->tableExists('watchdog')) {
      $database->truncate('watchdog')->execute();
    }
  }

  /**
   * Clear watchdog table.
   */
  #[Given('the watchdog is cleared')]
  public function testClearWatchdogTable(): void {
    $this->driverFor(CoreCapabilityInterface::class);

    $database = Database::getConnection();
    if ($database->schema()->tableExists('watchdog')) {
      $database->truncate('watchdog')->execute();
    }
  }

  /**
   * Assert that a user exists.
   */
  #[Then('user :name should exist')]
  public function testUserExists(string $name): void {
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
   * Assert that a user does not exist.
   */
  #[Then('user :name should not exist')]
  public function testUserNotExists(string $name): void {
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
   * Set watchdog error level for testing.
   */
  #[Given('set watchdog error level :level')]
  #[Given('set watchdog error level :level of type :type')]
  public function testSetWatchdogError(string $level, string $type = 'php'): void {
    $this->driverFor(CoreCapabilityInterface::class);

    \Drupal::logger($type)->log($level, 'test');
  }

  /**
   * Install a module.
   */
  #[Given('I install a :name module')]
  public function testInstallModule(string $name): void {
    $this->driverFor(CoreCapabilityInterface::class);

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
    catch (MissingDependencyException $missing_dependency_exception) {
      throw new \Exception(sprintf('Unable to install a module "%s": %s.', $name, $missing_dependency_exception->getMessage()), $missing_dependency_exception->getCode(), $missing_dependency_exception);
    }

    if (!$result) {
      throw new \Exception(sprintf('Unable to install a module "%s".', $name));
    }
  }

  /**
   * Uninstall a module.
   */
  #[Given('I uninstall a :name module')]
  public function testUninstallModule(string $name): void {
    $this->driverFor(CoreCapabilityInterface::class);

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
   * Send a test email.
   */
  #[When('I send test email to :email with')]
  #[When('I send test email to :email with:')]
  public function testSendEmail(string $email, PyStringNode $string): void {
    $this->driverFor(CoreCapabilityInterface::class);

    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      ['body' => (string) $string],
      NULL
    );
  }

  /**
   * Send a test email with CC.
   */
  #[When('I send test email to :to with cc :cc with')]
  #[When('I send test email to :to with cc :cc with:')]
  public function testSendEmailWithCc(string $to, string $cc, PyStringNode $string): void {
    $this->driverFor(CoreCapabilityInterface::class);

    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email_with_cc',
      $to,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => 'Test Email',
        'body' => (string) $string,
        'headers' => [
          'Cc' => $cc,
        ],
      ],
      NULL
    );
  }

  /**
   * Send a test email with BCC.
   */
  #[When('I send test email to :to with bcc :bcc with')]
  #[When('I send test email to :to with bcc :bcc with:')]
  public function testSendEmailWithBcc(string $to, string $bcc, PyStringNode $string): void {
    $this->driverFor(CoreCapabilityInterface::class);

    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email_with_bcc',
      $to,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => 'Test Email',
        'body' => (string) $string,
        'headers' => [
          'Bcc' => $bcc,
        ],
      ],
      NULL
    );
  }

  /**
   * Send a test email with both CC and BCC.
   */
  #[When('I send test email to :to with cc :cc and bcc :bcc with')]
  #[When('I send test email to :to with cc :cc and bcc :bcc with:')]
  public function testSendEmailWithCcAndBcc(string $to, string $cc, string $bcc, PyStringNode $string): void {
    $this->driverFor(CoreCapabilityInterface::class);

    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email_with_cc_bcc',
      $to,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => 'Test Email',
        'body' => (string) $string,
        'headers' => [
          'Cc' => $cc,
          'Bcc' => $bcc,
        ],
      ],
      NULL
    );
  }

  /**
   * Send a test email with an attachment.
   */
  #[When('I send test email to :email with subject :subject and attachment :attachment and body:')]
  public function testSendEmailWithAttachment(string $email, string $subject, string $attachment, PyStringNode $body): void {
    $this->driverFor(CoreCapabilityInterface::class);

    \Drupal::service('plugin.manager.mail')->mail(
      'mysite_core',
      'test_email_with_attachment',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => $subject,
        'body' => (string) $body,
        'attachments' => [
          ['filename' => $attachment, 'content' => 'Attachment content for ' . $attachment],
        ],
      ],
      NULL
    );
  }

  /**
   * Assert that a file object exists.
   */
  #[Then(':file_name file object exists')]
  public function testAssertFileObjectExists(string $file_name): void {
    $this->driverFor(CoreCapabilityInterface::class);

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
   * Assert that a file object does not exist.
   */
  #[Then('no :file_name file object exists')]
  public function testAssertFileObjectNotExists(string $file_name): void {
    $file_name = basename($file_name);
    $fids = $this->fileLoadMultiple(['filename' => $file_name]);
    if ($fids) {
      throw new \Exception(sprintf('"%s" file does exist in DB, but it should not', $file_name));
    }
  }

  /**
   * Assert that an entity exists with the specified UUID.
   */
  #[Then(':entity_type entity exists with UUID :uuid')]
  public function testAssertEntityExistsByUuid(string $entity_type, string $uuid): void {
    $this->driverFor(CoreCapabilityInterface::class);

    $entity = \Drupal::service('entity.repository')->loadEntityByUuid($entity_type, $uuid);

    if (!$entity) {
      throw new \Exception(sprintf('Entity of type "%s" does not exist in DB with UUID "%s", but it should', $entity_type, $uuid));
    }
  }

  /**
   * Assert that the mailsystem formatter has the expected value.
   */
  #[Then('the mailsystem formatter should be :expected')]
  public function testAssertMailsystemFormatter(string $expected): void {
    $this->driverFor(CoreCapabilityInterface::class);

    $formatter = \Drupal::config('mailsystem.settings')->get('defaults.formatter');
    if ($formatter !== $expected) {
      throw new \Exception(sprintf('Expected mailsystem formatter to be "%s", but got "%s".', $expected, $formatter));
    }
  }

  /**
   * Test tableTransposeVertical method.
   */
  #[When('I call tableTransposeVertical with:')]
  public function testCallTableTransposeVertical(TableNode $table): void {
    $result = $this->tableTransposeVertical($table);

    if (empty($result)) {
      throw new \Exception('tableTransposeVertical returned empty result.');
    }

    foreach ($result as $entity) {
      if (!is_array($entity)) {
        throw new \Exception('tableTransposeVertical returned invalid entity data.');
      }
    }
  }

  /**
   * Add items to a Drupal queue for testing.
   */
  #[Given('I add :count item(s) to the :queue queue')]
  public function testAddItemsToQueue(int $count, string $queue): void {
    $this->driverFor(CoreCapabilityInterface::class);

    $queue_instance = \Drupal::service('queue')->get($queue);
    for ($i = 0; $i < $count; $i++) {
      $queue_instance->createItem(['data' => 'test_item_' . $i]);
    }
  }

}
