<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Context\BackendAwareInterface;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;

/**
 * Injects the backend registry and its collaborators into a context.
 */
class BackendAwareInitializer implements ContextInitializer {

  /**
   * Constructs a BackendAwareInitializer object.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface $backendRegistry
   *   The backend registry.
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
   * @param \DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface $httpClientFactory
   *   Builds the detached and bare browsers on the shared transport.
   */
  public function __construct(
    protected readonly BackendRegistryInterface $backendRegistry,
    protected readonly array $parameters,
    protected readonly HookDispatcher $hookDispatcher,
    protected readonly BasicAuthenticatorInterface $basicAuthenticator,
    protected readonly AuthenticatorInterface $authenticator,
    protected readonly UserRegistryInterface $userRegistry,
    protected readonly TraitOptionResolverFactoryInterface $optionResolverFactory,
    protected readonly HttpClientFactoryInterface $httpClientFactory,
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

    if (!$context instanceof BackendAwareInterface) {
      return;
    }

    $context->setBackendRegistry($this->backendRegistry);
    $context->setDispatcher($this->hookDispatcher);
    $context->setBasicAuthenticator($this->basicAuthenticator);
    $context->setHttpClientFactory($this->httpClientFactory);

    // Set last: it rebuilds the resolver, so the parameters set above are the
    // ones the rebuild reads its 'steps' section from.
    $context->setOptionResolverFactory($this->optionResolverFactory);
  }

}
