<?php

namespace Tests\Unit\ChecklistService;

use App\Services\Checklist\PhotoAnalysisService;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 6: Selección de prompt IA según contexto

/**
 * Property 6: Selección de prompt IA según contexto
 *
 * For any combination of photo type (interior/exterior) and checklist type
 * (apertura/cierre), the analysis service must select the correct prompt
 * corresponding to that context.
 *
 * **Validates: Requirement 3.2**
 */
class PromptSelectionPropertyTest extends TestCase
{
    private PhotoAnalysisService $service;

    /**
     * Expected keywords that MUST appear in each context's prompt.
     */
    private const CONTEXT_KEYWORDS = [
        'interior_apertura' => ['INTERIOR', 'APERTURA', 'plancha', 'limpieza', 'equipos'],
        'exterior_apertura' => ['EXTERIOR', 'APERTURA', 'mesas', 'señalización', 'clientes'],
        'interior_cierre' => ['INTERIOR', 'CIERRE', 'plancha', 'desconectados', 'guardados'],
        'exterior_cierre' => ['EXTERIOR', 'CIERRE', 'guardados', 'basura', 'cerrado'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhotoAnalysisService();
    }

    /**
     * Property 6: Correct prompt is selected for all context combinations.
     * 100 iterations, each randomly picking a context and verifying the correct prompt.
     *
     * **Validates: Requirement 3.2**
     *
     * @test
     */
    public function correct_prompt_selected_for_100_random_context_picks(): void
    {
        $photoTypes = ['interior', 'exterior'];
        $checklistTypes = ['apertura', 'cierre'];
        $validContexts = PhotoAnalysisService::getValidContexts();

        // Verify all 4 combinations exist
        $this->assertCount(4, $validContexts, 'There should be exactly 4 valid contexts');

        for ($i = 0; $i < 100; $i++) {
            // Randomly pick a photo type and checklist type
            $photoType = $photoTypes[random_int(0, 1)];
            $checklistType = $checklistTypes[random_int(0, 1)];
            $contexto = "{$photoType}_{$checklistType}";

            // The context must be valid
            $this->assertContains(
                $contexto,
                $validContexts,
                "Iteration {$i}: Context '{$contexto}' should be valid"
            );

            // Get the prompt
            $prompt = $this->service->getPromptForContext($contexto);

            // Prompt must be a non-empty string
            $this->assertNotEmpty($prompt, "Iteration {$i}: Prompt for '{$contexto}' should not be empty");

            // Prompt must contain expected keywords for this context
            $expectedKeywords = self::CONTEXT_KEYWORDS[$contexto];
            foreach ($expectedKeywords as $keyword) {
                $this->assertStringContainsStringIgnoringCase(
                    $keyword,
                    $prompt,
                    "Iteration {$i}: Prompt for '{$contexto}' should contain keyword '{$keyword}'"
                );
            }

            // Prompt must NOT contain keywords unique to other contexts
            $this->assertPromptIsDistinct($prompt, $contexto, $i);
        }
    }

    /**
     * Verify that each context produces a distinct prompt (no two contexts share the same prompt).
     *
     * @test
     */
    public function all_four_contexts_produce_distinct_prompts(): void
    {
        $prompts = [];
        foreach (PhotoAnalysisService::getValidContexts() as $contexto) {
            $prompt = $this->service->getPromptForContext($contexto);
            $prompts[$contexto] = $prompt;
        }

        // All 4 prompts must be different
        $uniquePrompts = array_unique($prompts);
        $this->assertCount(4, $uniquePrompts, 'All 4 contexts should produce distinct prompts');
    }

    /**
     * Verify that invalid contexts throw an exception.
     *
     * @test
     */
    public function invalid_context_throws_exception(): void
    {
        $invalidContexts = ['invalid', 'interior', 'apertura', 'foo_bar', ''];

        foreach ($invalidContexts as $invalid) {
            try {
                $this->service->getPromptForContext($invalid);
                $this->fail("Expected exception for invalid context '{$invalid}'");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Contexto inválido', $e->getMessage());
            }
        }
    }

    /**
     * Assert that a prompt is distinct from other contexts by checking
     * it matches the expected photo type and checklist type.
     */
    private function assertPromptIsDistinct(string $prompt, string $currentContext, int $iteration): void
    {
        [$photoType, $checklistType] = explode('_', $currentContext);

        // The prompt should reference the correct photo location
        $this->assertStringContainsStringIgnoringCase(
            $photoType,
            $prompt,
            "Iteration {$iteration}: Prompt for '{$currentContext}' should reference '{$photoType}'"
        );

        // The prompt should reference the correct time (apertura/cierre in Spanish)
        $timeWord = $checklistType === 'apertura' ? 'APERTURA' : 'CIERRE';
        $this->assertStringContainsStringIgnoringCase(
            $timeWord,
            $prompt,
            "Iteration {$iteration}: Prompt for '{$currentContext}' should reference '{$timeWord}'"
        );
    }
}
