<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field\Parser;

/**
 * Contract for entity-field value parsers.
 *
 * Implementations transform a raw map of field-name to cell-text pairs (as
 * returned by 'EntityStubInterface::getValues()') into a final map for
 * 'EntityStubInterface::setValues()'.
 *
 * Each implementation owns all syntactic concerns: CSV multi-value splitting,
 * compound column splitting, inline named-column interpretation and
 * 'field:column' / ':column' multicolumn-header merging. It also owns all
 * field-type semantics: configurable, base, ignored or unknown.
 *
 * Dependencies those decisions require (entity type, classifier) are
 * constructor-injected. Per-call configuration that may vary between stubs
 * (e.g. the list of ignored property names) is set via fluent setters before
 * 'parse()' is called.
 */
interface EntityFieldParserInterface {

  /**
   * Parses raw stub values into final stub values.
   *
   * @param array<string|int, mixed> $values
   *   The raw stub values from 'EntityStubInterface::getValues()'.
   *
   * @return array<string, mixed>
   *   Final stub values, ready for 'EntityStubInterface::setValues()'.
   *
   * @throws \RuntimeException
   *   On unknown fields or orphan ':column' continuations.
   */
  public function parse(array $values): array;

  /**
   * Sets property names accepted without field-type validation.
   *
   * Backend-level creation hints on the stub (e.g. 'author', 'role',
   * 'vocabulary_machine_name') are not Drupal fields.
   *
   * @param string[] $properties
   *   Property names to accept without validation.
   *
   * @return static
   *   The same instance, for fluent chaining.
   */
  public function ignoring(array $properties): static;

}
