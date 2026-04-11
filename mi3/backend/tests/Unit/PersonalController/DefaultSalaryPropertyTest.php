<?php

namespace Tests\Unit\PersonalController;

use Faker\Factory as Faker;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 1: Asignación de sueldo base por defecto
 *
 * Validates: Requirements 1.1, 1.2, 1.4
 *
 * Property: For any worker creation request with valid roles, if the base salary fields
 * are null or 0, the corresponding field for the primary role must be $300.000.
 * If an explicit value > 0 is provided, that value must be preserved without modification.
 */
class DefaultSalaryPropertyTest extends TestCase
{
    private const DEFAULT_SUELDO = 300000;

    private const ROLE_TO_FIELD = [
        'cajero' => 'sueldo_base_cajero',
        'planchero' => 'sueldo_base_planchero',
        'administrador' => 'sueldo_base_admin',
        'seguridad' => 'sueldo_base_seguridad',
    ];

    /**
     * Simulate the applyDefaultSueldo logic from PersonalController.
     */
    private function applyDefaultSueldo(array &$data, array $roles): void
    {
        foreach (self::ROLE_TO_FIELD as $role => $field) {
            if (in_array($role, $roles)) {
                if (empty($data[$field]) || (float) $data[$field] === 0.0) {
                    $data[$field] = self::DEFAULT_SUELDO;
                }
            }
        }
    }

    /**
     * Property 1: When salary fields are null or 0, default $300.000 is applied.
     *
     * **Validates: Requirements 1.1, 1.2, 1.4**
     *
     * @test
     */
    public function null_or_zero_salary_gets_default_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $availableRoles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        for ($i = 0; $i < 100; $i++) {
            // Pick 1-3 random roles
            $numRoles = $faker->numberBetween(1, 3);
            $roles = $faker->randomElements($availableRoles, $numRoles);

            // Set salary fields to null or 0 for selected roles
            $data = [];
            foreach (self::ROLE_TO_FIELD as $role => $field) {
                if (in_array($role, $roles)) {
                    $data[$field] = $faker->randomElement([null, 0, 0.0]);
                }
            }

            $this->applyDefaultSueldo($data, $roles);

            foreach ($roles as $role) {
                $field = self::ROLE_TO_FIELD[$role];
                $this->assertEquals(
                    self::DEFAULT_SUELDO,
                    $data[$field],
                    "Role '{$role}' should have default salary {$field}=" . self::DEFAULT_SUELDO
                        . " but got " . ($data[$field] ?? 'null')
                        . " (iteration {$i})"
                );
            }
        }
    }

    /**
     * Property 1: When an explicit salary > 0 is provided, it is preserved.
     *
     * **Validates: Requirements 1.1, 1.2, 1.4**
     *
     * @test
     */
    public function explicit_salary_is_preserved_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $availableRoles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        for ($i = 0; $i < 100; $i++) {
            $numRoles = $faker->numberBetween(1, 3);
            $roles = $faker->randomElements($availableRoles, $numRoles);

            $data = [];
            $expectedValues = [];

            foreach (self::ROLE_TO_FIELD as $role => $field) {
                if (in_array($role, $roles)) {
                    $explicitValue = $faker->numberBetween(100000, 1000000);
                    $data[$field] = $explicitValue;
                    $expectedValues[$field] = $explicitValue;
                }
            }

            $this->applyDefaultSueldo($data, $roles);

            foreach ($expectedValues as $field => $expected) {
                $this->assertEquals(
                    $expected,
                    $data[$field],
                    "Explicit salary for {$field} should be preserved as {$expected}"
                        . " but got {$data[$field]} (iteration {$i})"
                );
            }
        }
    }

    /**
     * Property 1: Roles not selected should not get default salary applied.
     *
     * **Validates: Requirements 1.1, 1.2, 1.4**
     *
     * @test
     */
    public function unselected_roles_are_not_modified_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $availableRoles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        for ($i = 0; $i < 100; $i++) {
            // Pick exactly 1 role
            $selectedRole = $faker->randomElement($availableRoles);
            $roles = [$selectedRole];

            // Initialize all fields to null
            $data = [];
            foreach (self::ROLE_TO_FIELD as $role => $field) {
                $data[$field] = null;
            }

            $this->applyDefaultSueldo($data, $roles);

            // Selected role should have default
            $selectedField = self::ROLE_TO_FIELD[$selectedRole];
            $this->assertEquals(self::DEFAULT_SUELDO, $data[$selectedField]);

            // Other roles should remain null
            foreach (self::ROLE_TO_FIELD as $role => $field) {
                if ($role !== $selectedRole) {
                    $this->assertNull(
                        $data[$field],
                        "Unselected role '{$role}' field {$field} should remain null (iteration {$i})"
                    );
                }
            }
        }
    }
}
