<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Mink\Exception\ExpectationException;

/**
 * Trait TaxonomyTrait.
 */
trait TaxonomyTrait {

  /**
   * @Given taxonomy term :name from vocabulary :vocab exists
   */
  public function taxonomyAssertTermExistsByName($name, $vocabulary) {
    $vocab = taxonomy_vocabulary_machine_name_load($vocabulary);
    if (!$vocab) {
      throw new RuntimeException(sprintf('"%s" vocabulary does not exist', $vocabulary));
    }
    $found = taxonomy_term_load_multiple(NULL, [
      'name' => $name,
      'vid' => $vocab->vid,
    ]);

    if (count($found) == 0) {
      throw new ExpectationException(printf('Taxonomy term "%s" from vocabulary "%s" does not exist', $name, $vocabulary));
    }
  }

}
