<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Generic;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Steps\Generic\ModalTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for ModalTrait.
 */
#[CoversTrait(ModalTrait::class)]
class ModalTraitTest extends UnitTestCase {

  /**
   * A test implementation of ModalTrait.
   */
  protected ModalTraitTestImplementation $testObject;

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->page = $this->createMock(DocumentElement::class);

    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($this->page);
    $session->method('getDriver')->willReturn($this->createMock(DriverInterface::class));

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $this->testObject = new ModalTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  public function testCloseThrowsWhenVisibleModalHasNoCloseButton(): void {
    $modal = $this->createVisibleModal();
    // One find() per close selector.
    $modal->expects($this->exactly(3))->method('find')->willReturn(NULL);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Modal close button matching css ".ui-dialog-titlebar-close, [data-dismiss="modal"], .btn-close" not found.');

    $this->testObject->modalClose();
  }

  #[DataProvider('dataProviderAssertContainsThrowsWhenModalHasNoContentElement')]
  public function testAssertContainsThrowsWhenModalHasNoContentElement(string $method): void {
    $modal = $this->createVisibleModal();
    // One find() per content selector.
    $modal->expects($this->exactly(3))->method('find')->willReturn(NULL);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Modal content element matching css ".ui-dialog-content, .modal-content, .modal-body" not found.');

    $this->testObject->{$method}('Welcome');
  }

  public static function dataProviderAssertContainsThrowsWhenModalHasNoContentElement(): array {
    return [
      'should contain' => ['modalAssertContains'],
      'should not contain' => ['modalAssertNotContains'],
    ];
  }

  /**
   * Put a visible modal on the page.
   *
   * A visible modal passes modalFindVisible(), so the failure under test is
   * the lookup inside the modal.
   *
   * @return \Behat\Mink\Element\NodeElement&\PHPUnit\Framework\MockObject\MockObject
   *   The modal double.
   */
  protected function createVisibleModal(): NodeElement&MockObject {
    $modal = $this->createMock(NodeElement::class);
    $modal->method('isVisible')->willReturn(TRUE);
    $this->page->method('findAll')->with('css', '.ui-dialog')->willReturn([$modal]);

    return $modal;
  }

}

/**
 * Test implementation of ModalTrait.
 */
class ModalTraitTestImplementation extends RawMinkContext {

  use ModalTrait;

}
