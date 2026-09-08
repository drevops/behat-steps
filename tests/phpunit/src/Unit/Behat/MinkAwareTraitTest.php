<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat;

use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Mink\WebAssert;
use DrevOps\BehatSteps\Behat\MinkAwareTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\MinkAwareObject;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Mink session access a non-context class gets from the trait.
 */
#[CoversTrait(MinkAwareTrait::class)]
class MinkAwareTraitTest extends TestCase {

  public function testTheMinkInstanceIsReturned(): void {
    $mink = $this->createMink($this->createMock(Session::class));
    $host = new MinkAwareObject();
    $host->setMink($mink);

    $this->assertSame($mink, $host->getMink());
  }

  public function testTheDefaultSessionIsReturned(): void {
    $session = $this->createMock(Session::class);
    $host = new MinkAwareObject();
    $host->setMink($this->createMink($session));

    $this->assertSame($session, $host->getSession());
  }

  public function testAssertSessionReturnsTheSessionsAssertions(): void {
    $host = new MinkAwareObject();
    $host->setMink($this->createMink($this->createMock(Session::class)));

    $this->assertInstanceOf(WebAssert::class, $host->assertSession());
  }

  public function testParametersDefaultToAnEmptyMap(): void {
    $host = new MinkAwareObject();

    $this->assertSame([], $host->getMinkParameters());
    $this->assertNull($host->getMinkParameter('base_url'));
  }

  public function testParametersAreReadBackAsSet(): void {
    $host = new MinkAwareObject();
    $host->setMinkParameters(['base_url' => 'http://localhost']);

    $this->assertSame(['base_url' => 'http://localhost'], $host->getMinkParameters());
    $this->assertSame('http://localhost', $host->getMinkParameter('base_url'));
  }

  public function testSingleParameterCanBeOverridden(): void {
    $host = new MinkAwareObject();
    $host->setMinkParameters(['base_url' => 'http://localhost']);

    $host->setMinkParameter('base_url', 'http://example.com');

    $this->assertSame('http://example.com', $host->getMinkParameter('base_url'));
  }

  /**
   * Tests how a path is turned into a URL under the configured base.
   *
   * @param string $base_url
   *   The configured 'base_url'.
   * @param string $path
   *   The path to locate.
   * @param string $expected
   *   The URL the path is expected to resolve to.
   */
  #[DataProvider('dataProviderLocatePath')]
  public function testLocatePath(string $base_url, string $path, string $expected): void {
    $host = new MinkAwareObject();
    $host->setMinkParameters(['base_url' => $base_url]);

    $this->assertSame($expected, $host->locatePath($path));
  }

  public static function dataProviderLocatePath(): \Iterator {
    yield 'relative path is appended' => ['http://localhost', '/user', 'http://localhost/user'];
    yield 'trailing and leading slashes collapse' => ['http://localhost/', '/user', 'http://localhost/user'];
    yield 'path without a leading slash' => ['http://localhost', 'user', 'http://localhost/user'];
    yield 'absolute URL is left alone' => ['http://localhost', 'http://example.com/user', 'http://example.com/user'];
  }

  public function testVisitPathVisitsTheLocatedUrl(): void {
    $session = $this->createMock(Session::class);
    $session->expects($this->once())->method('visit')->with('http://localhost/user');

    $host = new MinkAwareObject();
    $host->setMink($this->createMink($session));
    $host->setMinkParameters(['base_url' => 'http://localhost']);

    $host->visitPath('/user');
  }

  /**
   * Builds a Mink instance holding one default session.
   */
  protected function createMink(Session $session): Mink {
    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    return $mink;
  }

}
