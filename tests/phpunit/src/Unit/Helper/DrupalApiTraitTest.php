<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use Behat\Behat\Context\Context;
use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Call\Callee;
use Behat\Testwork\Call\Handler\RuntimeCallHandler;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Behat\Context\DrupalApiInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManager;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManager;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
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
use DrevOps\BehatSteps\Helper\DrupalApiTrait;
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
 * Tests the Drupal scenario lifecycle a context composes.
 *
 * The cleanup hooks read the opt-out and the skip tags from the host context,
 * so the run covers that class too.
 */
#[CoversTrait(DrupalApiTrait::class)]
#[CoversClass(WebRawContext::class)]
class DrupalApiTraitTest extends UnitTestCase {

  /**
   * A directory carrying the entry file the Drupal driver requires.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../fixtures/driver/drupal-root';

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

  public function testImplementsDrupalApiInterface(): void {
    $this->assertInstanceOf(DrupalApiInterface::class, new TestableRawContext());
  }

  public function testUninitializedContextNamesTheMissingUserManager(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The user manager is available only after Behat has initialized the context.');

    (new TestableRawContext())->getUserManager();
  }

  public function testNodeCreationDelegatesAndTracksTheStub(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('node', 'page', ['title' => 'A title']);
    $driver->expects($this->once())->method('nodeCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->nodeCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testTermCreationDelegatesAndTracksTheStub(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term']);
    $driver->expects($this->once())->method('termCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->termCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testAnEmptyTermParentIsDropped(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'parent' => '']);
    $driver->method('termCreate')->willReturn($stub);

    $this->createContext($driver)->termCreate($stub);

    $this->assertFalse($stub->hasValue('parent'));
  }

  public function testNamedTermParentIsKept(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'parent' => 'Another term']);
    $driver->method('termCreate')->willReturn($stub);

    $this->createContext($driver)->termCreate($stub);

    $this->assertSame('Another term', $stub->getValue('parent'));
  }

  public function testGenericEntityCreationDelegatesAndTracksTheStub(): void {
    $driver = $this->createContentDriver();
    $stub = new EntityStub('block_content', 'basic', ['info' => 'A block']);
    $driver->expects($this->once())->method('entityCreate')->with($stub)->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->entityCreate($stub));
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

    $this->createContext($driver)->nodeCreate($stub);

    $this->assertSame('A title', $stub->getValue('title'));
  }

  public function testUserCreationRegistersTheUser(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class]);
    $stub = new EntityStub('user', NULL, ['name' => 'alice']);
    $driver->expects($this->once())->method('userCreate')->with($stub);

    $user_manager = new UserManager();
    $context = $this->createContext($driver, $user_manager);

    $this->assertSame($stub, $context->userCreate($stub));
    $this->assertSame($stub, $user_manager->getUser('alice'));
  }

  public function testLanguageCreationTracksTheReturnedStub(): void {
    $driver = $this->createDriver([LanguageCapabilityInterface::class]);
    $stub = new EntityStub('language', NULL, ['langcode' => 'fr']);
    $driver->method('languageCreate')->willReturn($stub);

    $context = $this->createContext($driver);

    $this->assertSame($stub, $context->languageCreate($stub));
    $this->assertSame([$stub], $context->getCreatedStubs());
  }

  public function testAnExistingLanguageIsNotTracked(): void {
    $driver = $this->createDriver([LanguageCapabilityInterface::class]);
    $driver->method('languageCreate')->willReturn(FALSE);

    $context = $this->createContext($driver);

    $this->assertFalse($context->languageCreate(new EntityStub('language', NULL, ['langcode' => 'fr'])));
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
    yield 'node' => ['nodeCreate', new EntityStub('node'), ContentCapabilityInterface::class];
    yield 'term' => ['termCreate', new EntityStub('taxonomy_term'), ContentCapabilityInterface::class];
    yield 'entity' => ['entityCreate', new EntityStub('block_content'), ContentCapabilityInterface::class];
    yield 'user' => ['userCreate', new EntityStub('user'), UserCapabilityInterface::class];
    yield 'language' => ['languageCreate', new EntityStub('language'), LanguageCapabilityInterface::class];
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

    $context->nodeCreate(new EntityStub('node', 'page', ['title' => 'A title']));
  }

  public function testHooksCannotBeDispatchedBeforeInitialization(): void {
    $context = new TestableRawContext();
    $context->setDriverManager($this->createMock(DriverManagerInterface::class));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The hook dispatcher is available only after Behat has initialized the context.');

    $context->entityCreate(new EntityStub('block_content'));
  }

  public function testHooksCannotBeDispatchedBeforeScenarioStarts(): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->method('getEnvironment')->willReturn(NULL);

    $context = new TestableRawContext();
    $context->setDriverManager($driver_manager);
    $context->setDispatcher($this->createHookDispatcher());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Hooks can be dispatched only once a scenario has started.');

    $context->entityCreate(new EntityStub('block_content'));
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

    $context->cleanEntities($this->createAfterScenarioScope());

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

    $context->cleanEntities($this->createAfterScenarioScope());
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

    $context->cleanEntities($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testLanguageIsLeftBehindByIncapableDriver(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('entityDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('language', NULL, ['langcode' => 'fr'])]);

    $context->cleanEntities($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testEntitiesAreLeftBehindByIncapableDriver(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->cleanEntities($this->createAfterScenarioScope());

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testNothingIsDeletedWhenNoEntityWasCreated(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('entityDelete');

    $this->createContext($driver)->cleanEntities($this->createAfterScenarioScope());
  }

  public function testCreatedUsersAreDeletedAndTheBatchIsDrained(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class, BatchCapabilityInterface::class]);
    $driver->expects($this->once())->method('userDelete');
    $driver->expects($this->once())->method('processBatch');

    $user_manager = new UserManager();
    $user_manager->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($driver, $user_manager)->cleanUsers($this->createAfterScenarioScope());

    $this->assertFalse($user_manager->hasUsers());
  }

  public function testUsersAreLeftBehindByIncapableDriver(): void {
    $user_manager = new UserManager();
    $user_manager->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(DriverInterface::class), $user_manager)->cleanUsers($this->createAfterScenarioScope());

    $this->assertTrue($user_manager->hasUsers());
  }

  public function testSessionIsResetWhenManagerSupportsFastLogout(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authentication_manager */
    $authentication_manager = $this->createMockForIntersectionOfInterfaces([AuthenticationManagerInterface::class, FastLogoutInterface::class]);
    $authentication_manager->expects($this->once())->method('fastLogout');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->cleanUsers($this->createAfterScenarioScope());
  }

  public function testKnownUserIsLoggedOutWithoutFastLogout(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->once())->method('logOut');

    $user_manager = new UserManager();
    $user_manager->setCurrentUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(DriverInterface::class), $user_manager, $authentication_manager)->cleanUsers($this->createAfterScenarioScope());
  }

  public function testAnAnonymousSessionIsLeftAloneWhenTheManagerHasNoFastLogout(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->cleanUsers($this->createAfterScenarioScope());
  }

  public function testCreatedRolesAreDeleted(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->exactly(2))->method('roleDelete');

    $context = $this->createContext($driver);
    $context->setRoles(['editor', 'reviewer']);

    $context->cleanRoles($this->createAfterScenarioScope());

    $this->assertSame([], $context->getRoles());
  }

  public function testRolesAreLeftBehindByIncapableDriver(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setRoles(['editor']);

    $context->cleanRoles($this->createAfterScenarioScope());

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testNoRoleIsDeletedWhenNoneWasCreated(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->never())->method('roleDelete');

    $this->createContext($driver)->cleanRoles($this->createAfterScenarioScope());
  }

  public function testStaticCachesAreClearedOnDriverTheScenarioReached(): void {
    $driver = $this->createDriver([CacheCapabilityInterface::class]);
    $driver->expects($this->once())->method('cacheClearStatic');

    $context = $this->createContext($driver);
    $context->driverFor(CacheCapabilityInterface::class);

    $context->clearStaticCaches();
  }

  public function testStaticCachesAreSkippedOnDriverTheScenarioNeverReached(): void {
    $driver = $this->createDriver([CacheCapabilityInterface::class]);
    $driver->expects($this->never())->method('cacheClearStatic');

    $this->createContext($driver)->clearStaticCaches();
  }

  public function testStaticCachesAreSkippedOnAnIncapableDriver(): void {
    $this->expectNotToPerformAssertions();

    $this->createContext($this->createMock(DriverInterface::class))->clearStaticCaches();
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

    $context->cleanEntities($this->createAfterScenarioScope());
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

    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->cleanUsers($this->createAfterScenarioScope());
  }

  public function testTheOptOutAlsoSkipsRoleCleanup(): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=1');

    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setRoles(['editor']);

    $context->cleanRoles($this->createAfterScenarioScope());

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

    $context->cleanEntities($this->createAfterScenarioScope($scenario_tags, $feature_tags));

    $this->assertCount(1, $context->getCreatedStubs());
  }

  public static function dataProviderTheSkipTagDisablesEntityCleanup(): \Iterator {
    yield 'on the scenario' => [['behat-steps-skip:cleanEntities'], []];
    yield 'on the feature' => [[], ['behat-steps-skip:cleanEntities']];
  }

  public function testTheSkipTagDisablesUserCleanup(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class]);
    $driver->expects($this->never())->method('userDelete');

    // The normal path calls 'fastLogout()' even for a scenario that created
    // no users, so the 'never()' expectation proves the early return ran.
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authentication_manager */
    $authentication_manager = $this->createMockForIntersectionOfInterfaces([AuthenticationManagerInterface::class, FastLogoutInterface::class]);
    $authentication_manager->expects($this->never())->method('fastLogout');

    $user_manager = new UserManager();
    $user_manager->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($driver, $user_manager, $authentication_manager)->cleanUsers($this->createAfterScenarioScope(['behat-steps-skip:cleanUsers']));

    $this->assertTrue($user_manager->hasUsers());
  }

  public function testTheSkipTagDisablesRoleCleanup(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->never())->method('roleDelete');

    $context = $this->createContext($driver);
    $context->setRoles(['editor']);

    $context->cleanRoles($this->createAfterScenarioScope(['behat-steps-skip:cleanRoles']));

    $this->assertSame(['editor'], $context->getRoles());
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

    $context->cleanEntities($this->createAfterScenarioScope(['behat-steps-entity-cleanup-skip:node']));

    $this->assertSame(['term'], $deleted);
  }

  public function testStringTimestampIsConvertedForInProcessDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025 UTC']);
    $context = $this->createContext($this->createDrupalContentDriver());

    TestableRawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

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

    TestableRawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

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

    TestableRawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));
  }

  public function testTimestampConversionIsSkippedForForeignContext(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $scope = new BeforeNodeCreateScope($this->createMock(Environment::class), $this->createMock(Context::class), $stub);

    TestableRawContext::alterNodeParameters($scope);

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForRemoteDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createMock(DriverInterface::class));

    TestableRawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForOutOfProcessDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createContentDriver());

    TestableRawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testAnUnsavedEntityIsNotRegisteredForCleanup(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturn('nid');

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(NULL);
    $entity->method('getEntityType')->willReturn($entity_type);

    $context = $this->createContext($this->createContentDriver());
    $context->entityRegister($entity);

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testLoginDelegatesToTheAuthenticationManager(): void {
    $user = new EntityStub('user', NULL, ['name' => 'alice']);

    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->once())->method('logIn')->with($user);

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->login($user);
  }

  public function testLogoutDelegatesToTheAuthenticationManager(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->once())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->logout();
  }

  public function testFastLogoutIsUsedWhenAskedForAndSupported(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authentication_manager */
    $authentication_manager = $this->createMockForIntersectionOfInterfaces([AuthenticationManagerInterface::class, FastLogoutInterface::class]);
    $authentication_manager->expects($this->once())->method('fastLogout');
    $authentication_manager->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->logout(TRUE);
  }

  public function testFastLogoutFallsBackWhenUnsupported(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->once())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->logout(TRUE);
  }

  public function testLoggedInDelegatesToTheAuthenticationManager(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->method('loggedIn')->willReturn(TRUE);

    $this->assertTrue($this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->loggedIn());
  }

  /**
   * Builds an initialized context over the given driver.
   *
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver the manager hands out.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserManagerInterface|null $user_manager
   *   The user manager, when the test inspects it.
   * @param \DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface|null $authentication_manager
   *   The authentication manager, when the test inspects it.
   * @param \Behat\Testwork\Hook\HookDispatcher|null $dispatcher
   *   The hook dispatcher, when the test needs one that finds hooks.
   */
  protected function createContext(DriverInterface $driver, ?UserManagerInterface $user_manager = NULL, ?AuthenticationManagerInterface $authentication_manager = NULL, ?HookDispatcher $dispatcher = NULL): TestableRawContext {
    $environment = $this->createMock(Environment::class);
    // A real environment binds a callee to the context instance it holds; the
    // fixture hooks are static, so handing back the callee's own callable is
    // enough for the dispatcher to invoke them.
    $environment->method('bindCallee')->willReturnCallback(static fn(Callee $callee): mixed => $callee->getCallable());

    $driver_manager = new DriverManager(['test' => $driver]);
    $driver_manager->setScenarioDrivers(['test' => 'test']);
    $driver_manager->setEnvironment($environment);

    $context = new TestableRawContext();
    $context->setDriverManager($driver_manager);
    $context->setDispatcher($dispatcher ?? $this->createHookDispatcher());
    $context->setUserManager($user_manager ?? new UserManager());
    $context->setAuthenticationManager($authentication_manager ?? $this->createMock(AuthenticationManagerInterface::class));

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
