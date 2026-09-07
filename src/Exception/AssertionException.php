<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Exception;

/**
 * Thrown when a step assertion fails and no Mink session is available.
 *
 * \Behat\Mink\Exception\ExpectationException requires a Mink driver, so a
 * trait that never touches the browser cannot construct it. This carries the
 * same meaning - an expectation about the system under test was not met -
 * without that dependency.
 *
 * A failure that is not an assertion, such as an invalid step argument, an
 * unmet prerequisite or an infrastructure error, is a \RuntimeException.
 */
class AssertionException extends \Exception {

}
