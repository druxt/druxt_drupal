<?php

declare(strict_types=1);

namespace Drupal\druxt\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the DruxtResource constraint.
 */
final class DruxtResourceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs the validator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof DruxtResourceConstraint) {
      throw new UnexpectedTypeException($constraint, DruxtResourceConstraint::class);
    }

    if ($value === NULL || $value === '') {
      return;
    }

    $resource = (string) $value;
    if (in_array($resource, druxt_grandfathered_resources(), TRUE)) {
      return;
    }

    [$entity_type_id] = explode('--', $resource, 2);

    // An entity type this site does not have grants nothing, because no
    // route exists for it. Rejecting it would stop a site sharing
    // configuration with one that does have the module.
    if (!isset($this->entityTypeManager->getDefinitions()[$entity_type_id])) {
      return;
    }

    if (druxt_resource_is_allowed($resource)) {
      return;
    }

    $this->context->addViolation($constraint->message, [
      '%resource' => $resource,
      '@entity_type' => $entity_type_id,
    ]);
  }

}
