<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

use Behat\Behat\Context\Context;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Base implementation of an entity creation scope.
 */
abstract class BaseEntityScope implements EntityScopeInterface {

  /**
   * Initializes the scope.
   */
  public function __construct(
    protected readonly Environment $environment,
    protected readonly Context $context,
    protected readonly EntityStubInterface $entityStub,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): Context {
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  public function getStub(): EntityStubInterface {
    return $this->entityStub;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironment(): Environment {
    return $this->environment;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuite(): Suite {
    return $this->environment->getSuite();
  }

}
