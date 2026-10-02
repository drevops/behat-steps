<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\DrushBackend;
use DrevOps\BehatSteps\Backend\Drush\DrushResult;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\ProcessStubDrushBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Tests the non-throwing 'drushResult()' executor and the 'DrushResult' VO.
 */
#[CoversClass(DrushBackend::class)]
#[CoversClass(DrushResult::class)]
#[Group('backends')]
#[Group('drush')]
class DrushBackendResultTest extends TestCase {

  /**
   * Tests that 'DrushResult' exposes the values it was constructed with.
   *
   * @param int $exit_code
   *   The exit code to construct with.
   * @param string $output
   *   The standard output to construct with.
   * @param string $error_output
   *   The standard error output to construct with.
   */
  #[DataProvider('dataProviderDrushResultExposesValues')]
  public function testDrushResultExposesValues(int $exit_code, string $output, string $error_output): void {
    $result = new DrushResult($exit_code, $output, $error_output);

    $this->assertSame($exit_code, $result->exitCode);
    $this->assertSame($output, $result->output);
    $this->assertSame($error_output, $result->errorOutput);
  }

  /**
   * Data provider for 'testDrushResultExposesValues()'.
   */
  public static function dataProviderDrushResultExposesValues(): \Iterator {
    yield 'success with stdout' => [0, 'the output', ''];
    yield 'failure with stderr' => [1, '', 'boom'];
    yield 'both streams populated' => [3, 'partial', 'warning'];
  }

  /**
   * Tests that 'drushResult()' maps a finished process onto a 'DrushResult'.
   *
   * @param int|null $exit_code
   *   The exit code reported by the process (NULL when signalled).
   * @param string $output
   *   The process standard output.
   * @param string $error_output
   *   The process standard error output.
   * @param int $expected_exit_code
   *   The exit code expected on the resulting 'DrushResult'.
   */
  #[DataProvider('dataProviderDrushResultMapsProcess')]
  public function testDrushResultMapsProcess(?int $exit_code, string $output, string $error_output, int $expected_exit_code): void {
    $backend = new ProcessStubDrushBackend('alias');
    $backend->stubProcess = $this->mockProcess($exit_code, $output, $error_output);

    $result = $backend->drushResult('version', [], ['format' => 'json']);

    $this->assertInstanceOf(DrushResult::class, $result);
    $this->assertSame($expected_exit_code, $result->exitCode);
    $this->assertSame($output, $result->output);
    $this->assertSame($error_output, $result->errorOutput);
  }

  /**
   * Data provider for 'testDrushResultMapsProcess()'.
   */
  public static function dataProviderDrushResultMapsProcess(): \Iterator {
    yield 'success' => [0, 'stdout text', '', 0];
    yield 'failure with stderr' => [2, '', 'stderr text', 2];
    yield 'signalled process maps null exit to one' => [NULL, '', '', 1];
  }

  /**
   * Tests that 'drush()' throws and carries stderr when the command fails.
   */
  public function testDrushThrowsWithErrorOutputOnFailure(): void {
    $backend = new ProcessStubDrushBackend('alias');
    $backend->stubProcess = $this->mockProcess(2, '', 'the failure reason');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('the failure reason');

    $backend->drush('cron');
  }

  /**
   * Tests that 'drush()' returns stdout, or stderr when stdout is empty/'0'.
   *
   * @param string $output
   *   The process standard output.
   * @param string $error_output
   *   The process standard error output.
   * @param string $expected
   *   The value 'drush()' is expected to return.
   */
  #[DataProvider('dataProviderDrushStdoutElseStderrFallback')]
  public function testDrushStdoutElseStderrFallback(string $output, string $error_output, string $expected): void {
    $backend = new ProcessStubDrushBackend('alias');
    $backend->stubProcess = $this->mockProcess(0, $output, $error_output);

    $this->assertSame($expected, $backend->drush('status'));
  }

  /**
   * Data provider for 'testDrushStdoutElseStderrFallback()'.
   */
  public static function dataProviderDrushStdoutElseStderrFallback(): \Iterator {
    yield 'stdout present' => ['hello', 'ignored stderr', 'hello'];
    yield 'empty stdout falls back to stderr' => ['', 'stderr fallback', 'stderr fallback'];
    yield 'zero-string stdout falls back to stderr' => ['0', 'stderr fallback', 'stderr fallback'];
  }

  /**
   * Builds a finished-process double with stubbed exit code and output.
   *
   * @param int|null $exit_code
   *   The exit code the process reports.
   * @param string $output
   *   The standard output the process reports.
   * @param string $error_output
   *   The standard error output the process reports.
   *
   * @return \Symfony\Component\Process\Process
   *   The stubbed process.
   */
  protected function mockProcess(?int $exit_code, string $output, string $error_output): Process {
    $process = $this->createMock(Process::class);
    $process->method('getExitCode')->willReturn($exit_code);
    $process->method('getOutput')->willReturn($output);
    $process->method('getErrorOutput')->willReturn($error_output);

    return $process;
  }

}
