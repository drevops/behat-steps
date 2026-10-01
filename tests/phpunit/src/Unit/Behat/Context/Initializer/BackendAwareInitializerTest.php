<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Context\BackendAwareInterface;
use DrevOps\BehatSteps\Behat\Context\Initializer\BackendAwareInitializer;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests which collaborators a context receives, by the interfaces it declares.
 */
#[CoversClass(BackendAwareInitializer::class)]
class BackendAwareInitializerTest extends UnitTestCase {

  /**
   * Parameters the initializer is constructed with.
   */
  protected const PARAMETERS = ['backends' => ['drush']];

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

  public function testBackendAwareContextReceivesEveryCollaborator(): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $dispatcher = $this->createHookDispatcher();
    $basic_authenticator = $this->createMock(BasicAuthenticatorInterface::class);
    $resolver_factory = $this->createMock(TraitOptionResolverFactoryInterface::class);
    $http_client_factory = $this->createMock(HttpClientFactoryInterface::class);

    $context = $this->createMock(BackendAwareInterface::class);
    $context->expects($this->once())->method('setParameters')->with(self::PARAMETERS);
    $context->expects($this->once())->method('setBackendRegistry')->with($backend_registry);
    $context->expects($this->once())->method('setDispatcher')->with($dispatcher);
    $context->expects($this->once())->method('setBasicAuthenticator')->with($basic_authenticator);
    $context->expects($this->once())->method('setHttpClientFactory')->with($http_client_factory);
    $context->expects($this->once())->method('setOptionResolverFactory')->with($resolver_factory);

    $initializer = new BackendAwareInitializer($backend_registry, self::PARAMETERS, $dispatcher, $basic_authenticator, $this->createMock(AuthenticatorInterface::class), $this->createMock(UserRegistryInterface::class), $resolver_factory, $http_client_factory);
    $initializer->initializeContext($context);
  }

  public function testUserAwareContextReceivesTheUserAndLoginManagers(): void {
    $user_registry = $this->createMock(UserRegistryInterface::class);
    $authenticator = $this->createMock(AuthenticatorInterface::class);

    $context = $this->createMock(UserAwareInterface::class);
    $context->expects($this->once())->method('authSetUserRegistry')->with($user_registry);
    $context->expects($this->once())->method('authSetAuthenticator')->with($authenticator);

    $initializer = new BackendAwareInitializer(
      $this->createMock(BackendRegistryInterface::class),
      self::PARAMETERS,
      $this->createHookDispatcher(),
      $this->createMock(BasicAuthenticatorInterface::class),
      $authenticator,
      $user_registry,
      $this->createMock(TraitOptionResolverFactoryInterface::class),
      $this->createMock(HttpClientFactoryInterface::class),
    );

    $initializer->initializeContext($context);
  }

  /**
   * Builds an initializer over stubbed collaborators.
   */
  protected function createInitializer(): BackendAwareInitializer {
    return new BackendAwareInitializer(
      $this->createMock(BackendRegistryInterface::class),
      self::PARAMETERS,
      $this->createHookDispatcher(),
      $this->createMock(BasicAuthenticatorInterface::class),
      $this->createMock(AuthenticatorInterface::class),
      $this->createMock(UserRegistryInterface::class),
      $this->createMock(TraitOptionResolverFactoryInterface::class),
      $this->createMock(HttpClientFactoryInterface::class),
    );
  }

}
