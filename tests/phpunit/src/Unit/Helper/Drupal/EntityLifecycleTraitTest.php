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
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistry;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistry;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;
use DrevOps\BehatSteps\Helper\Drupal\AuthTrait;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\StaticCacheTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableRawContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ThrowingHookReader;
use DrevOps\BehatSteps\Tests\UnitTestCase;
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
   * A directory carrying the entry file the Drupal backend requires.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../../fixtures/backend/drupal-root';

  /**
   * The cleanup opt-out value to restore, NULL when it was unset.
   */
  protected ?string $envBackup;

  protected function setUp(): void {
    parent::setUp();

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

    parent::tearDown();
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
    $backend = $this->createContentBackend();
    $stub = new EntityStub('node', 'page', ['title' => 'A title']);
    $backend->expects($this->once())->method('nodeCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($backend);

    $this->assertSame($stub, $context->entityLifecycleNodeCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testTermCreationDelegatesAndTracksTheStub(): void {
    $backend = $this->createContentBackend();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term']);
    $backend->expects($this->once())->method('termCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($backend);

    $this->assertSame($stub, $context->entityLifecycleTermCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testAnEmptyTermParentIsDropped(): void {
    $backend = $this->createContentBackend();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'parent' => '']);
    $backend->method('termCreate')->willReturn($stub);

    $this->createContext($backend)->entityLifecycleTermCreate($stub);

    $this->assertFalse($stub->hasValue('parent'));
  }

  public function testNamedTermParentIsKept(): void {
    $backend = $this->createContentBackend();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'parent' => 'Another term']);
    $backend->method('termCreate')->willReturn($stub);

    $this->createContext($backend)->entityLifecycleTermCreate($stub);

    $this->assertSame('Another term', $stub->getValue('parent'));
  }

  public function testGenericEntityCreationDelegatesAndTracksTheStub(): void {
    $backend = $this->createContentBackend();
    $stub = new EntityStub('block_content', 'basic', ['info' => 'A block']);
    $backend->expects($this->once())->method('entityCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($backend);

    $this->assertSame($stub, $context->entityLifecycleCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testScalarValuesSurviveTheBackendCall(): void {
    $stub = new EntityStub('node', 'page', ['title' => 'A title']);

    $backend = $this->createContentBackend();
    $backend->method('nodeCreate')->willReturnCallback(static function (EntityStub $stub): EntityStub {
      // The backend expands base fields into the storage shape.
      $stub->setValue('title', [['value' => 'A title']]);

      return $stub;
    });

    $this->createContext($backend)->entityLifecycleNodeCreate($stub);

    $this->assertSame('A title', $stub->getValue('title'));
  }

  public function testUserCreationRegistersTheUser(): void {
    $backend = $this->createBackend([UserCapabilityInterface::class]);
    $stub = new EntityStub('user', NULL, ['name' => 'alice']);
    $backend->expects($this->once())->method('userCreate')->with($stub);

    $user_registry = new UserRegistry();
    $context = $this->createContext($backend, $user_registry);

    $this->assertSame($stub, $context->authUserCreate($stub));
    $this->assertSame($stub, $user_registry->getUser('alice'));
  }

  public function testLanguageCreationTracksTheSavedStub(): void {
    $backend = $this->createBackend([LanguageCapabilityInterface::class]);
    $stub = (new EntityStub('language', NULL, ['langcode' => 'fr']))->markSaved(new \stdClass());
    $backend->method('languageCreate')->willReturn($stub);

    $context = $this->createContext($backend);

    $this->assertSame($stub, $context->entityLifecycleLanguageCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testAnExistingLanguageIsNotTracked(): void {
    $backend = $this->createBackend([LanguageCapabilityInterface::class]);
    $stub = new EntityStub('language', NULL, ['langcode' => 'fr']);
    $backend->method('languageCreate')->willReturn($stub);

    $context = $this->createContext($backend);

    $this->assertSame($stub, $context->entityLifecycleLanguageCreate($stub));
    $this->assertSame([], $context->getCreatedStubs());
  }

  /**
   * Tests that creation is refused when the backend lacks the capability.
   *
   * @param string $method
   *   The creation method to call.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStub $stub
   *   The stub to pass to it.
   * @param class-string $capability
   *   The capability the creation is expected to ask for.
   */
  #[DataProvider('dataProviderCreationRefusesIncapableBackend')]
  public function testCreationRefusesIncapableBackend(string $method, EntityStub $stub, string $capability): void {
    $context = $this->createContext($this->createMock(BackendInterface::class));

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessage(sprintf('No backend provides "%s". Backends available to this scenario, in order: test.', $capability));

    $context->$method($stub);
  }

  public static function dataProviderCreationRefusesIncapableBackend(): \Iterator {
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

    $context = $this->createContext($this->createContentBackend(), NULL, NULL, $dispatcher);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The hook failed.');

    $context->entityLifecycleNodeCreate(new EntityStub('node', 'page', ['title' => 'A title']));
  }

  public function testHooksCannotBeDispatchedBeforeInitialization(): void {
    $context = new TestableRawContext();
    $context->setBackendRegistry($this->createMock(BackendRegistryInterface::class));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The hook dispatcher is available only after Behat has initialized the context.');

    $context->entityLifecycleCreate(new EntityStub('block_content'));
  }

  public function testHooksCannotBeDispatchedBeforeScenarioStarts(): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->method('getEnvironment')->willReturn(NULL);

    $context = new TestableRawContext();
    $context->setBackendRegistry($backend_registry);
    $context->setHookDispatcher($this->createHookDispatcher());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Hooks can be dispatched only once a scenario has started.');

    $context->entityLifecycleCreate(new EntityStub('block_content'));
  }

  public function testCreatedEntitiesAreRemovedInReverseOrder(): void {
    $node = new EntityStub('node', 'page', ['title' => 'A node']);
    $term = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term']);
    $block = new EntityStub('block_content', 'basic');

    $deleted = [];
    $backend = $this->createContentBackend();
    $backend->method('nodeDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'node';
    });
    $backend->method('termDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'term';
    });
    $backend->method('entityDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'entity';
    });

    $context = $this->createContext($backend);
    $context->setCreatedStubs([$term, $node, $block]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope());

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
    $backend = $this->createBackend([LanguageCapabilityInterface::class, ContentCapabilityInterface::class]);
    $backend->expects($this->once())->method('languageDelete');
    $backend->expects($this->never())->method('entityDelete');

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub($entity_type, NULL, ['langcode' => 'fr'])]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope());
  }

  public static function dataProviderLanguageIsRemovedThroughLanguageCapability(): \Iterator {
    yield 'language' => ['language'];
    yield 'configurable_language' => ['configurable_language'];
  }

  public function testALanguageDeleteFailureSurfaces(): void {
    $backend = $this->createBackend([LanguageCapabilityInterface::class]);
    $backend->expects($this->once())->method('languageDelete')->willThrowException(new \RuntimeException('Cannot operate on a language without a non-empty "langcode" value.'));

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub('language')]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot operate on a language without a non-empty "langcode" value.');

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope());
  }

  public function testLanguageIsLeftBehindByIncapableBackend(): void {
    $backend = $this->createContentBackend();
    $backend->expects($this->never())->method('entityDelete');

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub('language', NULL, ['langcode' => 'fr'])]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testEntitiesAreLeftBehindByIncapableBackend(): void {
    $context = $this->createContext($this->createMock(BackendInterface::class));
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testNothingIsDeletedWhenNoEntityWasCreated(): void {
    $backend = $this->createContentBackend();
    $backend->expects($this->never())->method('entityDelete');

    $this->createContext($backend)->entityLifecycleAfterScenario($this->createAfterScenarioScope());
  }

  public function testCreatedUsersAreDeletedAndTheBatchIsDrained(): void {
    $backend = $this->createBackend([UserCapabilityInterface::class, BatchCapabilityInterface::class]);
    $backend->expects($this->once())->method('userDelete');
    $backend->expects($this->once())->method('processBatch');

    $user_registry = new UserRegistry();
    $user_registry->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($backend, $user_registry)->authCleanUsers($this->createAfterScenarioScope());

    $this->assertFalse($user_registry->hasUsers());
  }

  public function testUsersAreLeftBehindByIncapableBackend(): void {
    $user_registry = new UserRegistry();
    $user_registry->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(BackendInterface::class), $user_registry)->authCleanUsers($this->createAfterScenarioScope());

    $this->assertTrue($user_registry->hasUsers());
  }

  public function testSessionIsResetWhenManagerSupportsFastLogout(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authenticator */
    $authenticator = $this->createMockForIntersectionOfInterfaces([AuthenticatorInterface::class, FastLogoutInterface::class]);
    $authenticator->expects($this->once())->method('fastLogout');

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testKnownUserIsLoggedOutWithoutFastLogout(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logout');

    $user_registry = new UserRegistry();
    $user_registry->setCurrentUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(BackendInterface::class), $user_registry, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testAnAnonymousSessionIsLeftAloneWhenTheManagerHasNoFastLogout(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->never())->method('logout');

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testCreatedRolesAreDeleted(): void {
    $backend = $this->createBackend([RoleCapabilityInterface::class]);
    $backend->expects($this->exactly(2))->method('roleDelete');

    $context = $this->createContext($backend);
    $context->setRoles(['editor', 'reviewer']);

    $context->authCleanRoles($this->createAfterScenarioScope());

    $this->assertSame([], $context->getRoles());
  }

  public function testRolesAreLeftBehindByIncapableBackend(): void {
    $context = $this->createContext($this->createMock(BackendInterface::class));
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope());

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testNoRoleIsDeletedWhenNoneWasCreated(): void {
    $backend = $this->createBackend([RoleCapabilityInterface::class]);
    $backend->expects($this->never())->method('roleDelete');

    $this->createContext($backend)->authCleanRoles($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreClearedOnBackendTheScenarioReached(): void {
    $backend = $this->createBackend([CacheCapabilityInterface::class]);
    $backend->expects($this->once())->method('cacheClearStatic');

    $context = $this->createContext($backend);
    $context->backendFor(CacheCapabilityInterface::class);

    $context->staticCacheAfterScenario($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreSkippedOnBackendTheScenarioNeverReached(): void {
    $backend = $this->createBackend([CacheCapabilityInterface::class]);
    $backend->expects($this->never())->method('cacheClearStatic');

    $this->createContext($backend)->staticCacheAfterScenario($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreSkippedOnAnIncapableBackend(): void {
    $this->expectNotToPerformAssertions();

    $this->createContext($this->createMock(BackendInterface::class))->staticCacheAfterScenario($this->createAfterScenarioScope());
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

    $backend = $this->createContentBackend();
    $backend->expects($expected_cleanup ? $this->once() : $this->never())->method('nodeDelete');

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope());
  }

  public static function dataProviderCleanupOptOut(): \Iterator {
    yield 'empty value still cleans up' => ['', TRUE];
    yield 'unrecognized value still cleans up' => ['maybe', TRUE];
    yield 'zero still cleans up' => ['0', TRUE];
    yield 'one disables cleanup' => ['1', FALSE];
    yield 'true disables cleanup' => ['TRUE', FALSE];
    yield 'yes disables cleanup' => ['yes', FALSE];
    yield 'on disables cleanup' => [' On ', FALSE];
  }

  public function testTheOptOutAlsoSkipsUserCleanup(): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=1');

    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->never())->method('logout');

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authCleanUsers($this->createAfterScenarioScope());
  }

  public function testTheOptOutAlsoSkipsRoleCleanup(): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=1');

    $context = $this->createContext($this->createMock(BackendInterface::class));
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
    $backend = $this->createContentBackend();
    $backend->expects($this->never())->method('nodeDelete');

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope($scenario_tags, $feature_tags));

    $this->assertCount(1, $context->getCreatedStubs());
  }

  public static function dataProviderTheSkipTagDisablesEntityCleanup(): \Iterator {
    yield 'on the scenario' => [['behat-steps-skip:EntityLifecycleTrait'], []];
    yield 'on the feature' => [[], ['behat-steps-skip:EntityLifecycleTrait']];
  }

  public function testTheAuthSkipTagLeavesEntityCleanupRunning(): void {
    $backend = $this->createContentBackend();
    $backend->expects($this->once())->method('nodeDelete');

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope(['behat-steps-skip:AuthTrait']));

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testTheSkipTagDisablesUserCleanup(): void {
    $backend = $this->createBackend([UserCapabilityInterface::class]);
    $backend->expects($this->never())->method('userDelete');

    // The normal path calls 'fastLogout()' even for a scenario that created
    // no users, so the 'never()' expectation proves the early return ran.
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authenticator */
    $authenticator = $this->createMockForIntersectionOfInterfaces([AuthenticatorInterface::class, FastLogoutInterface::class]);
    $authenticator->expects($this->never())->method('fastLogout');

    $user_registry = new UserRegistry();
    $user_registry->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($backend, $user_registry, $authenticator)->authCleanUsers($this->createAfterScenarioScope(['behat-steps-skip:AuthTrait']));

    $this->assertTrue($user_registry->hasUsers());
  }

  public function testTheSkipTagDisablesRoleCleanup(): void {
    $backend = $this->createBackend([RoleCapabilityInterface::class]);
    $backend->expects($this->never())->method('roleDelete');

    $context = $this->createContext($backend);
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope(['behat-steps-skip:AuthTrait']));

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testTheEntitySkipTagLeavesRoleCleanupRunning(): void {
    $backend = $this->createBackend([RoleCapabilityInterface::class]);
    $backend->expects($this->once())->method('roleDelete');

    $context = $this->createContext($backend);
    $context->setRoles(['editor']);

    $context->authCleanRoles($this->createAfterScenarioScope(['behat-steps-skip:EntityLifecycleTrait']));

    $this->assertSame([], $context->getRoles());
  }

  public function testTheEntityCleanupSkipTagSparesOnlyTheNamedType(): void {
    $deleted = [];

    $backend = $this->createContentBackend();
    $backend->method('nodeDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'node';
    });
    $backend->method('termDelete')->willReturnCallback(static function (EntityStub $stub) use (&$deleted): void {
      $deleted[] = 'term';
    });

    $context = $this->createContext($backend);
    $context->setCreatedStubs([new EntityStub('taxonomy_term', 'tags'), new EntityStub('node', 'page')]);

    $context->entityLifecycleAfterScenario($this->createAfterScenarioScope(['behat-steps-entity-cleanup-skip:node']));

    $this->assertSame(['term'], $deleted);
  }

  public function testStringTimestampIsConvertedForInProcessBackend(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025 UTC']);
    $context = $this->createContext($this->createDrupalContentBackend());

    TestableRawContext::entityLifecycleBeforeNodeCreate(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame(strtotime('1 January 2025 UTC'), $stub->getValue('created'));
  }

  /**
   * Tests that a value the backend already accepts is not rewritten.
   *
   * @param mixed $value
   *   The value seeded on the timestamp field.
   */
  #[DataProvider('dataProviderNonTextualTimestampIsLeftAlone')]
  public function testNonTextualTimestampIsLeftAlone(mixed $value): void {
    $stub = new EntityStub('node', 'page', ['created' => $value]);
    $context = $this->createContext($this->createDrupalContentBackend());

    TestableRawContext::entityLifecycleBeforeNodeCreate(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame($value, $stub->getValue('created'));
  }

  public static function dataProviderNonTextualTimestampIsLeftAlone(): \Iterator {
    yield 'numeric timestamp' => ['1735689600'];
    yield 'empty value' => [''];
    yield 'absent value' => [NULL];
  }

  public function testUnreadableTimestampIsReported(): void {
    $stub = new EntityStub('node', 'page', ['created' => 'not a date at all']);
    $context = $this->createContext($this->createDrupalContentBackend());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to read the "created" value "not a date at all" as a date.');

    TestableRawContext::entityLifecycleBeforeNodeCreate(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));
  }

  public function testTimestampConversionIsSkippedForForeignContext(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $scope = new BeforeNodeCreateScope($this->createMock(Environment::class), $this->createMock(Context::class), $stub);

    TestableRawContext::entityLifecycleBeforeNodeCreate($scope);

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForRemoteBackend(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createMock(BackendInterface::class));

    TestableRawContext::entityLifecycleBeforeNodeCreate(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForOutOfProcessBackend(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createContentBackend());

    TestableRawContext::entityLifecycleBeforeNodeCreate(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testAnUnsavedEntityIsNotRegisteredForCleanup(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturn('nid');

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(NULL);
    $entity->method('getEntityType')->willReturn($entity_type);

    $context = $this->createContext($this->createContentBackend());
    $context->entityLifecycleRegister($entity);

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testLoginDelegatesToTheAuthenticator(): void {
    $user = new EntityStub('user', NULL, ['name' => 'alice']);

    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('login')->with($user);

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authLogin($user);
  }

  public function testLogoutDelegatesToTheAuthenticator(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logout');

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authLogout();
  }

  public function testFastLogoutIsUsedWhenAskedForAndSupported(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authenticator */
    $authenticator = $this->createMockForIntersectionOfInterfaces([AuthenticatorInterface::class, FastLogoutInterface::class]);
    $authenticator->expects($this->once())->method('fastLogout');
    $authenticator->expects($this->never())->method('logout');

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authLogout(TRUE);
  }

  public function testFastLogoutFallsBackWhenUnsupported(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->expects($this->once())->method('logout');

    $this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authLogout(TRUE);
  }

  public function testIsLoggedInDelegatesToTheAuthenticator(): void {
    $authenticator = $this->createMock(AuthenticatorInterface::class);
    $authenticator->method('isLoggedIn')->willReturn(TRUE);

    $this->assertTrue($this->createContext($this->createMock(BackendInterface::class), NULL, $authenticator)->authIsLoggedIn());
  }

  /**
   * Builds an initialized context over the given backend.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the registry returns.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface|null $user_registry
   *   The user registry, when the test inspects it.
   * @param \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface|null $authenticator
   *   The authenticator, when the test inspects it.
   * @param \Behat\Testwork\Hook\HookDispatcher|null $dispatcher
   *   The hook dispatcher, when the test needs one that finds hooks.
   */
  protected function createContext(BackendInterface $backend, ?UserRegistryInterface $user_registry = NULL, ?AuthenticatorInterface $authenticator = NULL, ?HookDispatcher $dispatcher = NULL): TestableRawContext {
    $environment = $this->createMock(Environment::class);
    // The fixture hooks are static, so the callee's own callable is enough
    // for the dispatcher to invoke them.
    $environment->method('bindCallee')->willReturnCallback(static fn(Callee $callee): mixed => $callee->getCallable());

    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);
    $backend_registry->setEnvironment($environment);

    $context = new TestableRawContext();
    $context->setBackendRegistry($backend_registry);
    $context->setHookDispatcher($dispatcher ?? $this->createHookDispatcher());
    $context->authSetUserRegistry($user_registry ?? new UserRegistry());
    $context->authSetAuthenticator($authenticator ?? $this->createMock(AuthenticatorInterface::class));

    return $context;
  }

  /**
   * Builds a backend double implementing the given capabilities.
   *
   * @param array<int, class-string> $capabilities
   *   The capability interfaces the backend should satisfy.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createBackend(array $capabilities): BackendInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, ...$capabilities]);

    return $backend;
  }

  /**
   * Builds a content-capable backend double.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createContentBackend(): BackendInterface&MockObject {
    return $this->createBackend([ContentCapabilityInterface::class]);
  }

  /**
   * Builds a backend double that saves content through Drupal's own storage.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createDrupalContentBackend(): BackendInterface&MockObject {
    return $this->createBackend([ContentCapabilityInterface::class, CoreCapabilityInterface::class]);
  }

}
