<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

use Behat\Gherkin\Node\TableNode;

/**
 * Replaces tokens in the cells of a step's table argument.
 *
 * Each trait that rewrites tokens in step arguments owns its grammar, its
 * transforms and its skip tag, and shares the walk over a table's cells.
 */
trait TokenTrait {

  /**
   * Replace the tokens in every cell of a table.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table argument.
   * @param callable(string): string $replace
   *   Returns the text of a cell with its tokens replaced.
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   A new table holding the replaced cells.
   */
  protected function tokenReplaceInTable(TableNode $table, callable $replace): TableNode {
    $rows = [];

    foreach ($table->getRows() as $row) {
      $rows[] = array_map($replace, $row);
    }

    return new TableNode($rows);
  }

}
