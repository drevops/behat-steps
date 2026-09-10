<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Driver\Core;

use DrevOps\BehatSteps\Driver\Core\Core;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParserInterface;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\Exception\ParseException;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for cell parsing inside the driver's create paths.
 *
 * Drives Core directly, with no Behat context in play, so a cell written in
 * the compound grammar has to reach storage through the driver alone.
 *
 * @group core
 * @group fields
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[Group('fields')]
class CoreEntityParseKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
  ];

  /**
   * The Core driver under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'filter', 'field']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->attachField('field_body', 'text_long', 1);
    $this->attachField('field_keyword', 'string', 3);

    $this->core = new Core($this->root);
  }

  /**
   * Tests that a compound cell reaches storage through the driver alone.
   */
  public function testCompoundCellIsParsedByTheDriver(): void {
    $stub = new EntityStub('node', 'article', [
      'title' => 'A node',
      'field_body' => 'value: "Body text", format: "plain_text"',
    ]);

    $this->core->nodeCreate($stub);

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(Node::class, $node);
    $this->assertSame([['value' => 'Body text', 'format' => 'plain_text']], $node->get('field_body')->getValue());
  }

  /**
   * Tests that a comma-separated cell becomes one delta per item.
   */
  public function testCommaSeparatedCellBecomesSeveralDeltas(): void {
    $stub = new EntityStub('node', 'article', [
      'title' => 'A node',
      'field_keyword' => 'alpha, "beta, gamma"',
    ]);

    $this->core->nodeCreate($stub);

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(Node::class, $node);
    $this->assertSame([['value' => 'alpha'], ['value' => 'beta, gamma']], $node->get('field_keyword')->getValue());
  }

  /**
   * Tests that a creation-alias key is accepted without field validation.
   */
  public function testCreationAliasKeyIsNotValidatedAsField(): void {
    User::create(['name' => 'author_of_record', 'status' => 1])->save();

    $stub = new EntityStub('node', 'article', ['title' => 'A node', 'author' => 'author_of_record']);

    $this->core->nodeCreate($stub);

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(Node::class, $node);
    $this->assertSame('author_of_record', $node->getOwner()->getAccountName());
  }

  /**
   * Tests that a value naming no field is rejected during creation.
   */
  public function testAnUnknownFieldIsRejectedDuringCreation(): void {
    $stub = new EntityStub('node', 'article', ['title' => 'A node', 'field_absent' => 'a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field "field_absent" does not exist on entity type "node".');

    $this->core->nodeCreate($stub);
  }

  /**
   * Tests that a malformed cell is rejected during creation.
   */
  public function testMalformedCellIsRejectedDuringCreation(): void {
    $stub = new EntityStub('node', 'article', ['title' => 'A node', 'field_body' => 'value: "Body text", format: bare']);

    $this->expectException(ParseException::class);

    $this->core->nodeCreate($stub);
  }

  /**
   * Tests that parsing runs once, however many times it is asked for.
   */
  public function testParsedStubIsLeftAlone(): void {
    $stub = new EntityStub('node', 'article', [
      'title' => 'A node',
      'field_keyword' => 'alpha, beta',
    ]);

    $this->core->parseEntityFields($stub);
    $this->assertTrue($stub->isParsed());
    $this->assertSame(['alpha', 'beta'], $stub->getValue('field_keyword'));

    $this->core->parseEntityFields($stub);
    $this->assertSame(['alpha', 'beta'], $stub->getValue('field_keyword'));

    $this->core->nodeCreate($stub);

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(Node::class, $node);
    $this->assertSame([['value' => 'alpha'], ['value' => 'beta']], $node->get('field_keyword')->getValue());
  }

  /**
   * Tests that the caller can widen the accepted property names.
   */
  public function testCallerSuppliedPropertiesAreAccepted(): void {
    $stub = new EntityStub('node', 'article', ['title' => 'A node', 'custom_hint' => 'a']);

    $this->core->parseEntityFields($stub, ['custom_hint']);

    $this->assertSame('a', $stub->getValue('custom_hint'));
  }

  /**
   * Tests that a term stub is parsed on its own create path.
   */
  public function testTermCellIsParsedByTheDriver(): void {
    $this->enableModules(['taxonomy']);
    $this->installEntitySchema('taxonomy_term');

    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'vocabulary_machine_name' => 'tags']);

    \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->create(['vid' => 'tags', 'name' => 'Tags'])->save();

    $this->core->termCreate($stub);

    $term = Term::load($stub->getValue('tid'));
    $this->assertInstanceOf(Term::class, $term);
    $this->assertTrue($stub->isParsed());
    $this->assertSame('A term', $term->label());
  }

  /**
   * Tests that a term's vocabulary is resolved before its fields are parsed.
   */
  public function testTermVocabularyIsResolvedBeforeParsing(): void {
    $this->enableModules(['taxonomy']);
    $this->installEntitySchema('taxonomy_term');

    \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->create(['vid' => 'tags', 'name' => 'Tags'])->save();

    FieldStorageConfig::create(['field_name' => 'field_note', 'entity_type' => 'taxonomy_term', 'type' => 'string', 'cardinality' => 2])->save();
    FieldConfig::create(['field_name' => 'field_note', 'entity_type' => 'taxonomy_term', 'bundle' => 'tags'])->save();

    // The vocabulary reaches the driver only through the creation alias, so
    // the bundle is unknown until the alias has run.
    $stub = new EntityStub('taxonomy_term', NULL, [
      'name' => 'A term',
      'vocabulary_machine_name' => 'tags',
      'field_note' => 'alpha, beta',
    ]);

    $this->core->termCreate($stub);

    $term = Term::load($stub->getValue('tid'));
    $this->assertInstanceOf(Term::class, $term);
    $this->assertSame([['value' => 'alpha'], ['value' => 'beta']], $term->get('field_note')->getValue());
  }

  /**
   * Tests that a user stub is parsed on its own create path.
   */
  public function testUserStubIsParsedByTheDriver(): void {
    $stub = new EntityStub('user', NULL, ['name' => 'a_user', 'mail' => 'a@example.com']);

    $this->core->userCreate($stub);

    $user = User::load($stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $user);
    $this->assertTrue($stub->isParsed());
    $this->assertSame('a_user', $user->getAccountName());
  }

  /**
   * Tests that the parser factory binds the entity type it is handed.
   */
  public function testTheParserFactoryBindsTheEntityType(): void {
    $parser = $this->core->getFieldParser('node', 'article');

    $this->assertInstanceOf(EntityFieldParserInterface::class, $parser);
    $this->assertSame(['field_keyword' => ['alpha']], $parser->parse(['field_keyword' => 'alpha']));
  }

  /**
   * Attaches a field to the article bundle.
   */
  protected function attachField(string $field_name, string $type, int $cardinality): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $cardinality,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
  }

}
