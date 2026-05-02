<?php

namespace App\Console\Commands;

use App\Models\Personal;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDynamicShiftsCommand extends Command
{
    protected $signature = 'mi3:generate-shifts {--mes= : Month in YYYY-MM format (default: current)}';
    protected $description = 'Generar turnos dinámicos 4x4 para La Ruta 11 (cajeros, plancheros, seguridad)';

    /**
     * Shift cycles configuration.
     * Each cycle: base date, person_a (pos 0-3), person_b (pos 4-7).
     * If person_a == person_b, that person works every day.
     */
    private const CYCLES = [
        ['base' => '2026-02-01', 'a_id' => 1, 'b_id' => 18, 'tipo' => 'normal'],      // Cajero: Camila / Dafne Fum
        ['base' => '2026-02-03', 'a_id' => 3, 'b_id' => 3, 'tipo' => 'normal'],        // Planchero: Andres / Andres
        ['base' => '2026-02-11', 'a_id' => 5, 'b_id' => 10, 'tipo' => 'seguridad'],    // Seguridad: Ricardo / Claudio
    ];

    public function handle(): int
    {
        // When run by scheduler (day 25), generate for NEXT month. When run manually with --mes, use that.
        $mesParam = $this->option('mes') ?? now()->addMonth()->format('Y-m');
        $start = Carbon::parse($mesParam . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $this->info("Generando turnos para {$start->format('Y-m')} ({$start->format('d')} al {$end->format('d')})...");

        $created = 0;
        $skipped = 0;

        foreach (self::CYCLES as $cycle) {
            $idA = $cycle['a_id'];
            $idB = $cycle['b_id'];

            // Verify both exist and are active
            $nameA = Personal::where('id', $idA)->where('activo', 1)->value('nombre');
            $nameB = Personal::where('id', $idB)->where('activo', 1)->value('nombre');

            if (!$nameA || !$nameB) {
                $this->warn("  Ciclo id={$idA}/id={$idB}: personal no encontrado o inactivo, omitiendo.");
                continue;
            }

            $base = Carbon::parse($cycle['base']);
            $current = $start->copy();

            while ($current->lte($end)) {
                $days = $base->diffInDays($current, false);
                $pos = (($days % 8) + 8) % 8;
                $personalId = ($pos < 4) ? $idA : $idB;
                $fecha = $current->format('Y-m-d');

                $exists = Turno::where('personal_id', $personalId)
                    ->where('fecha', $fecha)
                    ->exists();

                if (!$exists) {
                    Turno::create([
                        'personal_id' => $personalId,
                        'fecha' => $fecha,
                        'tipo' => $cycle['tipo'],
                    ]);
                    $created++;
                } else {
                    $skipped++;
                }

                $current->addDay();
            }

            $this->line("  {$nameA}/{$nameB} ({$cycle['tipo']}): procesado");
        }

        $this->info("Turnos creados: {$created}, omitidos (ya existían): {$skipped}");
        return self::SUCCESS;
    }
}
