<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaValidator;

final class SchemaTest extends BaseTestCase
{
    public function testSchemaIsValid(): void
    {
        $this->createClient();

        $validator = new SchemaValidator(self::$kernel->getContainer()->get('doctrine.orm.entity_manager'));
        $errors = $validator->validateMapping();

        $this->assertEmpty($errors, "Validation errors found: \n\n".var_export($errors, true));
    }
}
