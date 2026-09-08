<?php

declare(strict_types=1);

namespace Drupal\druxt\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that an exposed resource is safe to expose.
 */
#[Constraint(
  id: 'DruxtResource',
  label: new TranslatableMarkup('Druxt exposed resource', [], ['context' => 'Validation']),
)]
class DruxtResourceConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   */
  public string $message = '%resource cannot be added to this list, because @entity_type is a content entity type. Everything on this list is readable by everyone holding the access druxt resources permission, and there is no way to scope it to a role or a consumer, so adding a content entity type would expose every entity of that type including unpublished ones. A module may expose it with hook_druxt_resources_alter() instead, where the change is reviewable code.';

}
