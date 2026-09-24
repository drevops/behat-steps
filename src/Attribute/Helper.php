<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Attribute;

/**
 * Marks a trait as plumbing rather than vocabulary.
 *
 * A marked trait registers no Gherkin: it carries no step, transformation or
 * hook attribute, and it is published in HELPERS.md. A context and a step
 * trait may both compose one, which yields a single property slot and shared
 * state.
 *
 * @see \DrevOps\BehatSteps\Attribute\Steps
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Helper {
}
