<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for system-level methods on Core via the backend.
 *
 * Covers module install/uninstall, language create/delete, module list
 * retrieval, and the account switcher login/logout pair in a single class
 * to amortise per-method KernelTestBase bootstrap cost.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreSystemMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'language',
  ];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // users_data is a legacy schema used by user module uninstall hooks;
    // install it so moduleUninstall does not crash on missing table.
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'language']);

    $this->core = new Core($this->root);
  }

  /**
   * Tests that moduleInstall and moduleUninstall flip module state.
   *
   * 'syslog' is chosen because installing it does not create dependent
   * config. 'filter', by contrast, creates filter plugins referenced by the
   * default format and blocks later uninstall in kernel tests.
   */
  public function testModuleInstallAndUninstall(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('syslog'), 'syslog is not installed at setUp.');

    $this->core->moduleInstall('syslog');
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('syslog'), 'moduleInstall enabled syslog.');

    $this->core->moduleUninstall('syslog');
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('syslog'), 'moduleUninstall disabled syslog.');
  }

  public function testGetModuleListIncludesEnabledModules(): void {
    $modules = $this->core->getModuleList();

    $this->assertContains('system', $modules);
    $this->assertContains('user', $modules);
    $this->assertContains('language', $modules);
  }

  /**
   * Tests languageCreate with a fresh language and languageDelete removes it.
   */
  public function testLanguageLifecycle(): void {
    $this->assertNull(ConfigurableLanguage::load('fr'));

    $stub = new EntityStub('language', NULL, ['langcode' => 'fr']);
    $this->assertSame($stub, $this->core->languageCreate($stub));
    $this->assertTrue($stub->isSaved());
    $this->assertInstanceOf(ConfigurableLanguage::class, ConfigurableLanguage::load('fr'));

    $this->core->languageDelete($stub);
    $this->assertNull(ConfigurableLanguage::load('fr'));
  }

  public function testLanguageCreateLeavesAnExistingLanguageAlone(): void {
    $this->core->languageCreate(new EntityStub('language', NULL, ['langcode' => 'fr']));
    $existing = ConfigurableLanguage::load('fr');
    $this->assertInstanceOf(ConfigurableLanguage::class, $existing);

    $stub = new EntityStub('language', NULL, ['langcode' => 'fr']);

    $this->assertSame($stub, $this->core->languageCreate($stub));
    $this->assertFalse($stub->isSaved());
    $this->assertSame($existing->uuid(), ConfigurableLanguage::load('fr')?->uuid());
  }

  public function testLanguageDeleteToleratesMissingLanguage(): void {
    $this->assertNull(ConfigurableLanguage::load('fr'));

    $this->core->languageDelete(new EntityStub('language', NULL, ['langcode' => 'fr']));

    $this->assertNull(ConfigurableLanguage::load('fr'));
  }

  /**
   * Tests that login switches the active account and logout restores it.
   */
  public function testLoginAndLogoutSwitchesAccount(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $before_uid = \Drupal::currentUser()->id();

    $this->core->login(new EntityStub('user', NULL, ['uid' => $alice->id()]));
    $this->assertSame((int) $alice->id(), (int) \Drupal::currentUser()->id(), 'login switched to alice.');

    $this->core->logout();
    $this->assertSame((int) $before_uid, (int) \Drupal::currentUser()->id(), 'logout restored the original account.');
  }

}
