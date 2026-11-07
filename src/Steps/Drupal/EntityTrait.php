<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Transformation\Transform;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\TokenTrait;

/**
 * Create entities of a type that has no dedicated trait, and reference them.
 *
 * - Create entities of any type from a table of field values.
 * - Fill in an entity reference autocomplete field with an entity's label.
 * - Replace `[id:...]` tokens in step arguments and table cells with the ID
 *   of an entity looked up by its label.
 *
 * Covers types such as `commerce_product` or `group`, where a dedicated trait
 * would add vocabulary without adding behavior. Entities created here are
 * removed after the scenario along with every other entity the scenario
 * created.
 *
 * Possible token formats:
 * - `[id:ENTITY_TYPE:LABEL]`
 * - `[id:ENTITY_TYPE.BUNDLE:LABEL]`
 *
 * with:
 * - `ENTITY_TYPE`: the entity type ID, such as `node`, `user` or
 *   `taxonomy_term`.
 * - `BUNDLE`: the bundle to restrict the lookup to, such as `article`.
 * - `LABEL`: the label to match exactly, such as a title or a user name. It
 *   may hold balanced brackets, as in `[TEST] Page`, but no other token.
 *
 * Examples:
 * - `/print/pdf/user/[id:user:jane]` converted to `/print/pdf/user/12`
 * - `[id:taxonomy_term.topics:Jazz]` converted to `7`
 *
 * When several entities share the label, the newest is used. A token whose
 * entity does not exist fails the step.
 *
 * Skip cleanup for 1 type with tag:
 * `@behat-steps-entity-cleanup-skip:commerce_product`.
 *
 * Skip token processing with tag: `@behat-steps-skip:EntityTrait`.
 *
 * @title Entities
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait EntityTrait {

  use EntityLifecycleTrait;
  use QueryTrait;
  use TokenTrait;

  /**
   * Matches 1 entity ID token.
   *
   * The label group recurses into balanced brackets, so a label such as
   * '[TEST] Page' ends at the bracket closing the token.
   */
  protected const string ENTITY_ID_TOKEN_REGEX = '#\[id:(?<entity_type>[a-z0-9_]+)(?:\.(?<bundle>[a-z0-9_]+))?:(?<label>(?:[^\[\]]++|(?<nested>\[(?:[^\[\]]++|(?&nested))*\]))*)\]#';

  /**
   * Whether token replacement is active for the current scenario.
   */
  protected bool $entityEnabled = TRUE;

  /**
   * Resolves whether the current scenario replaces tokens.
   */
  #[BeforeScenario]
  public function entityBeforeScenario(BeforeScenarioScope $scope): void {
    $this->entityEnabled = !$this->skipTag(__TRAIT__, $scope);
  }

  /**
   * Create entities of a type from a table of field values.
   *
   * Each row becomes 1 entity; each column is a base property or a field.
   *
   * @code
   *   Given the following "commerce_product" entities exist:
   *     | title | type    | status |
   *     | TNT   | product | 1      |
   *     | Anvil | product | 1      |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following :entity_type entities exist:')]
  public function entityCreateMultiple(string $entity_type, TableNode $table): void {
    foreach ($table->getHash() as $values) {
      $this->entityLifecycleCreate(new EntityStub($entity_type, NULL, $values));
    }
  }

  /**
   * Fill in an entity reference autocomplete field with an entity's label.
   *
   * The field receives the 'Label (id)' value the autocomplete widget accepts
   * without its suggestion list. The entity is matched in every bundle of its
   * type, which is the only form for a type without bundles, such as a user.
   * When several entities share the label, the newest is used.
   *
   * @code
   * When I fill in the entity reference field "Authored by" with the "user" entity with the label "jane"
   * @endcode
   *
   * @actor:person
   */
  #[When('I fill in the entity reference field :field with the :entity_type entity with the label :label')]
  public function entityFillReferenceFieldWithLabel(string $field, string $entity_type, string $label): void {
    $this->entityFillAutocompleteField($field, $entity_type, $label);
  }

  /**
   * Fill in an entity reference autocomplete field with a bundle entity.
   *
   * The field receives the 'Label (id)' value the autocomplete widget accepts
   * without its suggestion list. When several entities of the bundle share
   * the label, the newest is used.
   *
   * @code
   * When I fill in the entity reference field "Program" with the "program" "node" entity with the label "Morning show"
   * When I fill in the entity reference field "Tags" with the "tags" "taxonomy_term" entity with the label "Jazz"
   * @endcode
   *
   * @actor:person
   */
  #[When('I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label')]
  public function entityFillReferenceFieldOfBundleWithLabel(string $field, string $bundle, string $entity_type, string $label): void {
    $this->entityFillAutocompleteField($field, $entity_type, $label, $bundle);
  }

  /**
   * Fill in an entity reference autocomplete field with an entity's label.
   *
   * The value replaces whatever the field holds, so a tags field ends up with
   * the 1 entity.
   *
   * @param string $field
   *   The field's id, name, label or placeholder.
   * @param string $entity_type
   *   The entity type id of the referenced entity.
   * @param string $label
   *   The label of the referenced entity.
   * @param string|null $bundle
   *   The bundle of the referenced entity, or NULL for every bundle.
   *
   * @throws \RuntimeException
   *   When the bundle does not exist or no entity has the label.
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the page has no such field.
   */
  public function entityFillAutocompleteField(string $field, string $entity_type, string $label, ?string $bundle = NULL): void {
    $id = $this->entityGetIdByLabel($entity_type, $label, $bundle);

    $this->getSession()->getPage()->fillField($field, $this->entityFormatAutocompleteValue($label, $id));
  }

  /**
   * Get the id of the newest entity of a type with a label.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $label
   *   The label to match exactly.
   * @param string|null $bundle
   *   The bundle to restrict the lookup to, or NULL for every bundle.
   *
   * @return string
   *   The id of the entity created last with the label.
   *
   * @throws \RuntimeException
   *   When the bundle does not exist or no entity has the label.
   */
  public function entityGetIdByLabel(string $entity_type, string $label, ?string $bundle = NULL): string {
    if ($bundle !== NULL && !$this->backendFor(EntityDefinitionCapabilityInterface::class)->bundleExists($entity_type, $bundle)) {
      throw new \RuntimeException(sprintf('The entity type "%s" has no bundle "%s".', $entity_type, $bundle));
    }

    $id = $this->queryFindNewestEntityIdByLabel($entity_type, $label, $bundle);

    if ($id === NULL) {
      $noun = $bundle === NULL ? sprintf('"%s"', $entity_type) : sprintf('"%s" "%s"', $bundle, $entity_type);

      throw new \RuntimeException(sprintf('The %s entity with the label "%s" does not exist.', $noun, $label));
    }

    return $id;
  }

  /**
   * Format the value an entity reference autocomplete widget accepts.
   *
   * A value holding a comma or a double quote is wrapped in double quotes with
   * each inner quote doubled, as Drupal's 'Tags::encode()' does, so the tags
   * widget reads it as 1 entity.
   *
   * @param string $label
   *   The entity label.
   * @param string $id
   *   The entity id.
   *
   * @return string
   *   The 'Label (id)' value.
   */
  protected function entityFormatAutocompleteValue(string $label, string $id): string {
    $value = sprintf('%s (%s)', $label, $id);

    if (!str_contains($value, ',') && !str_contains($value, '"')) {
      return $value;
    }

    return '"' . str_replace('"', '""', $value) . '"';
  }

  /**
   * Replaces every entity ID token inside a scalar step argument.
   *
   * @param string $argument
   *   The raw step argument.
   *
   * @return string
   *   The argument with every entity ID token resolved.
   */
  #[Transform('#(.*\[id:[a-z0-9_]+(?:\.[a-z0-9_]+)?:.*\].*)#')]
  public function entityTransformValue(string $argument): string {
    if (!$this->entityEnabled) {
      return $argument;
    }

    return $this->entitySubstituteIdTokens($argument);
  }

  /**
   * Replaces every entity ID token inside a table's cells.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The raw table argument.
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   The table with every cell's entity ID tokens resolved.
   */
  #[Transform('table:*')]
  public function entityTransformTable(TableNode $table): TableNode {
    if (!$this->entityEnabled || !str_contains($table->getTableAsString(), '[id:')) {
      return $table;
    }

    return $this->tokenReplaceInTable($table, $this->entitySubstituteIdTokens(...));
  }

  /**
   * Replace every entity ID token in a string with the ID of its entity.
   *
   * @param string $value
   *   The string to replace tokens in.
   *
   * @return string
   *   The string with every token replaced by the ID of the newest entity
   *   with the token's label.
   *
   * @throws \RuntimeException
   *   When the bundle does not exist or no entity has the label.
   */
  public function entitySubstituteIdTokens(string $value): string {
    $result = preg_replace_callback(self::ENTITY_ID_TOKEN_REGEX, fn(array $matches): string => $this->entityGetIdByLabel($matches['entity_type'], $matches['label'], $matches['bundle'] === '' ? NULL : $matches['bundle']), $value);

    return $result ?? $value;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function entityConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: "If `TRUE`, replaces `[id:...]` tokens in step arguments and table cells with the ID of the newest entity with the token's label, though not in multiline text. If `FALSE`, tokens stay as written."),
    ];
  }

}
