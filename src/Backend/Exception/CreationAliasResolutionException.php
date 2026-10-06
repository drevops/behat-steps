<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Exception;

/**
 * Thrown when a creation alias cannot resolve its value.
 *
 * Raised by alias implementations when a stub property cannot be
 * translated into a Drupal storage operation. Examples are an 'author'
 * alias with a username that matches no account, and a 'parent' term name
 * absent from the target vocabulary.
 */
final class CreationAliasResolutionException extends Exception {}
