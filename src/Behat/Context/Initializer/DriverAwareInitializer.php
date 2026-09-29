<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;

/**
 * Injects the driver registry and its collaborators into a context.
 */
class DriverAwareInitializer implements ContextInitializer {

  /**
   * Constructs a DriverAwareInitializer object.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface $driverRegistry
   *   The driver registry.
   * @param array<string, mixed> $parameters
   *   Configuration parameters.
   * @param \Behat\Testwork\Hook\HookDispatcher $hookDispatcher
   *   The hook dispatcher.
   * @param \DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface $basicAuthenticator
   *   Applies webserver-level basic auth, which needs no Drupal site.
   * @param \DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface $authenticator
   *   Logs a user in and out of the site under test.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface $userRegistry
   *   The user registry.
   * @param \DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface $optionResolverFactory
   *   Builds a context's option resolver out of the shared collaborators.
   */
  public function __construct(
    protected readonly DriverRegistryInterface $driverRegistry,
    protected readonly array $parameters,
    protected readonly HookDispatcher $hookDispatcher,
    protected readonly BasicAuthenticatorInterface $basicAuthenticator,
    protected readonly AuthenticatorInterface $authenticator,
    protected readonly UserRegistryInterface $userRegistry,
    protected readonly TraitOptionResolverFactoryInterface $optionResolverFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function initializeContext(Context $context): void {
    if ($context instanceof ParametersAwareInterface) {
      $context->setParameters($this->parameters);
    }

    if ($context instanceof UserAwareInterface) {
      $context->authSetUserRegistry($this->userRegistry);
      $context->authSetAuthenticator($this->authenticator);
    }

    if (!$context instanceof DriverAwareInterface) {
      return;
    }

    $context->setDriverRegistry($this->driverRegistry);
    $context->setDispatcher($this->hookDispatcher);
    $context->setBasicAuthenticator($this->basicAuthenticator);

    // Set last: it rebuilds the resolver, so the parameters set above are the
    // ones the rebuild reads its 'steps' section from.
    $context->setOptionResolverFactory($this->optionResolverFactory);
  }

}
