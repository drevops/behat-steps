<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;

/**
 * Injects the driver manager and its collaborators into a context.
 */
class DriverAwareInitializer implements ContextInitializer {

  /**
   * Constructs a DriverAwareInitializer object.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface $driverManager
   *   The driver manager.
   * @param array<string, mixed> $parameters
   *   Configuration parameters.
   * @param \Behat\Testwork\Hook\HookDispatcher $hookDispatcher
   *   The hook dispatcher.
   * @param \DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface $authenticationManager
   *   The authentication manager.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserManagerInterface $userManager
   *   The user manager.
   */
  public function __construct(
    protected readonly DriverManagerInterface $driverManager,
    protected readonly array $parameters,
    protected readonly HookDispatcher $hookDispatcher,
    protected readonly AuthenticationManagerInterface $authenticationManager,
    protected readonly UserManagerInterface $userManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function initializeContext(Context $context): void {
    // 'ParametersAwareInterface' is a strict subset of 'DriverAwareInterface'
    // (the latter extends the former). Pass parameters to any context that
    // asks for them, then layer the heavier driver wiring on top for full
    // driver-aware contexts only.
    if ($context instanceof ParametersAwareInterface) {
      $context->setParameters($this->parameters);
    }

    if (!$context instanceof DriverAwareInterface) {
      return;
    }

    $context->setDriverManager($this->driverManager);
    $context->setDispatcher($this->hookDispatcher);
    $context->setAuthenticationManager($this->authenticationManager);
    $context->setUserManager($this->userManager);
  }

}
