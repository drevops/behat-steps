<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\WebformTrait;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for loading webforms through 'WebformTrait'.
 */
#[CoversTrait(WebformTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class WebformTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'path', 'path_alias', 'user', 'field', 'webform'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['webform']);
  }

  public function testLoadMultipleLoadsTheMatchingWebforms(): void {
    $first = $this->createWebform('shared_form', '[TEST] Shared form');
    $second = $this->createWebform('shared_template', '[TEST] Shared template', TRUE);
    $this->createWebform('other_form', '[TEST] Other form');

    $webforms = $this->context->webformLoadMultiple('[TEST] Shared');

    $this->assertLoadedSet([$first, $second], $webforms, WebformInterface::class);
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createWebform('other_form', '[TEST] Other form');

    $this->assertSame([], $this->context->webformLoadMultiple('[TEST] Shared'));
  }

  public function testLoadTemplateMultipleLoadsOnlyTheMatchingTemplates(): void {
    $this->createWebform('shared_form', '[TEST] Shared form');
    $template = $this->createWebform('shared_template', '[TEST] Shared template', TRUE);
    $this->createWebform('other_template', '[TEST] Other template', TRUE);

    $templates = $this->context->webformLoadTemplateMultiple('[TEST] Shared');

    $this->assertLoadedSet([$template], $templates, WebformInterface::class);
  }

  public function testFindTemplateByTitleReturnsTheLastMachineNameInNaturalOrder(): void {
    $this->createWebform('shared_template_2', '[TEST] Shared template A', TRUE);
    $newest = $this->createWebform('shared_template_10', '[TEST] Shared template B', TRUE);

    $this->assertSame($newest->id(), $this->context->webformFindTemplateByTitle('[TEST] Shared')?->id());
  }

  public function testFindTemplateByTitlePrefersTheTemplateTheScenarioCreated(): void {
    $created = $this->createWebform('shared_template_1', '[TEST] Shared template A', TRUE);
    $this->createWebform('shared_template_2', '[TEST] Shared template B', TRUE);
    $this->context->entityLifecycleRegister($created);

    $this->assertSame($created->id(), $this->context->webformFindTemplateByTitle('[TEST] Shared')?->id());
  }

  public function testFindTemplateByTitleIgnoresWebformsThatAreNotTemplates(): void {
    $template = $this->createWebform('shared_template_1', '[TEST] Shared template', TRUE);
    $this->createWebform('shared_form_2', '[TEST] Shared form');

    $this->assertSame($template->id(), $this->context->webformFindTemplateByTitle('[TEST] Shared')?->id());
  }

  public function testFindTemplateByTitleFindsNothing(): void {
    $this->createWebform('shared_form', '[TEST] Shared form');

    $this->assertNull($this->context->webformFindTemplateByTitle('[TEST] Shared'));
  }

  public function testCloneTemplateClonesTheNewestTemplate(): void {
    $this->createWebform('shared_template_a', '[TEST] Shared template A', TRUE, "older:\n  '#type': textfield\n");
    $newest = $this->createWebform('shared_template_b', '[TEST] Shared template B', TRUE, "newest:\n  '#type': textfield\n");

    $this->context->webformCloneTemplate('[TEST] Clone', '[TEST] Shared template');

    $clones = array_values($this->context->webformLoadMultiple('[TEST] Clone'));
    $this->assertCount(1, $clones);
    $this->assertFalse($clones[0]->isTemplate());
    $this->assertSame($newest->getElementsRaw(), $clones[0]->getElementsRaw());
  }

  protected function createWebform(string $id, string $title, bool $is_template = FALSE, string $elements = ''): WebformInterface {
    $webform = Webform::create(['id' => $id, 'title' => $title, 'template' => $is_template, 'elements' => $elements]);
    $webform->save();

    return $webform;
  }

}
