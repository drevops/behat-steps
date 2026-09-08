<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Testwork\Call\Callee;
use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Call\Handler\RuntimeCallHandler;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManager;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriver;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableRawContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ThrowingHookReader;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the scenario lifecycle the base context owns.
 */
#[CoversClass(RawContext::class)]
class RawContextTest extends UnitTestCase {

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

  public function testImplementsDriverAwareInterface(): void {
    $this->assertInstanceOf(DriverAwareInterface::class, new RawContext());
  }

  /**
   * Tests that an uninitialized context reports what it is missing.
   *
   * @param string $method
   *   The accessor to call on an uninitialized context.
   * @param string $expected_message
   *   The message the accessor is expected to throw with.
   */
  #[DataProvider('dataProviderUninitializedContextNamesMissingCollaborator')]
  public function testUninitializedContextNamesMissingCollaborator(string $method, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    (new RawContext())->$method();
  }

  public static function dataProviderUninitializedContextNamesMissingCollaborator(): \Iterator {
    yield 'driver manager' => ['getDriverManager', 'The driver manager is available only after Behat has initialized the context.'];
    yield 'user manager' => ['getUserManager', 'The user manager is available only after Behat has initialized the context.'];
    yield 'authentication manager' => ['getAuthenticationManager', 'The authentication manager is available only after Behat has initialized the context.'];
  }

  public function testTheDriverComesFromTheManager(): void {
    $driver = $this->createMock(DriverInterface::class);
    $context = $this->createContext($driver);

    $this->assertSame($driver, $context->getDriver());
  }

  public function testTheRandomGeneratorComesFromTheDriver(): void {
    $random = new Random();
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('getRandom')->willReturn($random);

    $this->assertSame($random, $this->createContext($driver)->getRandom());
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
   * @param string $expected_message
   *   The message the guard is expected to throw with.
   */
  #[DataProvider('dataProviderCreationRefusesIncapableDriver')]
  public function testCreationRefusesIncapableDriver(string $method, EntityStub $stub, string $expected_message): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $context->$method($stub);
  }

  public static function dataProviderCreationRefusesIncapableDriver(): \Iterator {
    yield 'node' => ['nodeCreate', new EntityStub('node'), 'does not support content creation.'];
    yield 'term' => ['termCreate', new EntityStub('taxonomy_term'), 'does not support content creation.'];
    yield 'entity' => ['entityCreate', new EntityStub('block_content'), 'does not support content creation.'];
    yield 'user' => ['userCreate', new EntityStub('user'), 'does not support user creation.'];
    yield 'language' => ['languageCreate', new EntityStub('language'), 'does not support language management.'];
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

    $context->cleanEntities();

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

    $context->cleanEntities();
  }

  public static function dataProviderLanguageIsRemovedThroughLanguageCapability(): \Iterator {
    yield 'language' => ['language'];
    yield 'configurable_language' => ['configurable_language'];
  }

  public function testLanguageIsLeftBehindByIncapableDriver(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('entityDelete');

    $context = $this->createContext($driver);
    $context->setCreatedStubs([new EntityStub('language', NULL, ['langcode' => 'fr'])]);

    $context->cleanEntities();

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testEntitiesAreLeftBehindByIncapableDriver(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setCreatedStubs([new EntityStub('node', 'page')]);

    $context->cleanEntities();

    $this->assertSame([], $context->getCreatedStubs());
  }

  public function testNothingIsDeletedWhenNoEntityWasCreated(): void {
    $driver = $this->createContentDriver();
    $driver->expects($this->never())->method('entityDelete');

    $this->createContext($driver)->cleanEntities();
  }

  public function testCreatedUsersAreDeletedAndTheBatchIsDrained(): void {
    $driver = $this->createDriver([UserCapabilityInterface::class, BatchCapabilityInterface::class]);
    $driver->expects($this->once())->method('userDelete');
    $driver->expects($this->once())->method('processBatch');

    $user_manager = new UserManager();
    $user_manager->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($driver, $user_manager)->cleanUsers();

    $this->assertFalse($user_manager->hasUsers());
  }

  public function testUsersAreLeftBehindByIncapableDriver(): void {
    $user_manager = new UserManager();
    $user_manager->addUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(DriverInterface::class), $user_manager)->cleanUsers();

    $this->assertTrue($user_manager->hasUsers());
  }

  public function testSessionIsResetWhenManagerSupportsFastLogout(): void {
    /** @var \DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface&\DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface&\PHPUnit\Framework\MockObject\MockObject $authentication_manager */
    $authentication_manager = $this->createMockForIntersectionOfInterfaces([AuthenticationManagerInterface::class, FastLogoutInterface::class]);
    $authentication_manager->expects($this->once())->method('fastLogout');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->cleanUsers();
  }

  public function testKnownUserIsLoggedOutWithoutFastLogout(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->once())->method('logOut');

    $user_manager = new UserManager();
    $user_manager->setCurrentUser(new EntityStub('user', NULL, ['name' => 'alice']));

    $this->createContext($this->createMock(DriverInterface::class), $user_manager, $authentication_manager)->cleanUsers();
  }

  public function testAnAnonymousSessionIsLeftAloneWhenTheManagerHasNoFastLogout(): void {
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $authentication_manager->expects($this->never())->method('logOut');

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->cleanUsers();
  }

  public function testCreatedRolesAreDeleted(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->exactly(2))->method('roleDelete');

    $context = $this->createContext($driver);
    $context->setRoles(['editor', 'reviewer']);

    $context->cleanRoles();

    $this->assertSame([], $context->getRoles());
  }

  public function testRolesAreLeftBehindByIncapableDriver(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setRoles(['editor']);

    $context->cleanRoles();

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testNoRoleIsDeletedWhenNoneWasCreated(): void {
    $driver = $this->createDriver([RoleCapabilityInterface::class]);
    $driver->expects($this->never())->method('roleDelete');

    $this->createContext($driver)->cleanRoles();
  }

  public function testStaticCachesAreClearedOnCacheCapableDriver(): void {
    $driver = $this->createDriver([CacheCapabilityInterface::class]);
    $driver->expects($this->once())->method('cacheClearStatic');

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

    $context->cleanEntities();
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

    $this->createContext($this->createMock(DriverInterface::class), NULL, $authentication_manager)->cleanUsers();
  }

  public function testTheOptOutAlsoSkipsRoleCleanup(): void {
    putenv('BEHAT_STEPS_DISABLE_CLEANUP=1');

    $context = $this->createContext($this->createMock(DriverInterface::class));
    $context->setRoles(['editor']);

    $context->cleanRoles();

    $this->assertSame(['editor'], $context->getRoles());
  }

  public function testStringTimestampIsConvertedForInProcessDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025 UTC']);
    $context = $this->createContext(new DrupalDriver(self::DRUPAL_ROOT, 'default'));

    RawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

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
    $context = $this->createContext(new DrupalDriver(self::DRUPAL_ROOT, 'default'));

    RawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame($value, $stub->getValue('created'));
  }

  public static function dataProviderNonTextualTimestampIsLeftAlone(): \Iterator {
    yield 'numeric timestamp' => ['1735689600'];
    yield 'empty value' => [''];
    yield 'absent value' => [NULL];
  }

  public function testTimestampConversionIsSkippedForForeignContext(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $scope = new BeforeNodeCreateScope($this->createMock(Environment::class), $this->createMock(Context::class), $stub);

    RawContext::alterNodeParameters($scope);

    $this->assertSame('1 January 2025', $stub->getValue('created'));
  }

  public function testTimestampConversionIsSkippedForRemoteDriver(): void {
    $stub = new EntityStub('node', 'page', ['created' => '1 January 2025']);
    $context = $this->createContext($this->createMock(DriverInterface::class));

    RawContext::alterNodeParameters(new BeforeNodeCreateScope($this->createMock(Environment::class), $context, $stub));

    $this->assertSame('1 January 2025', $stub->getValue('created'));
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

    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->method('getDriver')->willReturn($driver);
    $driver_manager->method('getEnvironment')->willReturn($environment);

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

}
