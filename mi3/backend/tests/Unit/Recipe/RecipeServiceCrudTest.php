<?php

namespace Tests\Unit\Recipe;

use App\Services\Recipe\RecipeService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unit tests for RecipeService create/update/delete validation logic.
 * Uses Laravel TestCase to bootstrap the app (needed for ValidationException::withMessages).
 */
class RecipeServiceCrudTest extends TestCase
{
    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeService();
    }

    public function testValidateIngredientsRejectsDuplicateIds(): void
    {
        $this->expectException(ValidationException::class);

        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            ['ingredient_id' => 1, 'quantity' => 100, 'unit' => 'g'],
            ['ingredient_id' => 1, 'quantity' => 200, 'unit' => 'g'],
        ]);
    }

    public function testValidateIngredientsRejectsZeroQuantity(): void
    {
        $this->expectException(ValidationException::class);

        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            ['ingredient_id' => 1, 'quantity' => 0, 'unit' => 'g'],
        ]);
    }

    public function testValidateIngredientsRejectsNegativeQuantity(): void
    {
        $this->expectException(ValidationException::class);

        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            ['ingredient_id' => 1, 'quantity' => -5, 'unit' => 'g'],
        ]);
    }

    public function testValidateIngredientsRejectsMissingQuantity(): void
    {
        $this->expectException(ValidationException::class);

        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            ['ingredient_id' => 1, 'unit' => 'g'],
        ]);
    }

    public function testValidateIngredientsAcceptsValidInput(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        // Should not throw any exception
        $method->invoke($this->service, [
            ['ingredient_id' => 1, 'quantity' => 100, 'unit' => 'g'],
            ['ingredient_id' => 2, 'quantity' => 0.5, 'unit' => 'kg'],
            ['ingredient_id' => 3, 'quantity' => 3, 'unit' => 'unidad'],
        ]);

        $this->assertTrue(true);
    }

    public function testDuplicateValidationErrorMessage(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        try {
            $method->invoke($this->service, [
                ['ingredient_id' => 5, 'quantity' => 10, 'unit' => 'g'],
                ['ingredient_id' => 5, 'quantity' => 20, 'unit' => 'ml'],
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('ingredients', $errors);
            $this->assertStringContainsString('duplicado', $errors['ingredients'][0]);
        }
    }

    public function testQuantityValidationErrorMessage(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'validateIngredients');
        $method->setAccessible(true);

        try {
            $method->invoke($this->service, [
                ['ingredient_id' => 1, 'quantity' => -1, 'unit' => 'g'],
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('quantity', $errors);
            $this->assertStringContainsString('mayor a 0', $errors['quantity'][0]);
        }
    }
}
