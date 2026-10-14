<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RequestEnvironmentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\MenuLinkCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\NodeCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\PathAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\TermCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\UserActivityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackend;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\ParserExposingDrushBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the Drush backend.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
class DrushBackendTest extends UnitTestCase {

  public function testImplementsDrushBackendInterface(): void {
    $interfaces = (array) class_implements(DrushBackend::class);

    $this->assertContains(DrushBackendInterface::class, $interfaces);
    $this->assertContains(BackendInterface::class, $interfaces);
  }

  public function testPassesRequestHeadersToItsCommands(): void {
    $this->assertContains(RequestEnvironmentCapabilityInterface::class, (array) class_implements(DrushBackend::class));
  }

  /**
   * Tests that the backend does not claim a capability Drush cannot serve.
   *
   * @param string $capability_class
   *   The capability interface name.
   */
  #[DataProvider('dataProviderDoesNotImplementCapability')]
  public function testDoesNotImplementCapability(string $capability_class): void {
    $this->assertNotContains($capability_class, (array) class_implements(DrushBackend::class), sprintf('DrushBackend must not claim to implement %s.', $capability_class));
  }

  public static function dataProviderDoesNotImplementCapability(): \Iterator {
    yield 'authentication' => [AuthenticationCapabilityInterface::class];
    yield 'block' => [BlockCapabilityInterface::class];
    yield 'config entity' => [ConfigEntityCapabilityInterface::class];
    yield 'content' => [ContentCapabilityInterface::class];
    yield 'core' => [CoreCapabilityInterface::class];
    yield 'entity definition' => [EntityDefinitionCapabilityInterface::class];
    yield 'entity lookup' => [EntityLookupCapabilityInterface::class];
    yield 'language' => [LanguageCapabilityInterface::class];
    yield 'mail' => [MailCapabilityInterface::class];
    yield 'menu link' => [MenuLinkCapabilityInterface::class];
    yield 'node' => [NodeCapabilityInterface::class];
    yield 'path alias' => [PathAliasCapabilityInterface::class];
    yield 'term' => [TermCapabilityInterface::class];
    yield 'user activity' => [UserActivityCapabilityInterface::class];
  }

  public function testWithAlias(): void {
    $backend = new DrushBackend('alias');
    $this->assertSame('alias', $backend->alias, 'The drush alias was not properly set.');
  }

  public function testWithAliasPrefix(): void {
    $backend = new DrushBackend('@alias');
    $this->assertSame('alias', $backend->alias, 'The drush alias did not remove the "@" prefix.');
  }

  /**
   * Tests instantiating the backend with an alias that reads as falsy.
   */
  public function testWithAliasZero(): void {
    $backend = new DrushBackend('0');
    $this->assertSame('0', $backend->alias);
  }

  public function testWithRoot(): void {
    // The backend only resolves the root with 'realpath()', so the path to this
    // file serves as a root.
    $backend = new DrushBackend(NULL, __FILE__);
    $this->assertSame(__FILE__, $backend->root);
  }

  /**
   * Tests that an alias is used even when a root path is given beside it.
   */
  public function testWithAliasAndRoot(): void {
    $backend = new DrushBackend('alias', __FILE__);

    $this->assertSame('alias', $backend->alias);
    $this->assertFalse((new \ReflectionProperty(DrushBackend::class, 'root'))->isInitialized($backend));
  }

  /**
   * Tests that the backend rejects a missing or an empty alias and root path.
   *
   * @param string|null $alias
   *   The alias to construct with.
   * @param string|null $root_path
   *   The root path to construct with.
   * @param string $expected_message
   *   The message the constructor is expected to throw with.
   */
  #[DataProvider('dataProviderConstructorRejects')]
  public function testConstructorRejects(?string $alias, ?string $root_path, string $expected_message): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($expected_message);

    new DrushBackend($alias, $root_path);
  }

  public static function dataProviderConstructorRejects(): \Iterator {
    yield 'neither given' => [NULL, NULL, 'A drush alias or root path is required.'];
    yield 'an empty alias' => ['', NULL, 'The drush alias "" names no site. Pass NULL to leave it out.'];
    yield 'an alias of only the prefix' => ['@', NULL, 'The drush alias "@" names no site. Pass NULL to leave it out.'];
    yield 'an alias of only whitespace' => [' ', NULL, 'The drush alias " " names no site. Pass NULL to leave it out.'];
    yield 'an alias of the prefix and whitespace' => ['@ ', NULL, 'The drush alias "@ " names no site. Pass NULL to leave it out.'];
    yield 'an empty alias beside a root path' => ['', __FILE__, 'The drush alias "" names no site. Pass NULL to leave it out.'];
    yield 'an empty root path' => [NULL, '', 'The root path is empty. Pass NULL to leave it out.'];
    yield 'an empty root path beside an alias' => ['alias', '', 'The root path is empty. Pass NULL to leave it out.'];
    yield 'a root path that does not exist' => [NULL, '/nonexistent/drupal/root', 'No Drupal installation found at /nonexistent/drupal/root.'];
  }

  #[DataProvider('dataProviderParseUserId')]
  public function testParseUserId(string $drush_output, ?int $expected): void {
    $backend = new ParserExposingDrushBackend('alias');
    $result = $backend->callParseUserId($drush_output);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderParseUserId(): \Iterator {
    yield 'legacy key-value format' => [
      "User ID   :   550895\nUser name :   test\n",
      550895,
    ];
    yield 'drush 12 table format' => [
      " --------- ----------- ----------- --------------- ------------- \n  User ID   User name   User mail   User roles      User status  \n --------- ----------- ----------- --------------- ------------- \n  550895    test        test@ex.co  authenticated   1            \n --------- ----------- ----------- --------------- ------------- \n",
      550895,
    ];
    yield 'no user id present' => [
      "Some random output\n",
      NULL,
    ];
    yield 'drush 12 table uid 1' => [
      " --------- ----------- ----------- --------------- ------------- \n  User ID   User name   User mail   User roles      User status  \n --------- ----------- ----------- --------------- ------------- \n  1         admin       a@ex.co     administrator   1            \n --------- ----------- ----------- --------------- ------------- \n",
      1,
    ];
  }

}
