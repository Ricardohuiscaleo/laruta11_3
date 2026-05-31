<?php

namespace App\Console\Commands;

use App\Models\ExtractionFeedback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LearnSupplierMappingsCommand extends Command
{
    protected $signature = 'compras:learn-supplier-mappings
                            {--threshold=2 : Mínimo de correcciones iguales para aprender}
                            {--dry-run : Solo mostrar qué se insertaría}';
    protected $description = 'Auto-learn person→supplier mappings from extraction_feedback corrections';

    private const EXCLUDED_PERSONS = [
        'ricardo huiscaleo', 'ricardo aníbal huiscaleo', 'ricardo anibal huiscaleo',
        'ricardo aníbal huiscaleo llafquén', 'ricardo anibal huiscaleo llafquen',
        'mercado pago',
    ];

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $dryRun = (bool) $this->option('dry-run');

        $corrections = ExtractionFeedback::where('field_name', 'proveedor')
            ->whereNotNull('original_value')
            ->whereNotNull('corrected_value')
            ->where('original_value', '!=', '')
            ->where('corrected_value', '!=', '')
            ->whereColumn('original_value', '!=', 'corrected_value')
            ->get();

        if ($corrections->isEmpty()) {
            $this->warn('No hay correcciones de proveedor en extraction_feedback.');
            return 0;
        }

        // Group by (original_value, corrected_value) pairs
        $patterns = [];
        foreach ($corrections as $c) {
            $orig = mb_strtolower(trim($c->original_value));
            $corr = mb_strtolower(trim($c->corrected_value));
            $key = $orig . '|||' . $corr;
            if (!isset($patterns[$key])) {
                $patterns[$key] = [
                    'original' => $orig,
                    'corrected' => $corr,
                    'count' => 0,
                    'proveedor' => $c->proveedor,
                    'tipo_imagen' => $c->tipo_imagen,
                ];
            }
            $patterns[$key]['count']++;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($patterns as $pattern) {
            if ($pattern['count'] < $threshold) {
                continue;
            }

            $orig = $pattern['original'];

            // Skip excluded persons
            $isExcluded = false;
            foreach (self::EXCLUDED_PERSONS as $ex) {
                if (str_contains($orig, $ex)) {
                    $isExcluded = true;
                    break;
                }
            }
            if ($isExcluded) {
                $this->line("  ↪ Saltado (excluido): {$orig}");
                $skipped++;
                continue;
            }

            // Check if mapping already exists — update times_used if so
            $existingMapping = DB::table('person_supplier_mappings')
                ->where('person_name', $orig)
                ->first();

            if ($existingMapping) {
                DB::table('person_supplier_mappings')
                    ->where('id', $existingMapping->id)
                    ->update([
                        'times_used' => $existingMapping->times_used + $pattern['count'],
                        'updated_at' => now(),
                    ]);
                $this->line("  ↪ Actualizado times_used: {$orig} → {$pattern['corrected']} (+{$pattern['count']})");
                $skipped++;
                continue;
            }

            // Determine item_name and tipo_compra based on corrected supplier
            $itemName = null;
            $tipoCompra = 'otros';
            $supplierName = $pattern['corrected'];

            if (str_contains($supplierName, 'aria') || str_contains($supplierName, 'riaka')) {
                $supplierName = 'ARIAKA';
                $itemName = 'Servicios Delivery';
                $tipoCompra = 'otros';
            } elseif (str_contains($supplierName, 'abastible')) {
                $supplierName = 'Abastible';
                $itemName = 'gas 15';
                $tipoCompra = 'ingredientes';
            } elseif (str_contains($supplierName, 'arizt') || str_contains($supplierName, 'agrosuper')) {
                $supplierName = 'Ariztía (proveedor)';
                $tipoCompra = 'ingredientes';
            }

            if ($dryRun) {
                $this->line("  → Insertaría: {$orig} → {$supplierName} | item={$itemName} | tipo={$tipoCompra} | ({$pattern['count']} correcciones)");
                $inserted++;
                continue;
            }

            DB::table('person_supplier_mappings')->insert([
                'person_name' => $orig,
                'supplier_name' => $supplierName,
                'item_name' => $itemName,
                'tipo_compra' => $tipoCompra,
                'source' => 'learned',
                'times_used' => $pattern['count'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->line("  ✓ Insertado: {$orig} → {$supplierName}");
            Log::info('[LearnSupplierMappings] Auto-learned mapping', [
                'person_name' => $orig,
                'supplier_name' => $supplierName,
                'times_used' => $pattern['count'],
            ]);
            $inserted++;
        }

        $this->newLine();
        $this->info("Resumen: {$inserted} insertados, {$skipped} saltados, " . count($patterns) . " patrones encontrados.");

        return 0;
    }
}
