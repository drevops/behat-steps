<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistry;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the backend registry the extension hands to every context.
 */
#[CoversClass(BackendRegistry::class)]
class BackendRegistryTest extends TestCase {

  public function testImplementsInterface(): void {
    $registry = new BackendRegistry();

    $this->assertInstanceOf(BackendRegistryInterface::class, $registry);
  }

  public function testConstructorRegistersBackends(): void {
    $backend = $this->createBackendMock(TRUE);

    $registry = new BackendRegistry(['Alpha' => $backend]);

    $this->assertSame(['alpha' => $backend], $registry->getBackends());
  }

  public function testRegisterBackendLowercasesName(): void {
    $backend = $this->createBackendMock(TRUE);
    $registry = new BackendRegistry();

    $registry->registerBackend('FooBar', $backend);

    $this->assertArrayHasKey('foobar', $registry->getBackends());
  }

  public function testGetBackendsReturnsEmptyByDefault(): void {
    $registry = new BackendRegistry();

    $this->assertSame([], $registry->getBackends());
  }

  public function testScenarioBackendsAreEmptyByDefault(): void {
    $registry = new BackendRegistry();

    $this->assertSame([], $registry->getScenarioBackends());
  }

  public function testScenarioBackendsKeepTheOrderTheyWereGivenIn(): void {
    $registry = new BackendRegistry(['a' => $this->createBackendMock(TRUE), 'b' => $this->createBackendMock(TRUE)]);

    $registry->setScenarioBackends(['second' => 'B', 'FIRST' => 'a']);

    $this->assertSame(['second' => 'b', 'first' => 'a'], $registry->getScenarioBackends());
  }

  public function testSetScenarioBackendsRejectsAnUnregisteredBackend(): void {
    $registry = new BackendRegistry(['a' => $this->createBackendMock(TRUE)]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Backend "ghost" is not registered. Registered backends: a.');

    $registry->setScenarioBackends(['ghost' => 'ghost']);
  }

  public function testSetScenarioBackendsReportsWhenNothingIsRegistered(): void {
    $registry = new BackendRegistry();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Registered backends: none.');

    $registry->setScenarioBackends(['ghost' => 'ghost']);
  }

  public function testGetBackendResolvesTheTagName(): void {
    $backend = $this->createBackendMock(TRUE);
    $registry = new BackendRegistry(['acme-jsonapi' => $backend]);
    $registry->setScenarioBackends(['api' => 'acme-jsonapi']);

    $this->assertSame($backend, $registry->getBackend('API'));
  }

  public function testGetBackendRejectsNameOutsideTheScenarioOrder(): void {
    $registry = new BackendRegistry(['a' => $this->createBackendMock(TRUE), 'b' => $this->createBackendMock(TRUE)]);
    $registry->setScenarioBackends(['a' => 'a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Backend "b" is not available to this scenario. Available backends: a.');

    $registry->getBackend('b');
  }

  public function testGetBackendReportsAnEmptyScenarioOrder(): void {
    $registry = new BackendRegistry();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Available backends: none.');

    $registry->getBackend('a');
  }

  public function testGetBackendBootstrapsWhenNeeded(): void {
    $backend = $this->createBackendMock(FALSE);
    $backend->expects($this->once())->method('bootstrap');
    $registry = new BackendRegistry(['test' => $backend]);
    $registry->setScenarioBackends(['test' => 'test']);

    $registry->getBackend('test');
  }

  public function testGetBackendSkipsBootstrapWhenAlreadyBootstrapped(): void {
    $backend = $this->createBackendMock(TRUE);
    $backend->expects($this->never())->method('bootstrap');
    $registry = new BackendRegistry(['test' => $backend]);
    $registry->setScenarioBackends(['test' => 'test']);

    $registry->getBackend('test');
  }

  public function testGetBackendForReturnsTheFirstBackendWithTheCapability(): void {
    $first = $this->createCacheBackendMock(TRUE);
    $second = $this->createCacheBackendMock(TRUE);
    $registry = new BackendRegistry(['first' => $first, 'second' => $second]);
    $registry->setScenarioBackends(['first' => 'first', 'second' => 'second']);

    $this->assertSame($first, $registry->getBackendFor(CacheCapabilityInterface::class));
  }

  public function testGetBackendForSkipsBackendsWithoutTheCapability(): void {
    $plain = $this->createBackendMock(TRUE);
    $capable = $this->createCacheBackendMock(TRUE);
    $registry = new BackendRegistry(['plain' => $plain, 'capable' => $capable]);
    $registry->setScenarioBackends(['plain' => 'plain', 'capable' => 'capable']);

    $this->assertSame($capable, $registry->getBackendFor(CacheCapabilityInterface::class));
  }

  public function testGetBackendForBootstrapsOnlyTheBackendItReturns(): void {
    $plain = $this->createBackendMock(FALSE);
    $plain->expects($this->never())->method('bootstrap');

    $capable = $this->createCacheBackendMock(FALSE);
    $capable->expects($this->once())->method('bootstrap');

    $registry = new BackendRegistry(['plain' => $plain, 'capable' => $capable]);
    $registry->setScenarioBackends(['plain' => 'plain', 'capable' => 'capable']);

    $registry->getBackendFor(CacheCapabilityInterface::class);
  }

  public function testGetBackendForNamesTheCapabilityAndTheOrderWhenNoneMatches(): void {
    $registry = new BackendRegistry(['plain' => $this->createBackendMock(TRUE)]);
    $registry->setScenarioBackends(['plain' => 'plain']);

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessage(sprintf('No backend provides "%s". Backends available to this scenario, in order: plain.', CacheCapabilityInterface::class));

    $registry->getBackendFor(CacheCapabilityInterface::class);
  }

  public function testHasCapabilityReadsTheScenarioOrderWithoutBootstrapping(): void {
    $capable = $this->createCacheBackendMock(FALSE);
    $capable->expects($this->never())->method('bootstrap');

    $registry = new BackendRegistry(['capable' => $capable]);
    $registry->setScenarioBackends(['capable' => 'capable']);

    $this->assertTrue($registry->hasCapability(CacheCapabilityInterface::class));
    $this->assertFalse($registry->hasCapability(ContentCapabilityInterface::class));
  }

  public function testHasCapabilityIgnoresRegisteredBackendOutsideTheScenarioOrder(): void {
    $registry = new BackendRegistry(['capable' => $this->createCacheBackendMock(TRUE), 'plain' => $this->createBackendMock(TRUE)]);
    $registry->setScenarioBackends(['plain' => 'plain']);

    $this->assertFalse($registry->hasCapability(CacheCapabilityInterface::class));
  }

  public function testGetResolvedBackendForReturnsNullUntilStepAsksForIt(): void {
    $registry = new BackendRegistry(['capable' => $this->createCacheBackendMock(TRUE)]);
    $registry->setScenarioBackends(['capable' => 'capable']);

    $this->assertNull($registry->getResolvedBackendFor(CacheCapabilityInterface::class));
  }

  public function testGetResolvedBackendForReturnsBackendTheScenarioResolved(): void {
    $capable = $this->createCacheBackendMock(TRUE);
    $registry = new BackendRegistry(['capable' => $capable]);
    $registry->setScenarioBackends(['capable' => 'capable']);

    $registry->getBackendFor(CacheCapabilityInterface::class);

    $this->assertSame($capable, $registry->getResolvedBackendFor(CacheCapabilityInterface::class));
    $this->assertNull($registry->getResolvedBackendFor(ContentCapabilityInterface::class));
  }

  public function testGetResolvedBackendForAnswersInScenarioOrder(): void {
    $first = $this->createCacheBackendMock(TRUE);
    $second = $this->createCacheBackendMock(TRUE);
    $registry = new BackendRegistry(['first' => $first, 'second' => $second]);
    $registry->setScenarioBackends(['first' => 'first', 'second' => 'second']);

    // Reached in the reverse of the scenario's order, as a step asking for a
    // capability only the second backend provides would do.
    $registry->getBackend('second');
    $registry->getBackend('first');

    $this->assertSame($first, $registry->getResolvedBackendFor(CacheCapabilityInterface::class));
  }

  public function testGetResolvedBackendForSkipsAnUnreachedBackendAheadInTheOrder(): void {
    $first = $this->createCacheBackendMock(TRUE);
    $second = $this->createCacheBackendMock(TRUE);
    $registry = new BackendRegistry(['first' => $first, 'second' => $second]);
    $registry->setScenarioBackends(['first' => 'first', 'second' => 'second']);

    $registry->getBackend('second');

    $this->assertSame($second, $registry->getResolvedBackendFor(CacheCapabilityInterface::class));
  }

  public function testTheNextScenarioForgetsWhatThePreviousOneResolved(): void {
    $capable = $this->createCacheBackendMock(TRUE);
    $registry = new BackendRegistry(['capable' => $capable]);
    $registry->setScenarioBackends(['capable' => 'capable']);
    $registry->getBackendFor(CacheCapabilityInterface::class);

    $registry->setScenarioBackends(['capable' => 'capable']);

    $this->assertNull($registry->getResolvedBackendFor(CacheCapabilityInterface::class));
  }

  public function testGetEnvironmentReturnsNullByDefault(): void {
    $registry = new BackendRegistry();

    $this->assertNull($registry->getEnvironment());
  }

  public function testSetAndGetEnvironment(): void {
    $environment = $this->createMock(Environment::class);
    $registry = new BackendRegistry();

    $registry->setEnvironment($environment);

    $this->assertSame($environment, $registry->getEnvironment());
  }

  /**
   * Creates a backend double reporting the given bootstrap state.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createBackendMock(bool $bootstrapped): BackendInterface&MockObject {
    $backend = $this->createMock(BackendInterface::class);
    $backend->method('isBootstrapped')->willReturn($bootstrapped);

    return $backend;
  }

  /**
   * Creates a cache-capable backend double reporting the bootstrap state.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createCacheBackendMock(bool $bootstrapped): BackendInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, CacheCapabilityInterface::class]);
    $backend->method('isBootstrapped')->willReturn($bootstrapped);

    return $backend;
  }

}
