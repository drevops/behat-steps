<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Extension\Auth\AuthenticatorInterface;
use DrevOps\BehatSteps\Extension\Auth\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Extension\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Extension\Context\BackendAwareInterface;
use DrevOps\BehatSteps\Extension\Context\ParametersAwareInterface;
use DrevOps\BehatSteps\Extension\Context\UserAwareInterface;
use DrevOps\BehatSteps\Extension\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Extension\Registry\UserRegistryInterface;

/**
 * Injects the backend registry and its collaborators into a context.
 */
final readonly class BackendAwareInitializer implements ContextInitializer {

  /**
   * Constructs a BackendAwareInitializer object.
   *
   * @param \DrevOps\BehatSteps\Extension\Registry\BackendRegistryInterface $backendRegistry
   *   The backend registry.
   * @param array<string, mixed> $parameters
   *   Configuration parameters.
   * @param \Behat\Testwork\Hook\HookDispatcher $hookDispatcher
   *   The hook dispatcher.
   * @param \DrevOps\BehatSteps\Extension\Auth\BasicAuthenticatorInterface $basicAuthenticator
   *   Applies webserver-level basic auth, which needs no backend.
   * @param \DrevOps\BehatSteps\Extension\Auth\AuthenticatorInterface $authenticator
   *   Logs a user in and out of the site under test.
   * @param \DrevOps\BehatSteps\Extension\Registry\UserRegistryInterface $userRegistry
   *   The user registry.
   * @param \DrevOps\BehatSteps\Extension\Config\TraitOptionResolverFactoryInterface $traitOptionResolverFactory
   *   Builds a context's option resolver out of the shared collaborators.
   * @param \DrevOps\BehatSteps\Extension\Http\HttpClientFactoryInterface $httpClientFactory
   *   Builds the detached and bare browsers on the shared transport.
   */
  public function __construct(
    protected BackendRegistryInterface $backendRegistry,
    protected array $parameters,
    protected HookDispatcher $hookDispatcher,
    protected BasicAuthenticatorInterface $basicAuthenticator,
    protected AuthenticatorInterface $authenticator,
    protected UserRegistryInterface $userRegistry,
    protected TraitOptionResolverFactoryInterface $traitOptionResolverFactory,
    protected HttpClientFactoryInterface $httpClientFactory,
  ) {}

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
    $context->setHookDispatcher($this->hookDispatcher);
    $context->setBasicAuthenticator($this->basicAuthenticator);
    $context->setHttpClientFactory($this->httpClientFactory);

    // Set last: a context that rebuilds its resolver in this call reads the
    // 'steps' section from the parameters set above.
    $context->setOptionResolverFactory($this->traitOptionResolverFactory);
  }

}
