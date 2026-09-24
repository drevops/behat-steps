<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Attribute;

/**
 * Marks a trait as step vocabulary.
 *
 * A marked trait registers Gherkin: its members carry step, transformation or
 * hook attributes, and it is published in STEPS.md. A trait cannot implement
 * an interface, so the marker is what makes the distinction readable by
 * reflection and enforceable by a lint.
 *
 * @see \DrevOps\BehatSteps\Attribute\Helper
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Steps {
}
