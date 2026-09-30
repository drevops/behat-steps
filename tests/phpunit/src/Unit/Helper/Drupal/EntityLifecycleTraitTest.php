<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Drupal;

use Behat\Behat\Context\Context;
use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Call\Callee;
use Behat\Testwork\Call\Handler\RuntimeCallHandler;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistry;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Helper\Drupal\AuthTrait;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\StaticCacheTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableRawContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ThrowingHookReader;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the Drupal scenario lifecycle the step traits compose.
 *
 * The cleanup hooks read the opt-out and the skip tags from the host context,
 * so the run covers that class too.
 */
#[CoversTrait(AuthTrait::class)]
#[CoversTrait(EntityLifecycleTrait::class)]
#[CoversTrait(StaticCacheTrait::class)]
#[CoversClass(WebRawContext::class)]
class EntityLifecycleTraitTest extends UnitTestCase {

  /**
   * A directory carrying the entry file the Drupal driver requires.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../../fixtures/driver/drupal-root';

  /**
   * The cleanup opt-out value to restore, NULL when it was unset.
   */
  protected ?string $envBackup;

  protected function setUp(): void {
    $existing = getenv('BEHAT_STEPS_DISABLE_CLEANUP');
    $this->envBackup = $existing === FALSE ? NULL : $existing;
    putenv('BEHAT_STEPS_DISABLE_CLEANUP');
  }

  protected function tearDown(): void {
    if ($this->envBackup === NULL) {
      putenv('BEHAT_STEPS_DISABLE_CLEANUP');
    }
    else {
      putenv('BEHAT_STEPS_DISABLE_CLEANUP=' . $this->envBackup);
    }
  }

  public function testImplementsUserAwareInterface(): void {
    $this->assertInstanceOf(UserAwareInterface::class, new TestableRawContext());
  }

  public function testUninitializedContextNamesTheMissingUserRegistry(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The user registry is available only after Behat has initialized the context.');

    (new TestableRawContext())->authGetUserRegistry();
  }

  public function testNodeCreationDelegatesAndTracksTheStub(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('node', 'page', ['title' => 'A title']);
    $driver->expects($this->once())->method('nodeCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->entityLifecycleNodeCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testTermCreationDelegatesAndTracksTheStub(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term']);
    $driver->expects($this->once())->method('termCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->entityLifecycleTermCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testAnEmptyTermParentIsDropped(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'parent' => '']);
    $driver->method('termCreate')->willReturn($stub);

    $this->createContext($driver)->entityLifecycleTermCreate($stub);

    $this->assertFalse($stub->hasValue('parent'));
  }

  public function testNamedTermParentIsKept(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'parent' => 'Another term']);
    $driver->method('termCreate')->willReturn($stub);

    $this->createContext($driver)->entityLifecycleTermCreate($stub);

    $this->assertSame('Another term', $stub->getValue('parent'));
  }

  public function testGenericEntityCreationDelegatesAndTracksTheStub(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('block_content', 'basic', ['info' => 'A block']);
    $driver->expects($this->once())->method('entityCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->entityLifecycleCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testScalarValuesSurviveTheDriverCall(): void {
    $stub = new EntityStub('node', 'page', ['title' => 'A title']);

    $driver = $this->createContentDriver();
    $driver->method('nodeCreate')->willReturnCallback(static function (EntityStub $stub): EntityStub {
      // The driver expands base fields into the storage shape.
      $stub->setValue('title', [['value' => 'A title']]);

      return $stub;
    });

    $this->createContext($driver)->entityLifecycleNodeCreate($stub);

    $this->assertSame('A title', $stub->getValue('title'));
  }

  public function testUserCreationRegistersTheUser(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class]);
    $stub = new EntityStub('user', NULL, ['name' => 'alice']);
    $driver->expects($this->once())->method('userCreate')->with($stub);

    $user_registry = new UserRegistry();
    $context = $this->createContext($driver, $user_registry);

    $this->assertSame($stub, $context->authUserCreate($stub));
    $this->assertSame($stub, $user_registry->getUser('alice'));
  }

  public function testLanguageCreationTracksTheReturnedStub(): void {
    $driver = $this->createDriver([LanguageCapabilityInterface::class]);
    $stub = new EntityStub('language', NULL, ['langcode' => 'fr']);
    $driver->method('languageCreate')->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->entityLifecycleLanguageCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testAnExistingLanguageIsNotTracked(): void {
    $driver = $this->createDriver([LanguageCapabilityInterface::class]);
    $driver->method('languageCreate')->willReturn(FALSE);

    $context = $this->createContext($driver);

    $this->assertFalse($context->entityLifecycleLanguageCreate(new EntityStub('language', NULL, ['langcode' => 'fr'])));
    $this->assertSame([], $context->getCreatedStubs());
  }

  /**
   * Tests that creation is refused when the driver lacks the capability.
   *
   * @param string $method
   *   The creation method to call.
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStub $stub
   *   The stub to pass to it.
   * @param class-string $capability
   *   The capability the creation is expected to ask for.
   */
  #[DataProvider('dataProviderCreationRefusesIncapableDriver')]
  public function testCreationRefusesIncapableDriver(string $method, EntityStub $stub, string $capability): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No driver provides "%s". Drivers available to this scenario, in order: test.', $capability));

    $context->$method($stub);
  }

  public static function dataProviderCreationRefusesIncapableDriver(): \Iterator {
    yield 'node' => ['entityLifecycleNodeCreate', new EntityStub('node'), ContentCapabilityInterface::class];
    yield 'term' => ['entityLifecycleTermCreate', new EntityStub('taxonomy_term'), ContentCapabilityInterface::class];
    yield 'entity' => ['entityLifecycleCreate', new EntityStub('block_content'), ContentCapabilityInterface::class];
    yield 'user' => ['authUserCreate', new EntityStub('user'), UserCapabilityInterface::class];
    yield 'language' => ['entityLifecycleLanguageCreate', new EntityStub('language'), LanguageCapabilityInterface::class];
  }

  public function testHookExceptionSurfacesFromDispatcher(): void {
    $manager = new EnvironmentManager();
    $manager->registerEnvironmentReader(new ThrowingHookReader());

    $call_center = new CallCenter();
    $call_center->registerCallHandler(new RuntimeCallHandler());

    $dispatcher = new HookDispatcher(new HookRepository($manager), $call_center);

    $context = $this->createContext($this->createContentDriver(), NULL, NULL, $dispatcher);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The hook failed.');

    $context->entityLifecycleNodeCreate(new EntityStub('node', 'page', ['title' => 'A title']));
  }

  public function testHooksCannotBeDispatchedBeforeInitialization(): void {
    $context = new TestableRawContext();
    $context->setDriverRegistry($this->createMock(DriverRegistryInterface::class));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The hook dispatcher is available only after Behat has initialized the context.');

    $context->entityLifecycleCreate(new EntityStub('block_content'));
  }

  public function testHooksCannotBeDispatchedBeforeScenarioStarts(): void {
    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->method('getEnvironment')->willReturn(NULL);

    $context = new TestableRawContext();
    $context->setDriverRegistry($driver_registry);
    $context->setDispatcher($this->createHookDispatcher());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Hooks can be dispatched only once a scenario has started.');

    $context->entityLifecycleCreate(new EntityStub('block_content'));
  }

  public function testCreatedEntitiesAreRemovedInReverseOrder(): void {
    $node = new EntityStub('node', 'page', ['title' => 'A node']);
    $term = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term']);
    $block = new EntityStub('block_content', 'basic');

    $deleted = [];
    $driver = $this->createContentDriver();
    $driver->method('nodeDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'node';
    });
    $driver->method('termDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): bool {
      $deleted[] = 'term';

      return TRUE;
    });
    $driver->method('entityDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'entity';
    });

    $context = $this->createContext($driver);
    $context->setCreatedStubs([$term, $node, $block]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope());

    $this->assertSame(['entity', 'node', 'term'], $deleted);
    $this->assertSame([], $context->getCreatedStubs());
  }

  /**
   * Tests that both language entity types route to the language capability.
   *
   * @param string $entity_type
   *   The entity type of the tracked stub.
   */
  #[DataProvider('dataProviderLanguageIsRemovedThroughLanguageCapability')]
  public function testLanguageIsRemovedThroughLanguageCapability(string $entity_type): void {
    $driver = $this->createDriver([LanguageCapabilityInterface::class, ContentCapabilityInterface::class]);
    $driver->expects($this->once())->method('languageDelete');
    $driver->expects($this->never())->method('entityDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub($entity_type, NULL, ['langcode' => 'fr'])]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope());
  }

  public static function dataProviderLanguageIsRemovedThroughLanguageCapability(): \Iterator {
    yield 'language' => ['language'];
    yield 'configurable_language' => ['configurable_language'];
  }

  public function testAnAlreadyRemovedLanguageDoesNotStopCleanup(): void {
    $driver = $this->createDriver([LanguageCapabilityInterface::class, ContentCapabilityInterface::class]);
    $driver->expects($this->once())->method('languageDelete')->willThrowException(new \RuntimeException('The language "fr" does not exist.'));
    $driver->expects($this->once())->method('nodeDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('node', 'page'), new EntityStub('language', NULL, ['langcode' => 'fr'])]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testLanguageIsLeftBehindByIncapableDriver(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('entityDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('language', NULL, ['langcode' => 'fr'])]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testEntitiesAreLeftBehindByIncapableDriver(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testNothingIsDeletedWhenNoEntityWasCreated(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('entityDelete');

    $this->createContext($driver)->entityLifecycleCleanAll($this->createAfterScenarioScope());
  }

  public function testCreatedUsersAreDeletedAndTheBatchIsDrained(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class, BatchCapabilityInterface::class]);
    $driver->expects($this->once())->method('userDelete');
    $driver->expects($this->once())->method('processBatch');

    $user_registry = new UserRegistry();
    $user_registry->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($driver, $user_registry)->authCleanUsers($this->createAfterScenarioScope());

    $this->assertFalse($user_registry->hasUsers());
  }

  public function testUsersAreLeftBehindByIncapableDriver(): void {
    $user_registry = new UserRegistry();
    $user_registry->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(DriverInterface::class), $user_registry)->authCleanUsers($this->createAfterScenarioScope());

    $this->assertTrue($user_registry->hasUsers());
  }

  public function testSessionIsResetWhenManagerSupportsFastLogout(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authenticator */
    $authenticator = $this->createMockForIntersectionOfInterfaces([AuthenticatorInterface::class, FastLogoutInterface::class]);
    $authenticator->expects($this->once())->method('fastLogout');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testKnownUserIsLoggedOutWithoutFastLogout(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logOut');

    $user_registry = new UserRegistry();
    $user_registry->setCurrentUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(DriverInterface::class), $user_registry, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testAnAnonymousSessionIsLeftAloneWhenTheManagerHasNoFastLogout(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testCreatedRolesAreDeleted(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->exactly(2))->method('roleDelete');

    $context = $this->createContext($driver);
    $context->setRoles(['editor', 'reviewer']);

    $context->authCleanRoles($this->createAfterScenarioScope());

    $this->assertSame([], $context->getRoles());
  }

  public function testRolesAreLeftBehindByIncapableDriver(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope());

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testNoRoleIsDeletedWhenNoneWasCreated(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->never())->method('roleDelete');

    $this->createContext($driver)->authCleanRoles($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreClearedOnDriverTheScenarioReached(): void {
    $driver = $this->createDriver([CacheCapabilityInterface::class]);
    $driver->expects($this->once())->method('cacheClearStatic');

    $context = $this->createContext($driver);
    $context->driverFor(CacheCapabilityInterface::class);

    $context->staticCacheClear($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreSkippedOnDriverTheScenarioNeverReached(): void {
    $driver = $this->createDriver([CacheCapabilityInterface::class]);
    $driver->expects($this->never())->method('cacheClearStatic');

    $this->createContext($driver)->staticCacheClear($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreSkippedOnAnIncapableDriver(): void {
    $this->expectNotToPerformAssertions();

    $this->createContext($this->createMock(DriverInterface::class))->staticCacheClear($this->createAfterScenarioScope());
  }

  /**
   * Tests which values of the opt-out variable disable cleanup.
   *
   * @param string $value
   *   The value of the cleanup opt-out variable.
   * @param bool $expected_cleanup
   *   Whether cleanup is expected to run.
   */
  #[DataProvider('dataProviderCleanupOptOut')]
  public function testCleanupOptOut(string $value, bool $expected_cleanup): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=' . $value);

    $driver = $this->createContentDriver();
    $driver->expects($expected_cleanup ? $this->once() : $this->never())->method('nodeDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope());
  }

  public static function dataProviderCleanupOptOut(): \Iterator {
    yield 'empty value still cleans up' => ['', TRUE];
    yield 'unrecognised value still cleans up' => ['maybe', TRUE];
    yield 'zero still cleans up' => ['0', TRUE];
    yield 'one disables cleanup' => ['1', FALSE];
    yield 'true disables cleanup' => ['TRUE', FALSE];
    yield 'yes disables cleanup' => ['yes', FALSE];
    yield 'on disables cleanup' => [' On ', FALSE];
  }

  public function testTheOptOutAlsoSkipsUserCleanup(): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=1');

    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testTheOptOutAlsoSkipsRoleCleanup(): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=1');

    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope());

    $this->assertSame(['editor'], $context->getRoles());
  }

  /**
   * Tests that the skip tag disables entity cleanup from either level.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   */
  #[DataProvider('dataProviderTheSkipTagDisablesEntityCleanup')]
  public function testTheSkipTagDisablesEntityCleanup(array $scenario_tags, array $feature_tags): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('nodeDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope($scenario_tags, $feature_tags));

    $this->assertCount(1, $context->getCreatedStubs());
  }

  public static function dataProviderTheSkipTagDisablesEntityCleanup(): \Iterator {
    yield 'on the scenario' => [['behat-steps-skip:EntityLifecycleTrait'], []];
    yield 'on the feature' => [[], ['behat-steps-skip:EntityLifecycleTrait']];
  }

  public function testTheAuthSkipTagLeavesEntityCleanupRunning(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->once())->method('nodeDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope(['behat-steps-skip:AuthTrait']));

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testTheSkipTagDisablesUserCleanup(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class]);
    $driver->expects($this->never())->method('userDelete');

    // The normal path calls 'fastLogout()' even for a scenario that created
    // no users, so the 'never()' expectation proves the early return ran.
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authenticator */
    $authenticator = $this->createMockForIntersectionOfInterfaces([AuthenticatorInterface::class, FastLogoutInterface::class]);
    $authenticator->expects($this->never())->method('fastLogout');

    $user_registry = new UserRegistry();
    $user_registry->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($driver, $user_registry, $authenticator)->authCleanUsers($this->createAfterScenarioScope(['behat-steps-skip:AuthTrait']));

    $this->assertTrue($user_registry->hasUsers());
  }

  public function testTheSkipTagDisablesRoleCleanup(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->never())->method('roleDelete');

    $context = $this->createContext($driver);
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope(['behat-steps-skip:AuthTrait']));

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testTheEntitySkipTagLeavesRoleCleanupRunning(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->once())->method('roleDelete');

    $context = $this->createContext($driver);
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope(['behat-steps-skip:EntityLifecycleTrait']));

    $this->assertSame([], $context->getRoles());
  }

  public function testTheEntityCleanupSkipTagSparesOnlyTheNamedType(): void {
    $deleted = [];

    $driver = $this->createContentDriver();
    $driver->method('nodeDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'node';
    });
    $driver->method('termDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): bool {
      $deleted[] = 'term';

      return TRUE;
    });

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('taxonomy_term', 'tags'), new EntityStub('node', 'page')]);

    $context->entityLifecycleCleanAll($this->createAfterScenarioScope(['behat-steps-entity-cleanup-skip:node']));

    $this->assertSame(['term'], $deleted);
  }

  public function testStringTimestampIsConvertedForInProcessDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025 UTC']);
    $context = $this->createContext($this->createDrupalContentDriver());

    TestableRawContext::entityLifecycleAlterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame(strtotime('1 January 2025 UTC'), $stub->getValue('created'));
  }

  /**
   * Tests that a value the driver already accepts is not rewritten.
   *
   * @param mixed $value
   *   The value seeded on the timestamp field.
   */
  #[DataProvider('dataProviderNonTextualTimestampIsLeftAlone')]
  public function testNonTextualTimestampIsLeftAlone(mixed $value): void {
    $stub = new EntityStub('node', 'page', ['created' => $value]);
    $context = $this->createContext($this->createDrupalContentDriver());

    TestableRawContext::entityLifecycleAlterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame($value, $stub->getValue('created'));
  }

  public static function dataProviderNonTextualTimestampIsLeftAlone(): \Iterator {
    yield 'numeric timestamp' => ['1735689600'];
    yield 'empty value' => [''];
    yield 'absent value' => [NULL];
  }

  public function testUnreadableTimestampIsReported(): void {
    $stub = new EntityStub('node', 'page', ['created' => 'not a date at all']);
    $context = $this->createContext($this->createDrupalContentDriver());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to read the "created" value "not a date at all" as a date.');

    TestableRawContext::entityLifecycleAlterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));
  }

  public function testTimestampConversionIsSkippedForForeignContext(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $scope = new BeforeNodeCreateScope($this->createMock(Environment::class), $this->createMock(Context::class), $stub);

    TestableRawContext::entityLifecycleAlterNodeParameters($scope);

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForRemoteDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createMock(DriverInterface::class));

    TestableRawContext::entityLifecycleAlterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForOutOfProcessDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createContentDriver());

    TestableRawContext::entityLifecycleAlterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testAnUnsavedEntityIsNotRegisteredForCleanup(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturn('nid');

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(NULL);
    $entity->method('getEntityType')->willReturn($entity_type);

    $context = $this->createContext($this->createContentDriver());
    $context->entityLifecycleRegister($entity);

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testLoginDelegatesToTheAuthenticator(): void {
    $user = new EntityStub('user', NULL, ['name' => 'alice']);

    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logIn')->with($user);

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authLogin($user);
  }

  public function testLogoutDelegatesToTheAuthenticator(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authLogout();
  }

  public function testFastLogoutIsUsedWhenAskedForAndSupported(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authenticator */
    $authenticator = $this->createMockForIntersectionOfInterfaces([AuthenticatorInterface::class, FastLogoutInterface::class]);
    $authenticator->expects($this->once())->method('fastLogout');
    $authenticator->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authLogout(TRUE);
  }

  public function testFastLogoutFallsBackWhenUnsupported(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authLogout(TRUE);
  }

  public function testLoggedInDelegatesToTheAuthenticator(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->method('loggedIn')->willReturn(TRUE);

    $this->assertTrue($this->createContext($this->createMock(DriverInterface::class), NULL, $authenticator)->authLoggedIn());
  }

  /**
   * Builds an initialized context over the given driver.
   *
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver the registry hands out.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface|null $user_registry
   *   The user registry, when the test inspects it.
   * @param \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface|null $authenticator
   *   The authenticator, when the test inspects it.
   * @param \Behat\Testwork\Hook\HookDispatcher|null $dispatcher
   *   The hook dispatcher, when the test needs one that finds hooks.
   */
  protected function createContext(DriverInterface $driver, ?UserRegistryInterface $user_registry = NULL, ?AuthenticatorInterface $authenticator = NULL, ?HookDispatcher $dispatcher = NULL): TestableRawContext {
    $environment = $this->createMock(Environment::class);
    // A real environment binds a callee to the context instance it holds. The
    // fixture hooks are static, so the callee's own callable is enough for
    // the dispatcher to invoke them.
    $environment->method('bindCallee')->willReturnCallback(static fn(Callee $callee): mixed => $callee->getCallable());

    $driver_registry = new DriverRegistry(['test' => $driver]);
    $driver_registry->setScenarioDrivers(['test' => 'test']);
    $driver_registry->setEnvironment($environment);

    $context = new TestableRawContext();
    $context->setDriverRegistry($driver_registry);
    $context->setDispatcher($dispatcher ?? $this->createHookDispatcher());
    $context->authSetUserRegistry($user_registry ?? new UserRegistry());
    $context->authSetAuthenticator($authenticator ?? $this->createMock(AuthenticatorInterface::class));

    return $context;
  }

  /**
   * Builds a driver double implementing the given capabilities.
   *
   * @param array<int, class-string> $capabilities
   *   The capability interfaces the driver should satisfy.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createDriver(array $capabilities): DriverInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject $driver */
    $driver = $this->createMockForIntersectionOfInterfaces([DriverInterface::class, ...$capabilities]);

    return $driver;
  }

  /**
   * Builds a content-capable driver double.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createContentDriver(): DriverInterface&MockObject {
    return $this->createDriver([ContentCapabilityInterface::class]);
  }

  /**
   * Builds a driver double that saves content through Drupal's own storage.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createDrupalContentDriver(): DriverInterface&MockObject {
    return $this->createDriver([ContentCapabilityInterface::class, CoreCapabilityInterface::class]);
  }

}
