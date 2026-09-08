<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat;

use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Mink\WebAssert;

/**
 * Provides Mink session access to classes that are not Behat contexts.
 *
 * A context reaches the session through its 'RawMinkContext' ancestor. The
 * managers are container services rather than contexts, so they take the Mink
 * instance through 'setMink()' and read the same session through this trait.
 *
 * @see \Behat\MinkExtension\Context\RawMinkContext
 */
trait MinkAwareTrait {

  /**
   * The Mink sessions manager.
   */
  protected Mink $mink;

  /**
   * The parameters for the Mink extension.
   *
   * @var array<string, mixed>
   */
  protected array $minkParameters = [];

  /**
   * Sets the Mink sessions manager.
   */
  public function setMink(Mink $mink): void {
    $this->mink = $mink;
  }

  /**
   * Returns the Mink sessions manager.
   */
  public function getMink(): Mink {
    return $this->mink;
  }

  /**
   * Returns the Mink session.
   *
   * @param string|null $name
   *   The name of the session to return. If omitted the active session will
   *   be returned.
   */
  public function getSession(?string $name = NULL): Session {
    return $this->getMink()->getSession($name);
  }

  /**
   * Returns the parameters provided for Mink.
   *
   * @return array<string, mixed>
   *   An array of Mink parameters.
   */
  public function getMinkParameters(): array {
    return $this->minkParameters;
  }

  /**
   * Sets parameters provided for Mink.
   *
   * @param array<string, mixed> $parameters
   *   The Mink parameters to set.
   */
  public function setMinkParameters(array $parameters): void {
    $this->minkParameters = $parameters;
  }

  /**
   * Returns a specific Mink parameter.
   */
  public function getMinkParameter(string $name): mixed {
    return $this->minkParameters[$name] ?? NULL;
  }

  /**
   * Applies the given parameter to the Mink configuration.
   *
   * The value applies only within the class using this trait.
   */
  public function setMinkParameter(string $name, mixed $value): void {
    $this->minkParameters[$name] = $value;
  }

  /**
   * Returns the Mink session assertion tool.
   *
   * @param string|null $name
   *   The name of the session to return. If omitted the active session will
   *   be returned.
   */
  public function assertSession(?string $name = NULL): WebAssert {
    return $this->getMink()->assertSession($name);
  }

  /**
   * Visits the provided relative path using the provided or default session.
   */
  public function visitPath(string $path, ?string $session_name = NULL): void {
    $this->getSession($session_name)->visit($this->locatePath($path));
  }

  /**
   * Locates a URL, based on the provided path.
   *
   * Override to provide a custom routing mechanism.
   */
  public function locatePath(string $path): string {
    $start_url = rtrim((string) $this->getMinkParameter('base_url'), '/') . '/';

    return str_starts_with($path, 'http') ? $path : $start_url . ltrim($path, '/');
  }

}
