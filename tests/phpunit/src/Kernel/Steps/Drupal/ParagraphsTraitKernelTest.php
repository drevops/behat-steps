<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Drupal\ParagraphsTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for attaching paragraphs to an entity through 'ParagraphsTrait'.
 *
 * The 'landing_page' content type references 'text' paragraphs from its
 * 'field_paragraph' field, which takes any number of them.
 */
#[CoversTrait(ParagraphsTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class ParagraphsTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'file', 'entity_reference_revisions', 'paragraphs'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter']);

    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();
    $this->attachField('paragraph', 'text', 'field_paragraph_title', 'string');

    NodeType::create(['type' => 'landing_page', 'name' => 'Landing page'])->save();
    $this->attachField('node', 'landing_page', 'field_paragraph', 'entity_reference_revisions', ['target_type' => 'paragraph'], ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => ['text' => 'text']]], -1);
  }

  public function testAddWithFieldsAppendsEachParagraphToTheParent(): void {
    $node = $this->createLandingPage('Landing');

    $this->context->paragraphsAddWithFields('node', 'landing_page', 'field_paragraph', 'title', 'Landing', 'text', new TableNode([['field_paragraph_title', 'First']]));
    $this->context->paragraphsAddWithFields('node', 'landing_page', 'field_paragraph', 'title', 'Landing', 'text', new TableNode([['field_paragraph_title', 'Second']]));

    $titles = [];

    foreach ($this->reloadNode($node)->get('field_paragraph')->getValue() as $item) {
      $paragraph = $this->loadParagraph($item['target_id']);

      $this->assertEquals($paragraph->getRevisionId(), $item['target_revision_id'], 'The reference names the current revision of the paragraph.');
      $this->assertSame('node', $paragraph->get('parent_type')->value);
      $this->assertSame((string) $node->id(), (string) $paragraph->get('parent_id')->value);
      $this->assertSame('field_paragraph', $paragraph->get('parent_field_name')->value);

      $titles[] = $paragraph->get('field_paragraph_title')->value;
    }

    $this->assertSame(['First', 'Second'], $titles, 'The second paragraph was appended after the first.');
  }

  public function testAttachFromStubToEntityAcceptsParentWithoutBundle(): void {
    $node = $this->createLandingPage('Landing');

    $created = $this->context->paragraphsAttachFromStubToEntity(new EntityStub('node', NULL, ['nid' => $node->id()]), 'field_paragraph', 'text', new EntityStub('paragraph', 'text', ['field_paragraph_title' => 'Direct']));

    $this->assertTrue($created->isSaved());
    $this->assertSame('text', $created->getBundle());

    $items = $this->reloadNode($node)->get('field_paragraph')->getValue();
    $this->assertCount(1, $items);
    $this->assertSame((string) $created->getValue('id'), (string) $items[0]['target_id']);
    $this->assertSame('Direct', $this->loadParagraph($items[0]['target_id'])->get('field_paragraph_title')->value);
  }

  public function testFindEntityReturnsTheStubOfTheNewestEntity(): void {
    $this->createLandingPage('Shared');
    $newest = $this->createLandingPage('Shared');

    $stub = $this->context->paragraphsFindEntity('node', 'landing_page', 'title', 'Shared');

    $this->assertNotNull($stub);
    $this->assertSame('landing_page', $stub->getBundle());
    $this->assertSame((string) $newest->id(), $stub->getValue('nid'));
    $this->assertNull($this->context->paragraphsFindEntity('node', 'landing_page', 'title', 'Missing'));
  }

  /**
   * Attaches a field to a bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The machine name of the field.
   * @param string $type
   *   The field type.
   * @param array<string, mixed> $storage_settings
   *   Settings passed to FieldStorageConfig.
   * @param array<string, mixed> $field_settings
   *   Settings passed to FieldConfig.
   * @param int $cardinality
   *   The number of values the field takes, -1 for any number.
   */
  protected function attachField(string $entity_type, string $bundle, string $field_name, string $type, array $storage_settings = [], array $field_settings = [], int $cardinality = 1): void {
    FieldStorageConfig::create(['field_name' => $field_name, 'entity_type' => $entity_type, 'type' => $type, 'settings' => $storage_settings, 'cardinality' => $cardinality])->save();
    FieldConfig::create(['field_name' => $field_name, 'entity_type' => $entity_type, 'bundle' => $bundle, 'settings' => $field_settings])->save();
  }

  protected function createLandingPage(string $title): NodeInterface {
    $node = Node::create(['type' => 'landing_page', 'title' => $title]);
    $node->save();

    return $node;
  }

  /**
   * Loads a node again, bypassing the static cache.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  protected function reloadNode(NodeInterface $node): NodeInterface {
    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertInstanceOf(NodeInterface::class, $reloaded);

    return $reloaded;
  }

  /**
   * Loads a paragraph.
   *
   * @param int|string $id
   *   The paragraph ID.
   */
  protected function loadParagraph(int|string $id): ParagraphInterface {
    $paragraph = Paragraph::load($id);
    $this->assertInstanceOf(ParagraphInterface::class, $paragraph);

    return $paragraph;
  }

}
