<?php

namespace Tests\Unit\Recipe;

use App\Services\Recipe\RecipeService;
use Tests\TestCase;

/**
 * Unit tests for RecipeService bulk adjustment helper logic.
 * Tests the pure calculation methods via reflection (no DB needed).
 */
class RecipeServiceBulkAdjustmentTest extends TestCase
{
    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeService();
    }

    // --- calculateAdjustedCost (private helper) ---

    public function testPercentageIncrease(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // +10% on $1000 → $1100
        $result = $method->invoke($this->service, 1000.0, 'percentage', 10.0);
        $this->assertEqualsWithDelta(1100.0, $result, 0.01);
    }

    public function testPercentageDecrease(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // -20% on $500 → $400
        $result = $method->invoke($this->service, 500.0, 'percentage', -20.0);
        $this->assertEqualsWithDelta(400.0, $result, 0.01);
    }

    public function testFixedIncrease(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // +500 on $1000 → $1500
        $result = $method->invoke($this->service, 1000.0, 'fixed', 500.0);
        $this->assertEqualsWithDelta(1500.0, $result, 0.01);
    }

    public function testFixedDecrease(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // -300 on $1000 → $700
        $result = $method->invoke($this->service, 1000.0, 'fixed', -300.0);
        $this->assertEqualsWithDelta(700.0, $result, 0.01);
    }

    public function testPercentageZeroValue(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // 0% on $1000 → $1000 (no change)
        $result = $method->invoke($this->service, 1000.0, 'percentage', 0.0);
        $this->assertEqualsWithDelta(1000.0, $result, 0.01);
    }

    public function testFixedZeroValue(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // +0 on $1000 → $1000 (no change)
        $result = $method->invoke($this->service, 1000.0, 'fixed', 0.0);
        $this->assertEqualsWithDelta(1000.0, $result, 0.01);
    }

    public function testPercentageOnZeroCost(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // +50% on $0 → $0
        $result = $method->invoke($this->service, 0.0, 'percentage', 50.0);
        $this->assertEqualsWithDelta(0.0, $result, 0.01);
    }

    public function testFixedWouldCauseNegative(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // -1500 on $1000 → -500 (negative — caller must reject)
        $result = $method->invoke($this->service, 1000.0, 'fixed', -1500.0);
        $this->assertLessThan(0, $result);
    }

    public function testPercentageWouldCauseNegative(): void
    {
        $method = new \ReflectionMethod(RecipeService::class, 'calculateAdjustedCost');
        $method->setAccessible(true);

        // -150% on $1000 → -500 (negative — caller must reject)
        $result = $method->invoke($this->service, 1000.0, 'percentage', -150.0);
        $this->assertLessThan(0, $result);
    }
}
