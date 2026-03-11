<?php

declare(strict_types=1);

namespace InventoryAgent\Tests;

use InventoryAgent\AdminValidator;
use PHPUnit\Framework\TestCase;

class AdminValidatorTest extends TestCase
{
    /** @dataProvider validAdminProvider */
    public function testValidAdminIds(string $modifier): void
    {
        $this->assertTrue(AdminValidator::isValid($modifier));
    }

    /** @return array<string, array{string}> */
    public static function validAdminProvider(): array
    {
        return [
            'plain -adm suffix'            => ['jsmith-adm'],
            'uppercase before suffix'      => ['JSmith-ADM'],
            'leading/trailing whitespace'  => ['  jsmith-adm  '],
            'only -adm'                    => ['-adm'],
        ];
    }

    /** @dataProvider invalidAdminProvider */
    public function testInvalidAdminIds(string $modifier): void
    {
        $this->assertFalse(AdminValidator::isValid($modifier));
    }

    /** @return array<string, array{string}> */
    public static function invalidAdminProvider(): array
    {
        return [
            'no suffix at all'         => ['jsmith'],
            'wrong suffix'             => ['jsmith-admin'],
            'adm without dash'         => ['jsmithadm'],
            'empty string'             => [''],
            'suffix in middle'         => ['jsmith-adm-extra'],
        ];
    }
}
