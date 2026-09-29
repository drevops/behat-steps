<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\ServiceContainer;

use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Behat\ServiceContainer\DriverPass;
use DrevOps\BehatSteps\Driver\BlackboxDriver;
use DrevOps\BehatSteps\Driver\Core\Core;
use DrevOps\BehatSteps\Driver\DrupalDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests that the compiler pass wires tagged drivers into the registry.
 */
#[CoversClass(DriverPass::class)]
class DriverPassTest extends TestCase {

  public function testWithoutTheManagerNothingIsProcessed(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.driver.blackbox', (new Definition(BlackboxDriver::class))->addTag('behat_steps.driver', ['alias' => 'blackbox']));

    (new DriverPass())->process($container);

    $this->assertFalse($container->hasDefinition('behat_steps.driver_registry'));
  }

  public function testTaggedDriversAreRegisteredUnderTheirAlias(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.driver.blackbox', (new Definition(BlackboxDriver::class))->addTag('behat_steps.driver', ['alias' => 'blackbox']));

    (new DriverPass())->process($container);

    $calls = $container->getDefinition('behat_steps.driver_registry')->getMethodCalls();

    $this->assertSame('registerDriver', $calls[0][0]);
    $this->assertSame('blackbox', $calls[0][1][0]);
    $this->assertEquals(new Reference('behat_steps.driver.blackbox'), $calls[0][1][1]);
  }

  public function testTaggedDriverWithoutAliasIsSkipped(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.driver.nameless', (new Definition(BlackboxDriver::class))->addTag('behat_steps.driver'));

    (new DriverPass())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.driver_registry')->getMethodCalls());
  }

  public function testRegisteredNamesAreCollectedInRegistrationOrder(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.driver.blackbox', (new Definition(BlackboxDriver::class))->addTag('behat_steps.driver', ['alias' => 'Blackbox']));
    $container->setDefinition('behat_steps.driver.drupal', (new Definition(DrupalDriver::class))->addTag('behat_steps.driver', ['alias' => 'drupal']));
    $container->setDefinition('behat_steps.driver.nameless', (new Definition(BlackboxDriver::class))->addTag('behat_steps.driver'));

    $this->assertSame(['blackbox', 'drupal'], DriverPass::registeredNames($container));
  }

  public function testRegisteredNamesAreEmptyWithoutTaggedDrivers(): void {
    $this->assertSame([], DriverPass::registeredNames($this->createContainer()));
  }

  public function testTheDrupalDriverReceivesTheTaggedCore(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.driver.drupal', (new Definition(DrupalDriver::class))->addTag('behat_steps.driver', ['alias' => 'drupal']));
    $container->setDefinition('behat_steps.driver.core', (new Definition(Core::class))->addTag('behat_steps.core'));

    (new DriverPass())->process($container);

    $this->assertEquals([['setCore', [new Reference('behat_steps.driver.core')]]], $container->getDefinition('behat_steps.driver.drupal')->getMethodCalls());
  }

  public function testTheDrupalDriverIsLeftAloneWhenNoCoreIsTagged(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.driver.drupal', (new Definition(DrupalDriver::class))->addTag('behat_steps.driver', ['alias' => 'drupal']));

    (new DriverPass())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.driver.drupal')->getMethodCalls());
  }

  public function testOnlyDrupalDriverReceivesCore(): void {
    $container = $this->createContainer();
    $container->setDefinition('behat_steps.driver.blackbox', (new Definition(BlackboxDriver::class))->addTag('behat_steps.driver', ['alias' => 'blackbox']));
    $container->setDefinition('behat_steps.driver.core', (new Definition(Core::class))->addTag('behat_steps.core'));

    (new DriverPass())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.driver.blackbox')->getMethodCalls());
  }

  /**
   * Builds a container holding the driver registry the pass looks for.
   */
  protected function createContainer(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.driver_registry', new Definition(DriverRegistry::class));

    return $container;
  }

}
