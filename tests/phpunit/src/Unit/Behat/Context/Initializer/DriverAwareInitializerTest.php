<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Context\Initializer\DriverAwareInitializer;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests which collaborators a context receives, by the interfaces it declares.
 */
#[CoversClass(DriverAwareInitializer::class)]
class DriverAwareInitializerTest extends UnitTestCase {

  /**
   * Parameters the initializer is constructed with.
   */
  protected const PARAMETERS = ['api_driver' => 'drush'];

  public function testPlainContextIsLeftAlone(): void {
    $context = $this->createMock(Context::class);

    $this->createInitializer()->initializeContext($context);

    $this->assertInstanceOf(Context::class, $context);
  }

  public function testParametersAwareContextReceivesOnlyParameters(): void {
    /** @var \Behat\Behat\Context\Context&\DrevOps\BehatSteps\Behat\ParametersAwareInterface&\PHPUnit\Framework\MockObject\MockObject $context */
    $context = $this->createMockForIntersectionOfInterfaces([Context::class, ParametersAwareInterface::class]);
    $context->expects($this->once())->method('setParameters')->with(self::PARAMETERS);

    $this->createInitializer()->initializeContext($context);
  }

  public function testDriverAwareContextReceivesEveryCollaborator(): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $dispatcher = $this->createHookDispatcher();
    $authentication_manager = $this->createMock(AuthenticationManagerInterface::class);
    $user_manager = $this->createMock(UserManagerInterface::class);

    $context = $this->createMock(DriverAwareInterface::class);
    $context->expects($this->once())->method('setParameters')->with(self::PARAMETERS);
    $context->expects($this->once())->method('setDriverManager')->with($driver_manager);
    $context->expects($this->once())->method('setDispatcher')->with($dispatcher);
    $context->expects($this->once())->method('setAuthenticationManager')->with($authentication_manager);
    $context->expects($this->once())->method('setUserManager')->with($user_manager);

    $initializer = new DriverAwareInitializer($driver_manager, self::PARAMETERS, $dispatcher, $authentication_manager, $user_manager);
    $initializer->initializeContext($context);
  }

  /**
   * Builds an initializer over stubbed collaborators.
   */
  protected function createInitializer(): DriverAwareInitializer {
    return new DriverAwareInitializer(
      $this->createMock(DriverManagerInterface::class),
      self::PARAMETERS,
      $this->createHookDispatcher(),
      $this->createMock(AuthenticationManagerInterface::class),
      $this->createMock(UserManagerInterface::class),
    );
  }

}
