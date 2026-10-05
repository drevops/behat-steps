<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Prerequisite;

/**
 * One prerequisite a trait declares.
 *
 * A prerequisite is stated through a backend capability. A backend in the
 * scenario's list provides the capability, and for a check, that backend
 * passes the check.
 *
 * A trait returns its prerequisites from a '<prefix>Prerequisites()' method.
 *
 * @see \DrevOps\BehatSteps\Behat\Prerequisite\PrerequisiteReader
 */
final readonly class Prerequisite {

  /**
   * The capability interface a backend in the scenario's list provides.
   *
   * @var class-string
   */
  public string $capability;

  /**
   * Constructs a Prerequisite.
   *
   * @param string $capability
   *   The capability interface a backend in the scenario's list provides.
   * @param \Closure|null $check
   *   The check that backend passes, or NULL when providing the capability is
   *   the whole prerequisite. It takes the backend as its only parameter, typed
   *   to the capability interface, and returns whether the prerequisite holds.
   * @param string $description
   *   What holds when the prerequisite is met, as a clause that completes
   *   "requires that", such as 'the core "dblog" module is enabled'.
   *
   * @throws \RuntimeException
   *   When the description is empty, the capability is not an interface, or
   *   the check is not a static closure taking the capability and returning
   *   'bool'.
   */
  public function __construct(
    string $capability,
    public ?\Closure $check,
    public string $description,
  ) {
    if (trim($description) === '') {
      throw new \RuntimeException('A prerequisite declares a description.');
    }

    if ($check instanceof \Closure) {
      $function = new \ReflectionFunction($check);
      $parameters = $function->getParameters();
      $type = count($parameters) === 1 ? $parameters[0]->getType() : NULL;
      $return = $function->getReturnType();

      // Declarations are cached for the whole run, so a bound closure would keep
      // the context of the scenario that first read it.
      if ($function->getClosureThis() !== NULL) {
        throw new \RuntimeException(sprintf('The prerequisite "%s" declares its check as a static closure.', $description));
      }

      if (!$type instanceof \ReflectionNamedType || $type->allowsNull()) {
        throw new \RuntimeException(sprintf('The prerequisite "%s" declares a check taking exactly 1 parameter, typed to a capability interface.', $description));
      }

      if ($type->getName() !== $capability) {
        throw new \RuntimeException(sprintf('The prerequisite "%s" declares a check whose parameter is typed to "%s" rather than to its capability "%s".', $description, $type->getName(), $capability));
      }

      if (!$return instanceof \ReflectionNamedType || $return->getName() !== 'bool') {
        throw new \RuntimeException(sprintf('The prerequisite "%s" declares a check returning "bool".', $description));
      }
    }

    if (!interface_exists($capability)) {
      throw new \RuntimeException(sprintf('The prerequisite "%s" names "%s", which is not an interface.', $description, $capability));
    }

    $this->capability = $capability;
  }

  /**
   * Declares a capability a backend in the scenario's list provides.
   *
   * @param string $capability
   *   The capability interface.
   * @param string|null $description
   *   What holds when a backend provides it, as a clause, or NULL for a clause
   *   naming the capability.
   *
   * @return self
   *   The prerequisite.
   *
   * @throws \RuntimeException
   *   When the capability is not an interface.
   */
  public static function capability(string $capability, ?string $description = NULL): self {
    if ($description === NULL) {
      $separator = strrpos($capability, '\\');
      $description = sprintf('a backend in the scenario\'s list provides "%s"', $separator === FALSE ? $capability : substr($capability, $separator + 1));
    }

    return new self($capability, NULL, $description);
  }

  /**
   * Declares a check a backend providing a capability passes.
   *
   * The capability is the type of the closure's only parameter, so it cannot
   * differ from what the closure calls.
   *
   * @param \Closure $check
   *   A static closure taking the backend, typed to the capability interface it
   *   calls, and returning whether the prerequisite holds.
   * @param string $description
   *   What holds when the check passes, as a clause.
   *
   * @return self
   *   The prerequisite.
   *
   * @throws \RuntimeException
   *   When the closure does not take exactly 1 parameter typed to an interface
   *   and return 'bool', or the description is empty.
   */
  public static function check(\Closure $check, string $description): self {
    $parameters = (new \ReflectionFunction($check))->getParameters();
    $type = count($parameters) === 1 ? $parameters[0]->getType() : NULL;

    return new self($type instanceof \ReflectionNamedType ? $type->getName() : '', $check, $description);
  }

}
