<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Entity;

use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EntityStub typed envelope.
 */
#[CoversClass(EntityStub::class)]
#[Group('entity')]
class EntityStubTest extends TestCase {

  public function testConstructorPinsTypeAndBundle(): void {
    $stub = new EntityStub('node', 'article');

    $this->assertSame('node', $stub->getEntityType());
    $this->assertSame('article', $stub->getBundle());
  }

  public function testConstructorAcceptsInitialValues(): void {
    $stub = new EntityStub('node', 'article', ['title' => 'Hello']);

    $this->assertTrue($stub->hasValue('title'));
    $this->assertSame('Hello', $stub->getValue('title'));
    $this->assertSame(['title' => 'Hello'], $stub->getValues());
  }

  /**
   * Tests that the bundle defaults to NULL for entity types without bundles.
   */
  public function testBundleDefaultsToNull(): void {
    $stub = new EntityStub('user');

    $this->assertNull($stub->getBundle());
  }

  public function testGetValueReturnsDefaultWhenAbsent(): void {
    $stub = new EntityStub('node', 'article');

    $this->assertNull($stub->getValue('title'));
    $this->assertSame('fallback', $stub->getValue('title', 'fallback'));
  }

  public function testSetValueIsChainable(): void {
    $stub = new EntityStub('node', 'article');

    $returned = $stub->setValue('title', 'Hello')->setValue('promote', 1);

    $this->assertSame($stub, $returned);
    $this->assertSame('Hello', $stub->getValue('title'));
    $this->assertSame(1, $stub->getValue('promote'));
  }

  /**
   * Tests that 'hasValue()' is true even when the stored value is NULL.
   */
  public function testHasValueDistinguishesNullFromAbsent(): void {
    $stub = new EntityStub('node', 'article');
    $stub->setValue('title', NULL);

    $this->assertTrue($stub->hasValue('title'), 'NULL is a stored value.');
    $this->assertNull($stub->getValue('title', 'fallback'), 'Stored NULL is preserved by getValue, not replaced by the default.');
    $this->assertFalse($stub->hasValue('promote'), 'unset key is not a stored value.');
    $this->assertSame('fallback', $stub->getValue('promote', 'fallback'), 'Unset key falls back to the supplied default.');
  }

  public function testRemoveValueDeletesKey(): void {
    $stub = new EntityStub('node', 'article', ['title' => 'Hello']);

    $stub->removeValue('title');

    $this->assertFalse($stub->hasValue('title'));
    $this->assertSame([], $stub->getValues());
  }

  public function testSetValuesReplacesBag(): void {
    $stub = new EntityStub('node', 'article', ['title' => 'Old']);

    $stub->setValues(['promote' => 1]);

    $this->assertFalse($stub->hasValue('title'), 'old keys were dropped.');
    $this->assertSame(1, $stub->getValue('promote'));
  }

  public function testIsSavedFlipsAfterMarkSaved(): void {
    $stub = new EntityStub('node', 'article');

    $this->assertFalse($stub->isSaved());

    $stub->markSaved((object) ['id' => 7]);

    $this->assertTrue($stub->isSaved());
  }

  public function testGetSavedEntityReturnsAttachedObject(): void {
    $entity = (object) ['id' => 7];
    $stub = new EntityStub('node', 'article');
    $stub->markSaved($entity);

    $this->assertSame($entity, $stub->getSavedEntity());
  }

  public function testGetSavedEntityThrowsWhenUnsaved(): void {
    $stub = new EntityStub('node', 'article');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/EntityStub for "node" has not been saved/');

    $stub->getSavedEntity();
  }

  /**
   * Tests that 'getId()' resolves through the saved entity's id() method.
   */
  public function testGetIdReadsFromSavedEntity(): void {
    $entity = new class() {

      /**
       * Returns a fixed identifier mirroring Drupal's entity 'id()' contract.
       */
      public function id(): int {
        return 42;
      }

    };

    $stub = new EntityStub('node', 'article');
    $stub->markSaved($entity);

    $this->assertSame(42, $stub->getId());
  }

  public function testGetIdReturnsNullWhenUnsaved(): void {
    $stub = new EntityStub('node', 'article');

    $this->assertNull($stub->getId());
  }

  public function testGetIdReturnsNullWhenSavedEntityHasNoIdMethod(): void {
    $stub = new EntityStub('node', 'article');
    $stub->markSaved((object) ['identifier' => 'x']);

    $this->assertNull($stub->getId());
  }

  public function testBundleKeyDefaultsToTypeAndIsMutable(): void {
    $stub = new EntityStub('taxonomy_term', 'tags');

    $this->assertSame(EntityStubInterface::DEFAULT_BUNDLE_KEY, $stub->getBundleKey());
    $this->assertSame('type', $stub->getBundleKey());

    $returned = $stub->setBundleKey('vid');

    $this->assertSame($stub, $returned);
    $this->assertSame('vid', $stub->getBundleKey());
  }

  public function testImplementsInterface(): void {
    $this->assertInstanceOf(EntityStubInterface::class, new EntityStub('node'));
  }

}
