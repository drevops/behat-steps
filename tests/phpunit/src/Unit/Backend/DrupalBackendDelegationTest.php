<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\AuthCapableCoreInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises every 'DrupalBackend' public method to guarantee line coverage.
 *
 * 'DrupalBackend' is a thin facade over 'CoreInterface'; the tests here verify
 * that each method delegates to the corresponding core method. Kernel tests
 * under 'Kernel/Backend/Core/' exercise the behavior end-to-end.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
class DrupalBackendDelegationTest extends UnitTestCase {

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

    yield 'createUser' => ['createUser', [$user], 'createUser'];
    yield 'deleteUser' => ['deleteUser', [$user], 'deleteUser'];
    yield 'addUserRole' => ['addUserRole', [$user, 'admin'], 'addUserRole'];
    yield 'processBatch' => ['processBatch', [], 'processBatch'];
    yield 'cacheClear' => ['cacheClear', ['all'], 'cacheClear'];
    yield 'cacheClearStatic' => ['cacheClearStatic', [], 'cacheClearStatic'];
    yield 'createNode' => ['createNode', [$node], 'createNode'];
    yield 'deleteNode' => ['deleteNode', [$node], 'deleteNode'];
    yield 'cronRun' => ['cronRun', [], 'cronRun'];
    yield 'createTerm' => ['createTerm', [$term], 'createTerm'];
    yield 'deleteTerm' => ['deleteTerm', [$term], 'deleteTerm'];
    yield 'createRole' => ['createRole', [['admin']], 'createRole'];
    yield 'createRole named' => ['createRole', [['admin'], 'editor', 'Editor'], 'createRole'];
    yield 'deleteRole' => ['deleteRole', ['editor'], 'deleteRole'];
    yield 'createLanguage' => ['createLanguage', [$language], 'createLanguage'];
    yield 'deleteLanguage' => ['deleteLanguage', [$language], 'deleteLanguage'];
    yield 'configGet' => ['configGet', ['system.site', 'name'], 'configGet'];
    yield 'configGetOriginal' => ['configGetOriginal', ['system.site', 'name'], 'configGetOriginal'];
    yield 'configSet' => ['configSet', ['system.site', 'name', 'v'], 'configSet'];
    yield 'configExists' => ['configExists', ['system.site'], 'configExists'];
    yield 'configGetData' => ['configGetData', ['system.site'], 'configGetData'];
    yield 'configSetData' => ['configSetData', ['system.site', ['name' => 'v']], 'configSetData'];
    yield 'configDelete' => ['configDelete', ['system.site'], 'configDelete'];
    yield 'stateGet' => ['stateGet', ['my.key'], 'stateGet'];
    yield 'stateSet' => ['stateSet', ['my.key', 'v'], 'stateSet'];
    yield 'stateDelete' => ['stateDelete', ['my.key'], 'stateDelete'];
    yield 'stateExists' => ['stateExists', ['my.key'], 'stateExists'];
    yield 'createEntity' => ['createEntity', [$entity], 'createEntity'];
    yield 'deleteEntity' => ['deleteEntity', [$entity], 'deleteEntity'];
    yield 'placeBlock' => ['placeBlock', [$block], 'placeBlock'];
    yield 'deleteBlock' => ['deleteBlock', [$block], 'deleteBlock'];
    yield 'createBlockContent' => ['createBlockContent', [$block_content], 'createBlockContent'];
    yield 'deleteBlockContent' => ['deleteBlockContent', [$block_content], 'deleteBlockContent'];
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
    yield 'moduleIsEnabled' => ['moduleIsEnabled', ['node'], 'moduleIsEnabled'];
    yield 'moduleIsPresent' => ['moduleIsPresent', ['node'], 'moduleIsPresent'];
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

    $root_property = $reflection->getProperty('drupalRoot');
    $root_property->setValue($backend, __DIR__);

    $uri_property = $reflection->getProperty('uri');
    $uri_property->setValue($backend, 'default');

    $version_property = $reflection->getProperty('version');
    $version_property->setValue($backend, $version);

    $backend->setCore($core);

    return $backend;
  }

}
