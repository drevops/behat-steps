<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

/**
 * Interact with HTML table elements and assert their content.
 *
 * - Assert table row and column counts.
 * - Assert table column headers in thead.
 * - Assert table empty and non-empty states.
 * - Assert table sort order by column.
 * - Assert text values present in a specific table row.
 * - Assert bulk row content against expected values.
 */
trait TableTrait {

  /**
   * Assert that a table has the expected number of rows in its tbody.
   *
   * @code
   * Then the table ".views-table" should have 5 rows
   * @endcode
   */
  #[Then('the table :selector should have :count row(s)')]
  public function tableAssertRowCount(string $selector, int $count): void {
    $table = $this->tableFindTable($selector);
    $rows = $table->findAll('css', $this->tableGetBodyRowSelector());
    $actual = count($rows);

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected table "%s" to have %d row(s), but found %d.', $selector, $count, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table has the expected number of columns.
   *
   * @code
   * Then the table ".views-table" should have 5 columns
   * @endcode
   */
  #[Then('the table :selector should have :count column(s)')]
  public function tableAssertColumnCount(string $selector, int $count): void {
    $table = $this->tableFindTable($selector);
    $headers = $table->findAll('css', $this->tableGetHeaderSelector());
    $actual = count($headers);

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected table "%s" to have %d column(s), but found %d.', $selector, $count, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table contains the expected column headers.
   *
   * @code
   * Then the table ".views-table" should contain the following columns:
   *   | Title  |
   *   | Author |
   *   | Status |
   * @endcode
   */
  #[Then('the table :selector should contain the following columns:')]
  public function tableAssertColumns(string $selector, TableNode $table): void {
    $table_element = $this->tableFindTable($selector);
    $header_elements = $table_element->findAll('css', $this->tableGetHeaderSelector());
    $actual_headers = [];
    foreach ($header_elements as $header) {
      $actual_headers[] = trim($header->getText());
    }

    foreach ($table->getColumn(0) as $expected_column) {
      $expected_column = trim($expected_column);
      if (!in_array($expected_column, $actual_headers, TRUE)) {
        throw new ExpectationException(sprintf('Column "%s" not found in table "%s". Available columns: %s.', $expected_column, $selector, implode(', ', $actual_headers)), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a table is empty (has no rows in tbody).
   *
   * @code
   * Then the table ".views-table" should be empty
   * @endcode
   */
  #[Then('the table :selector should be empty')]
  public function tableAssertEmpty(string $selector): void {
    $table = $this->tableFindTable($selector);
    $rows = $table->findAll('css', $this->tableGetBodyRowSelector());
    $actual = count($rows);

    if ($actual !== 0) {
      throw new ExpectationException(sprintf('Expected table "%s" to be empty, but found %d row(s).', $selector, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table is not empty (has rows in tbody).
   *
   * @code
   * Then the table ".views-table" should not be empty
   * @endcode
   */
  #[Then('the table :selector should not be empty')]
  public function tableAssertNotEmpty(string $selector): void {
    $table = $this->tableFindTable($selector);
    $rows = $table->findAll('css', $this->tableGetBodyRowSelector());

    if (count($rows) === 0) {
      throw new ExpectationException(sprintf('Expected table "%s" to not be empty, but it has no rows.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table is sorted by a column in a specific direction.
   *
   * @code
   * Then the table ".views-table" should be sorted by "Title" in "ascending" order
   * Then the table ".views-table" should be sorted by "Date" in "descending" order
   * @endcode
   */
  #[Then('the table :selector should be sorted by :column in :direction order')]
  public function tableAssertSortOrder(string $selector, string $column, string $direction): void {
    if ($direction !== 'ascending' && $direction !== 'descending') {
      throw new ExpectationException(sprintf('Invalid sort direction "%s". Use "ascending" or "descending".', $direction), $this->getSession()->getDriver());
    }

    $table = $this->tableFindTable($selector);

    $header_elements = $table->findAll('css', $this->tableGetHeaderSelector());
    $column_index = NULL;
    foreach ($header_elements as $index => $header) {
      if (trim($header->getText()) === $column) {
        $column_index = $index;
        break;
      }
    }

    if ($column_index === NULL) {
      throw new ExpectationException(sprintf('Column "%s" not found in table "%s".', $column, $selector), $this->getSession()->getDriver());
    }

    $rows = $table->findAll('css', $this->tableGetBodyRowSelector());
    $values = [];
    foreach ($rows as $row) {
      $cells = $row->findAll('css', 'td');
      if (isset($cells[$column_index])) {
        $values[] = trim($cells[$column_index]->getText());
      }
    }

    $sorted = $values;
    natcasesort($sorted);
    $sorted = array_values($sorted);

    if ($direction === 'descending') {
      $sorted = array_reverse($sorted);
    }

    if ($values !== $sorted) {
      throw new ExpectationException(sprintf('Expected table "%s" to be sorted by "%s" in %s order. Actual values: %s.', $selector, $column, $direction, implode(', ', $values)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a table contains the expected rows.
   *
   * @code
   * Then the table ".views-table" should contain the following rows:
   *   | Title     | Status    |
   *   | Article 1 | Published |
   *   | Article 2 | Draft     |
   * @endcode
   */
  #[Then('the table :selector should contain the following rows:')]
  public function tableAssertRows(string $selector, TableNode $expected_table): void {
    $table = $this->tableFindTable($selector);

    $header_elements = $table->findAll('css', $this->tableGetHeaderSelector());
    $actual_headers = [];
    foreach ($header_elements as $header) {
      $actual_headers[] = trim($header->getText());
    }

    $expected_headers = $expected_table->getRow(0);
    $column_indices = [];
    foreach ($expected_headers as $expected_header) {
      $index = array_search($expected_header, $actual_headers, TRUE);
      if ($index === FALSE) {
        throw new ExpectationException(sprintf('Column "%s" not found in table "%s". Available columns: %s.', $expected_header, $selector, implode(', ', $actual_headers)), $this->getSession()->getDriver());
      }
      $column_indices[] = $index;
    }

    $rows = $table->findAll('css', $this->tableGetBodyRowSelector());
    $expected_rows = $expected_table->getHash();

    foreach ($expected_rows as $row_index => $expected_row) {
      $found = FALSE;
      foreach ($rows as $actual_row) {
        $cells = $actual_row->findAll('css', 'td');
        $match = TRUE;
        foreach ($column_indices as $col_pos => $col_index) {
          $expected_value = $expected_row[$expected_headers[$col_pos]];
          $actual_value = isset($cells[$col_index]) ? trim($cells[$col_index]->getText()) : '';
          if ($actual_value !== $expected_value) {
            $match = FALSE;
            break;
          }
        }
        if ($match) {
          $found = TRUE;
          break;
        }
      }

      if (!$found) {
        throw new ExpectationException(sprintf('Row %d with values [%s] not found in table "%s".', $row_index + 1, implode(', ', array_values($expected_row)), $selector), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a table row containing a text has the expected values.
   *
   * @code
   * Then the "Article title" row should contain the following:
   *   | Published |
   *   | admin     |
   * @endcode
   */
  #[Then('the :rowText row should contain the following:')]
  public function tableAssertMultipleTextsInRow(string $row_text, TableNode $table): void {
    $row = $this->tableFindRowByText($row_text);

    if (!$row) {
      throw new ExpectationException(sprintf('Table row containing text "%s" not found.', $row_text), $this->getSession()->getDriver());
    }

    $actual_text = $row->getText();
    foreach ($table->getColumn(0) as $expected_text) {
      if (!str_contains((string) $actual_text, $expected_text)) {
        throw new ExpectationException(sprintf('Row containing "%s" does not contain expected text "%s".', $row_text, $expected_text), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Get the CSS selector for table header cells.
   */
  protected function tableGetHeaderSelector(): string {
    return 'thead tr th';
  }

  /**
   * Get the CSS selector for table body rows.
   */
  protected function tableGetBodyRowSelector(): string {
    return 'tbody tr';
  }

  /**
   * Find a table element by CSS selector.
   *
   * @param string $selector
   *   The CSS selector for the table.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table element.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the table is not found.
   */
  protected function tableFindTable(string $selector): NodeElement {
    $page = $this->getSession()->getPage();
    $table = $page->find('css', $selector);

    if (!$table) {
      throw new ExpectationException(sprintf('Table with selector "%s" not found.', $selector), $this->getSession()->getDriver());
    }

    return $table;
  }

  /**
   * Find a table row containing the given text.
   *
   * @param string $text
   *   The text to search for within a table row.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The row element if found, or NULL.
   */
  protected function tableFindRowByText(string $text): ?NodeElement {
    $rows = $this->getSession()->getPage()->findAll('css', 'table tr');

    foreach ($rows as $row) {
      if (str_contains((string) $row->getText(), $text)) {
        return $row;
      }
    }

    return NULL;
  }

}
