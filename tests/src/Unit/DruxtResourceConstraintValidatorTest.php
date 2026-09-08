<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\druxt\Plugin\Validation\Constraint\DruxtResourceConstraintValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Tests the resource constraint validator's contract.
 *
 * @group druxt
 */
#[Group('druxt')]
class DruxtResourceConstraintValidatorTest extends UnitTestCase {

  /**
   * Tests that the validator refuses a constraint it does not implement.
   *
   * Symfony hands a validator whatever constraint names it, so a mistake in
   * a plugin definition would otherwise be checked against the wrong rule
   * and quietly pass.
   */
  public function testValidateRefusesAnotherConstraint(): void {
    $validator = new DruxtResourceConstraintValidator(
      $this->createMock(EntityTypeManagerInterface::class)
    );

    $this->expectException(UnexpectedTypeException::class);
    $validator->validate('view--view', new NotBlank());
  }

}
