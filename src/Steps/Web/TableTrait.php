<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Interact with HTML table elements and assert their content.
 *
 * - Assert table row and column counts.
 * - Assert table column headers.
 * - Assert table empty and non-empty states.
 * - Assert table sort order by column.
 * - Assert text values present in a specific table row.
 * - Assert bulk row content against expected values.
 * - Click links and press buttons within a row identified by part of its
 *   text.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait TableTrait {

  use StringTrait;

  /**
   * Click a link within a row.
   *
   * @code
   * When I click on the link "Edit" in the row containing "Article title"
   * @endcode
   */
  #[When('I click on the link :link in the row containing :partial_text')]
  public function tableClickLinkInRow(string $link, string $partial_text): void {
    $element = $this->tableGetRowByText($partial_text)->findLink($link);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('link in the row containing "%s"', $partial_text), 'id|title|alt|text', $link);
    }

    $element->click();
  }

  /**
   * Press a button within a row.
   *
   * @code
   * When I press the button "Remove" in the row containing "Article title"
   * @endcode
   */
  #[When('I press the button :button in the row containing :partial_text')]
  public function tablePressButtonInRow(string $button, string $partial_text): void {
    $element = $this->tableGetRowByText($partial_text)->findButton($button);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('button in the row containing "%s"', $partial_text), 'id|name|title|alt|value', $button);
    }

    $element->press();
  }

  /**
   * Assert that a table has the expected number of body rows.
   *
   * @code
   * Then the table ".mytable" should have 5 rows
   * @endcode
   */
  #[Then('the table :selector should have :count row(s)')]
  public function tableAssertRowCount(string $selector, string $count): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $table = $this->tableGet($selector);
    $actual = count($this->tableGetRows($table));

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected the table "%s" to have %d row(s), but found %d.', $selector, $count, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table has the expected number of columns.
   *
   * @code
   * Then the table ".mytable" should have 5 columns
   * @endcode
   */
  #[Then('the table :selector should have :count column(s)')]
  public function tableAssertColumnCount(string $selector, string $count): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $table = $this->tableGet($selector);
    $actual = count($this->tableGetHeaders($table));

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected the table "%s" to have %d column(s), but found %d.', $selector, $count, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table contains the expected column headers.
   *
   * @code
   * Then the table ".mytable" should contain the following columns:
   *   | Title  |
   *   | Author |
   *   | Status |
   * @endcode
   */
  #[Then('the table :selector should contain the following columns:')]
  public function tableAssertColumns(string $selector, TableNode $table): void {
    $actual_headers = $this->tableGetHeaders($this->tableGet($selector));

    foreach ($table->getColumn(0) as $expected_column) {
      if (!in_array(trim($expected_column), $actual_headers, TRUE)) {
        throw new ExpectationException(sprintf('The column "%s" was not found in the table "%s". Available columns: %s.', trim($expected_column), $selector, implode(', ', $actual_headers)), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a table is empty (has no body rows).
   *
   * @code
   * Then the table ".mytable" should be empty
   * @endcode
   */
  #[Then('the table :selector should be empty')]
  public function tableAssertEmpty(string $selector): void {
    $table = $this->tableGet($selector);
    $actual = count($this->tableGetRows($table));

    if ($actual !== 0) {
      throw new ExpectationException(sprintf('Expected the table "%s" to be empty, but found %d row(s).', $selector, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table is not empty (has body rows).
   *
   * @code
   * Then the table ".mytable" should not be empty
   * @endcode
   */
  #[Then('the table :selector should not be empty')]
  public function tableAssertNotEmpty(string $selector): void {
    $table = $this->tableGet($selector);

    if (count($this->tableGetRows($table)) === 0) {
      throw new ExpectationException(sprintf('Expected the table "%s" to not be empty, but it has no rows.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table is sorted by a column in a specific direction.
   *
   * @code
   * Then the table ".mytable" should be sorted by the column "Title" in "ascending" order
   * Then the table ".mytable" should be sorted by the column "Date" in "descending" order
   * @endcode
   */
  #[Then('the table :selector should be sorted by the column :column in :direction order')]
  public function tableAssertSortOrder(string $selector, string $column, string $direction): void {
    if ($direction !== 'ascending' && $direction !== 'descending') {
      throw new \RuntimeException(sprintf('Invalid sort direction "%s". Use "ascending" or "descending".', $direction));
    }

    $table = $this->tableGet($selector);
    $values = $this->tableGetColumnValues($table, $this->tableGetColumnIndex($table, $column, $selector));

    if ($values !== $this->tableSortValues($values, $direction)) {
      throw new ExpectationException(sprintf('Expected the table "%s" to be sorted by the column "%s" in %s order. Actual values: %s.', $selector, $column, $direction, implode(', ', $values)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table contains the expected rows.
   *
   * @code
   * Then the table ".mytable" should contain the following rows:
   *   | Title     | Status    |
   *   | Article 1 | Published |
   *   | Article 2 | Draft     |
   * @endcode
   */
  #[Then('the table :selector should contain the following rows:')]
  public function tableAssertRows(string $selector, TableNode $expected_table): void {
    $table = $this->tableGet($selector);

    // Every column is resolved before any row, so a missing column fails even
    // for a table of headers alone.
    foreach ($expected_table->getRow(0) as $expected_header) {
      $this->tableGetColumnIndex($table, $expected_header, $selector);
    }

    foreach ($expected_table->getHash() as $row_index => $expected_row) {
      if (!$this->tableFindRowByValues($table, $expected_row, $selector) instanceof NodeElement) {
        throw new ExpectationException(sprintf('The table "%s" does not contain the row %d with the values [%s].', $selector, $row_index + 1, implode(', ', array_values($expected_row))), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a table row containing a text has the expected values.
   *
   * @code
   * Then the row containing "Article title" should contain the following:
   *   | Published |
   *   | admin     |
   * @endcode
   */
  #[Then('the row containing :partial_text should contain the following:')]
  public function tableAssertRowContainsMultiple(string $partial_text, TableNode $table): void {
    $actual_text = $this->tableGetRowByText($partial_text)->getText();

    foreach ($table->getColumn(0) as $expected_text) {
      if (!str_contains((string) $actual_text, $expected_text)) {
        throw new ExpectationException(sprintf('The row containing "%s" does not contain the text "%s".', $partial_text, $expected_text), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a row contains a value.
   *
   * @code
   * Then the row containing "Article title" should contain the value "Published"
   * @endcode
   */
  #[Then('the row containing :partial_text should contain the value :value')]
  public function tableAssertRowContains(string $partial_text, string $value): void {
    $row = $this->tableGetRowByText($partial_text);

    if (!str_contains($row->getText(), $value)) {
      throw new ExpectationException(sprintf('The row containing "%s" does not contain the text "%s".', $partial_text, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a row does not contain a value.
   *
   * @code
   * Then the row containing "Article title" should not contain the value "Unpublished"
   * @endcode
   */
  #[Then('the row containing :partial_text should not contain the value :value')]
  public function tableAssertRowNotContains(string $partial_text, string $value): void {
    $row = $this->tableGetRowByText($partial_text);

    if (str_contains($row->getText(), $value)) {
      throw new ExpectationException(sprintf('The row containing "%s" contains the text "%s".', $partial_text, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a row contains the link.
   *
   * @code
   * Then the link "Edit" in the row containing "Article title" should exist
   * @endcode
   */
  #[Then('the link :link in the row containing :partial_text should exist')]
  public function tableAssertLinkExistsInRow(string $link, string $partial_text): void {
    if (!$this->tableGetRowByText($partial_text)->findLink($link) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('link in the row containing "%s"', $partial_text), 'id|title|alt|text', $link);
    }
  }

  /**
   * Assert that a row does not contain the link.
   *
   * @code
   * Then the link "Delete" in the row containing "Article title" should not exist
   * @endcode
   */
  #[Then('the link :link in the row containing :partial_text should not exist')]
  public function tableAssertLinkNotExistsInRow(string $link, string $partial_text): void {
    if ($this->tableGetRowByText($partial_text)->findLink($link) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The row containing "%s" has the link "%s".', $partial_text, $link), $this->getSession()->getDriver());
    }
  }

  /**
   * Return the first row on the page containing the text.
   *
   * @param string $row_text
   *   Text identifying the row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When no row contains the text.
   */
  public function tableGetRowByText(string $row_text): NodeElement {
    $row = $this->tableFindRowByText($row_text);

    if (!$row instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'table row', 'text', $row_text);
    }

    return $row;
  }

  /**
   * Get the CSS selector for table header cells.
   */
  public function tableGetHeaderSelector(): string {
    return $this->getOptionString('table', 'header_selector');
  }

  /**
   * Get the CSS selector for table body rows.
   */
  public function tableGetBodyRowSelector(): string {
    return $this->getOptionString('table', 'body_row_selector');
  }

  /**
   * Return the table element matching a CSS selector.
   *
   * @param string $selector
   *   The CSS selector for the table.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the table is not found.
   */
  public function tableGet(string $selector): NodeElement {
    $page = $this->getSession()->getPage();
    $table = $page->find('css', $selector);

    if (!$table) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'table', 'css', $selector);
    }

    return $table;
  }

  /**
   * Get the header texts from a table element.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   *
   * @return array<string>
   *   An array of trimmed header texts.
   */
  public function tableGetHeaders(NodeElement $table): array {
    return array_map(static fn(NodeElement $element): string => trim($element->getText()), $table->findAll('css', $this->tableGetHeaderSelector()));
  }

  /**
   * Get the body rows from a table element.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   *
   * @return array<\Behat\Mink\Element\NodeElement>
   *   An array of row elements.
   */
  public function tableGetRows(NodeElement $table): array {
    return $table->findAll('css', $this->tableGetBodyRowSelector());
  }

  /**
   * Get the index of a column by its header text.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   * @param string $column
   *   The column header text.
   * @param string $selector
   *   The table selector for error messages.
   *
   * @return int
   *   The column index.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the column is not found.
   */
  public function tableGetColumnIndex(NodeElement $table, string $column, string $selector): int {
    $headers = $this->tableGetHeaders($table);
    $index = array_search($column, $headers, TRUE);

    if ($index === FALSE) {
      throw new ExpectationException(sprintf('The column "%s" was not found in the table "%s". Available columns: %s.', $column, $selector, implode(', ', $headers)), $this->getSession()->getDriver());
    }

    return (int) $index;
  }

  /**
   * Get the texts of a column's cells in the body rows of a table.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   * @param int $column_index
   *   The 0-based index of the column.
   *
   * @return array<int, string>
   *   The trimmed cell texts, in row order. A row without the cell adds none.
   */
  public function tableGetColumnValues(NodeElement $table, int $column_index): array {
    $values = [];

    foreach ($this->tableGetRows($table) as $row) {
      $cells = $row->findAll('css', 'td');

      if (isset($cells[$column_index])) {
        $values[] = trim($cells[$column_index]->getText());
      }
    }

    return $values;
  }

  /**
   * Find the first body row whose cells hold the given values.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   * @param array<int|string, string> $values
   *   The expected cell texts, keyed by column header.
   * @param string $selector
   *   The table selector for error messages.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The row, or NULL when no body row holds every value.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When a column is not found.
   */
  public function tableFindRowByValues(NodeElement $table, array $values, string $selector): ?NodeElement {
    $column_values = [];

    foreach ($values as $header => $value) {
      $column_values[$this->tableGetColumnIndex($table, (string) $header, $selector)] = $value;
    }

    foreach ($this->tableGetRows($table) as $row) {
      $cells = $row->findAll('css', 'td');
      $is_match = TRUE;

      foreach ($column_values as $column_index => $value) {
        $actual_value = isset($cells[$column_index]) ? trim($cells[$column_index]->getText()) : '';

        if ($actual_value !== $value) {
          $is_match = FALSE;
          break;
        }
      }

      if ($is_match) {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Sort cell texts in natural, case-insensitive order.
   *
   * @param array<int, string> $values
   *   The cell texts.
   * @param string $direction
   *   The sort direction: 'ascending' or 'descending'.
   *
   * @return array<int, string>
   *   The sorted texts.
   */
  protected function tableSortValues(array $values, string $direction): array {
    natcasesort($values);
    $values = array_values($values);

    return $direction === 'descending' ? array_reverse($values) : $values;
  }

  /**
   * Find a table row containing the given text.
   *
   * @param string $row_text
   *   The text to search for within a table row.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The row element if found, or NULL.
   */
  public function tableFindRowByText(string $row_text): ?NodeElement {
    $rows = $this->getSession()->getPage()->findAll('css', 'table tr');

    foreach ($rows as $row) {
      if (str_contains((string) $row->getText(), $row_text)) {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function tableConfigSchema(): array {
    return [
      new Option('header_selector', default: 'thead tr th', description: 'CSS selector of a table header cell, relative to the table.'),
      new Option('body_row_selector', default: 'tbody tr', description: 'CSS selector of a table body row, relative to the table.'),
    ];
  }

}
