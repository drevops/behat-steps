<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Browser\Adapter;

/**
 * Overrides the browser clock and timezone over the Chrome DevTools Protocol.
 *
 * Implements 'ClockCapabilityInterface' for an adapter that can send a CDP
 * command. The adapter supplies the connection and the transport.
 */
trait DevToolsClockTrait {

  /**
   * Script that replaces 'Date' with a clock running at a fixed offset.
   *
   * 'Date()' called without 'new' still returns a string, 'instanceof Date'
   * still holds and a subclass keeps its prototype. The native 'Date' is kept
   * under a global symbol, so a second override offsets the native clock
   * rather than the first override.
   */
  protected const string DEVTOOLS_CLOCK_SCRIPT = <<<'JS'
    (() => {
      const key = Symbol.for('drevops.behat_steps.native_date');
      const NativeDate = globalThis[key] || globalThis.Date;
      Object.defineProperty(globalThis, key, {value: NativeDate, configurable: true});
      const offset = %d;
      const now = () => NativeDate.now() + offset;
      const MockDate = function Date(...args) {
        if (!new.target) {
          return new NativeDate(now()).toString();
        }
        return Reflect.construct(NativeDate, args.length === 0 ? [now()] : args, new.target);
      };
      Object.setPrototypeOf(MockDate, NativeDate);
      Object.defineProperty(MockDate, 'length', {value: NativeDate.length});
      MockDate.prototype = NativeDate.prototype;
      MockDate.now = now;
      NativeDate.prototype.constructor = MockDate;
      globalThis.Date = MockDate;
    })();
    JS;

  /**
   * Identifiers of the added clock scripts, keyed by the CDP connection.
   *
   * An adapter is built per context, but the script outlives the scenario on
   * the same connection. Keying by the connection lets a later scenario
   * remove it, and drops the identifier with the browser session it belongs
   * to.
   *
   * @var \WeakMap<object, string>|null
   */
  protected static ?\WeakMap $devToolsClockScriptIds = NULL;

  /**
   * {@inheritdoc}
   */
  public function clockSetTime(int $timestamp): void {
    $this->devToolsClockRemoveScript();

    // A fixed offset keeps the clock ticking on from the given time instead of
    // restarting at each load. The 'performance' clock is read rather than
    // 'Date', which an earlier script may already have replaced.
    $browser_clock = $this->devToolsSend('Runtime.evaluate', ['expression' => 'performance.timeOrigin + performance.now()', 'returnByValue' => TRUE]);
    $evaluated = $browser_clock['result'] ?? NULL;
    $browser_now = is_array($evaluated) ? ($evaluated['value'] ?? NULL) : NULL;
    $now = is_numeric($browser_now) ? (float) $browser_now : microtime(TRUE) * 1000;
    $offset = $timestamp * 1000 - (int) round($now);

    $result = $this->devToolsSend('Page.addScriptToEvaluateOnNewDocument', ['source' => sprintf(static::DEVTOOLS_CLOCK_SCRIPT, $offset)]);

    if (!isset($result['identifier']) || !is_scalar($result['identifier'])) {
      throw new \RuntimeException('The browser did not return an identifier for the clock script.');
    }

    static::devToolsClockGetScriptIds()[$this->devToolsConnection()] = (string) $result['identifier'];
  }

  /**
   * {@inheritdoc}
   */
  public function clockSetTimezone(string $timezone): void {
    $this->devToolsSend('Emulation.setTimezoneOverride', ['timezoneId' => $timezone]);
  }

  /**
   * {@inheritdoc}
   */
  public function clockReset(): void {
    $this->devToolsClockRemoveScript();

    // An empty timezone ID clears any override, set by this adapter or not.
    $this->devToolsSend('Emulation.setTimezoneOverride', ['timezoneId' => '']);
  }

  /**
   * Removes the clock script, so documents loaded next read the real time.
   */
  protected function devToolsClockRemoveScript(): void {
    $script_ids = static::devToolsClockGetScriptIds();
    $connection = $this->devToolsConnection();

    if (!isset($script_ids[$connection])) {
      return;
    }

    $this->devToolsSend('Page.removeScriptToEvaluateOnNewDocument', ['identifier' => $script_ids[$connection]]);

    unset($script_ids[$connection]);
  }

  /**
   * Returns the clock script identifiers, creating the map on first use.
   *
   * @return \WeakMap<object, string>
   *   The identifiers, keyed by the CDP connection.
   */
  protected static function devToolsClockGetScriptIds(): \WeakMap {
    static::$devToolsClockScriptIds ??= new \WeakMap();

    return static::$devToolsClockScriptIds;
  }

  /**
   * Returns the object CDP commands go through for the current page.
   *
   * A new browser session or window gives a new object, so state kept per
   * connection never reaches a browser it was not set in.
   *
   * @return object
   *   The connection.
   */
  abstract protected function devToolsConnection(): object;

  /**
   * Sends a Chrome DevTools Protocol command to the page the session drives.
   *
   * @param string $method
   *   The CDP method, such as 'Emulation.setTimezoneOverride'.
   * @param array<string, mixed> $params
   *   The method parameters.
   *
   * @return array<string|int, mixed>
   *   The result of the command.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the browser does not accept CDP commands.
   */
  abstract protected function devToolsSend(string $method, array $params): array;

}
