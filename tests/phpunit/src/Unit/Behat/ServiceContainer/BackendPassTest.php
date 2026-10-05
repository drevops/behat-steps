<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\ServiceContainer;

use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Behat\ServiceContainer\BackendPass;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests that the compiler pass wires tagged backends into the registry.
 */
#[CoversClass(BackendPass::class)]
class BackendPassTest extends UnitTestCase {

  public function testWithoutTheRegistryNothingIsProcessed(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'blackbox']));

    (new BackendPass())->process($container);

    $this->assertFalse($container->hasDefinition('behat_steps.backend_registry'));
  }

  public function testTaggedBackendsAreRegisteredUnderTheirAlias(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'blackbox']));

    (new BackendPass())->process($container);

    $calls = $container->getDefinition('behat_steps.backend_registry')->getMethodCalls();

    $this->assertSame('registerBackend', $calls[0][0]);
    $this->assertSame('blackbox', $calls[0][1][0]);
    $this->assertEquals(new Reference('behat_steps.backend.blackbox'), $calls[0][1][1]);
  }

  public function testTaggedBackendWithoutAliasIsSkipped(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.backend.nameless', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend'));

    (new BackendPass())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.backend_registry')->getMethodCalls());
  }

  public function testRegisteredNamesAreCollectedInRegistrationOrder(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'Blackbox']));
    $container->setDefinition('behat_steps.backend.drupal', (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $container->setDefinition('behat_steps.backend.nameless', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend'));

    $this->assertSame(['blackbox', 'drupal'], BackendPass::registeredNames($container));
  }

  public function testRegisteredNamesAreEmptyWithoutTaggedBackends(): void {
    $this->assertSame([], BackendPass::registeredNames($this->createContainer()));
  }

  public function testTheDrupalBackendReceivesTheTaggedCore(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.backend.drupal', (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $container->setDefinition('behat_steps.backend.core', (new Definition(Core::class))->addTag('behat_steps.core'));

    (new BackendPass())->process($container);

    $this->assertEquals([['setCore', [new Reference('behat_steps.backend.core')]]], $container->getDefinition('behat_steps.backend.drupal')->getMethodCalls());
  }

  public function testTheDrupalBackendIsLeftAloneWhenNoCoreIsTagged(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.backend.drupal', (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));

    (new BackendPass())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.backend.drupal')->getMethodCalls());
  }

  public function testOnlyDrupalBackendReceivesCore(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'blackbox']));
    $container->setDefinition('behat_steps.backend.core', (new Definition(Core::class))->addTag('behat_steps.core'));

    (new BackendPass())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.backend.blackbox')->getMethodCalls());
  }

  /**
   * Builds a container holding the backend registry the pass looks for.
   */
  protected function createContainer(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.backend_registry', new Definition(BackendRegistry::class));

    return $container;
  }

}
