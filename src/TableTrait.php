<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

/**
 * Interact with HTML table elements and assert their content.
 *
 * - Assert table row counts in tbody.
 * - Assert table column headers in thead.
 * - Assert text values present in a specific table row.
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
    $page = $this->getSession()->getPage();
    $table = $page->find('css', $selector);

    if (!$table) {
      throw new ExpectationException(sprintf('Table with selector "%s" not found.', $selector), $this->getSession()->getDriver());
    }

    $rows = $table->findAll('css', 'tbody tr');
    $actual = count($rows);

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected table "%s" to have %d row(s), but found %d.', $selector, $count, $actual), $this->getSession()->getDriver());
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
    $page = $this->getSession()->getPage();
    $table_element = $page->find('css', $selector);

    if (!$table_element) {
      throw new ExpectationException(sprintf('Table with selector "%s" not found.', $selector), $this->getSession()->getDriver());
    }

    $header_elements = $table_element->findAll('css', 'th');
    $actual_headers = [];
    foreach ($header_elements as $header) {
      $actual_headers[] = trim($header->getText());
    }

    foreach ($table->getColumn(0) as $expected_column) {
      if (!in_array($expected_column, $actual_headers, TRUE)) {
        throw new ExpectationException(sprintf('Column "%s" not found in table "%s". Available columns: %s.', $expected_column, $selector, implode(', ', $actual_headers)), $this->getSession()->getDriver());
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
  public function tableAssertMultipleTextsInRow(string $rowText, TableNode $table): void {
    $row = $this->tableFindRowByText($rowText);

    if (!$row) {
      throw new ExpectationException(sprintf('Table row containing text "%s" not found.', $rowText), $this->getSession()->getDriver());
    }

    $row_text = $row->getText();
    foreach ($table->getColumn(0) as $expected_text) {
      if (!str_contains($row_text, $expected_text)) {
        throw new ExpectationException(sprintf('Row containing "%s" does not contain expected text "%s".', $rowText, $expected_text), $this->getSession()->getDriver());
      }
    }
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
  protected function tableFindRowByText(string $text) {
    $rows = $this->getSession()->getPage()->findAll('css', 'table tr');

    foreach ($rows as $row) {
      if (str_contains($row->getText(), $text)) {
        return $row;
      }
    }

    return NULL;
  }

}
