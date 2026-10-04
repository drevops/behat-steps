<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\MetatagTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for MetatagTrait.
 */
#[CoversTrait(MetatagTrait::class)]
class MetatagTraitTest extends UnitTestCase {

  public function testFetchUrlReturnsTheBody(): void {
    $object = new MetatagTraitTestImplementation(new MockResponse('<html lang="de"></html>'));

    $this->assertSame('<html lang="de"></html>', $object->metatagFetchUrl('http://example.com/de'));
    $this->assertSame(['timeout' => 30], $object->detachedOptions);
  }

  /**
   * Tests that a failed fetch names the alternate page.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the alternate page answers with.
   * @param string $message
   *   The message the fetch is expected to fail with.
   */
  #[DataProvider('dataProviderFetchUrlFailureNamesThePage')]
  public function testFetchUrlFailureNamesThePage(MockResponse $response, string $message): void {
    $object = new MetatagTraitTestImplementation($response);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    $object->metatagFetchUrl('http://example.com/de');
  }

  public static function dataProviderFetchUrlFailureNamesThePage(): \Iterator {
    yield 'a missing page' => [new MockResponse('Not found', ['http_code' => 404]), 'The hreflang alternate page "http://example.com/de" returned HTTP status 404.'];
    yield 'an unreachable host' => [new MockResponse('', ['error' => 'Could not resolve host']), 'Failed to fetch the hreflang alternate page "http://example.com/de": Could not resolve host'];
  }

}

/**
 * Test implementation of MetatagTrait.
 */
class MetatagTraitTestImplementation extends WebRawContext {

  use MetatagTrait {
    metatagFetchUrl as public;
  }

  /**
   * The options the trait passed to httpDetachedClient().
   *
   * @var array<string, mixed>
   */
  public array $detachedOptions = [];

  /**
   * Constructs a MetatagTraitTestImplementation object.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the detached browser answers with.
   */
  public function __construct(protected MockResponse $response) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function httpDetachedClient(array $options = []): AbstractBrowser {
    $this->detachedOptions = $options;

    return new HttpBrowser(new MockHttpClient($this->response));
  }

}
