<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\AuthCapableCoreInterface;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every 'DrupalBackend' public method to guarantee line coverage.
 *
 * 'DrupalBackend' is a thin facade over 'CoreInterface'; the tests here verify
 * that each method delegates to the corresponding core method. Kernel tests
 * under 'Kernel/Backend/Core/' exercise the behaviour end-to-end.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
class DrupalBackendDelegationTest extends TestCase {

  public function testGetCoreReturnsInjectedCore(): void {
    $core = $this->createMock(CoreInterface::class);
    $backend = $this->createBackendWithCore($core);

    $this->assertSame($core, $backend->getCore());
  }

  public function testGetRandomDelegatesToCore(): void {
    $random = new Random();
    $core = $this->createMock(CoreInterface::class);
    $core->expects($this->once())->method('getRandom')->willReturn($random);

    $backend = $this->createBackendWithCore($core);

    $this->assertSame($random, $backend->getRandom());
  }

  /**
   * Tests that 'bootstrap()' calls the core and flips the bootstrapped flag.
   */
  public function testBootstrapDelegatesToCore(): void {
    $core = $this->createMock(CoreInterface::class);
    $core->expects($this->once())->method('bootstrap');

    $backend = $this->createBackendWithCore($core);
    $this->assertFalse($backend->isBootstrapped());

    $backend->bootstrap();

    $this->assertTrue($backend->isBootstrapped());
  }

  /**
   * Tests that 'setCore()' assigns the injected instance verbatim.
   */
  public function testSetCoreAssignsInjectedInstance(): void {
    $backend = $this->createBackendWithCore($this->createMock(CoreInterface::class));
    $custom = $this->createMock(CoreInterface::class);

    $backend->setCore($custom);

    $this->assertSame($custom, $backend->getCore());
  }

  public function testLoginDelegatesToAuthCapableCore(): void {
    $stub = new EntityStub('user');
    $core = $this->createMock(AuthCapableCoreInterface::class);
    $core->expects($this->once())->method('login')->with($stub);

    $backend = $this->createBackendWithCore($core);

    $backend->login($stub);
  }

  public function testLogoutDelegatesToAuthCapableCore(): void {
    $core = $this->createMock(AuthCapableCoreInterface::class);
    $core->expects($this->once())->method('logout');

    $backend = $this->createBackendWithCore($core);

    $backend->logout();
  }

  public function testLoginThrowsWithNonAuthCore(): void {
    $backend = $this->createBackendWithCore($this->createMock(CoreInterface::class));

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessageMatches('/Authentication is not supported by/');

    $backend->login(new EntityStub('user'));
  }

  public function testLogoutThrowsWithNonAuthCore(): void {
    $backend = $this->createBackendWithCore($this->createMock(CoreInterface::class));

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessageMatches('/Authentication is not supported by/');

    $backend->logout();
  }

  /**
   * Tests that every delegating method forwards to the matching core method.
   *
   * @param string $backend_method
   *   The 'DrupalBackend' method to invoke.
   * @param array<int, mixed> $args
   *   Positional arguments.
   * @param string $core_method
   *   The expected core method to be invoked with the same args.
   */
  #[DataProvider('dataProviderForwardsToCore')]
  public function testForwardsToCore(string $backend_method, array $args, string $core_method): void {
    $core = $this->createMock(CoreInterface::class);
    $core->expects($this->once())->method($core_method)->with(...$args);

    $backend = $this->createBackendWithCore($core);
    $backend->{$backend_method}(...$args);
  }

  /**
   * Data provider listing every delegating method and its arguments.
   */
  public static function dataProviderForwardsToCore(): \Iterator {
    $user = new EntityStub('user');
    $node = new EntityStub('node', 'article');
    $term = new EntityStub('taxonomy_term', 'tags');
    $entity = new EntityStub('node', 'article');
    $language = new EntityStub('language', NULL, ['langcode' => 'fr']);
    $block = new EntityStub('block');
    $block_content = new EntityStub('block_content', 'basic');

    yield 'userCreate' => ['userCreate', [$user], 'userCreate'];
    yield 'userDelete' => ['userDelete', [$user], 'userDelete'];
    yield 'userAddRole' => ['userAddRole', [$user, 'admin'], 'userAddRole'];
    yield 'processBatch' => ['processBatch', [], 'processBatch'];
    yield 'cacheClear' => ['cacheClear', ['all'], 'cacheClear'];
    yield 'cacheClearStatic' => ['cacheClearStatic', [], 'cacheClearStatic'];
    yield 'nodeCreate' => ['nodeCreate', [$node], 'nodeCreate'];
    yield 'nodeDelete' => ['nodeDelete', [$node], 'nodeDelete'];
    yield 'cronRun' => ['cronRun', [], 'cronRun'];
    yield 'termCreate' => ['termCreate', [$term], 'termCreate'];
    yield 'termDelete' => ['termDelete', [$term], 'termDelete'];
    yield 'roleCreate' => ['roleCreate', [['admin']], 'roleCreate'];
    yield 'roleCreate named' => ['roleCreate', [['admin'], 'editor', 'Editor'], 'roleCreate'];
    yield 'roleDelete' => ['roleDelete', ['editor'], 'roleDelete'];
    yield 'languageCreate' => ['languageCreate', [$language], 'languageCreate'];
    yield 'languageDelete' => ['languageDelete', [$language], 'languageDelete'];
    yield 'configGet' => ['configGet', ['system.site', 'name'], 'configGet'];
    yield 'configGetOriginal' => ['configGetOriginal', ['system.site', 'name'], 'configGetOriginal'];
    yield 'configSet' => ['configSet', ['system.site', 'name', 'v'], 'configSet'];
    yield 'entityCreate' => ['entityCreate', [$entity], 'entityCreate'];
    yield 'entityDelete' => ['entityDelete', [$entity], 'entityDelete'];
    yield 'blockPlace' => ['blockPlace', [$block], 'blockPlace'];
    yield 'blockDelete' => ['blockDelete', [$block], 'blockDelete'];
    yield 'blockContentCreate' => ['blockContentCreate', [$block_content], 'blockContentCreate'];
    yield 'blockContentDelete' => ['blockContentDelete', [$block_content], 'blockContentDelete'];
    yield 'mailStartCollecting' => ['mailStartCollecting', [], 'mailStartCollecting'];
    yield 'mailStopCollecting' => ['mailStopCollecting', [], 'mailStopCollecting'];
    yield 'mailGet' => ['mailGet', [], 'mailGet'];
    yield 'mailClear' => ['mailClear', [], 'mailClear'];
    yield 'mailSend' => ['mailSend', ['body', 'subject', 'to@ex.co', 'en'], 'mailSend'];
    yield 'mailSend with attachments' => [
      'mailSend',
      ['body', 'subject', 'to@ex.co', 'en', [['filename' => 'doc.pdf']]],
      'mailSend',
    ];
    yield 'moduleInstall' => ['moduleInstall', ['node'], 'moduleInstall'];
    yield 'moduleUninstall' => ['moduleUninstall', ['node'], 'moduleUninstall'];
  }

  /**
   * Creates a 'DrupalBackend' with an injected core and a fixed version.
   *
   * Bypasses the constructor (which requires a real Drupal installation) and
   * sets the protected properties directly via reflection.
   */
  protected function createBackendWithCore(CoreInterface $core, int $version = 11): DrupalBackend {
    $reflection = new \ReflectionClass(DrupalBackend::class);
    /** @var \DrevOps\BehatSteps\Backend\DrupalBackend $backend */
    $backend = $reflection->newInstanceWithoutConstructor();

    $root = $reflection->getProperty('drupalRoot');
    $root->setValue($backend, __DIR__);

    $uri = $reflection->getProperty('uri');
    $uri->setValue($backend, 'default');

    $version_property = $reflection->getProperty('version');
    $version_property->setValue($backend, $version);

    $backend->setCore($core);

    return $backend;
  }

}
