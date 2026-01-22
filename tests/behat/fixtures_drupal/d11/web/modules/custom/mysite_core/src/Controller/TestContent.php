<?php

declare(strict_types=1);

namespace Drupal\mysite_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mysite_core\Time\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for providing test content.
 */
final class TestContent extends ControllerBase {

  public function __construct(
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('mysite_core.time'),
    );
  }

  /**
   * Provides example content for route specific authentication.
   *
   * @return array<mixed>
   *   The username of the current logged in user.
   */
  public function testCurrentUser(): array {
    $account = $this->currentUser();

    return ['#markup' => $account->getAccountName()];
  }

  /**
   * Returns the current time as plain text.
   */
  public function testTime(): Response {
    return new Response((string) $this->time->getCurrentTime(), 200, [
      'Content-Type' => 'text/plain',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
  }

}
