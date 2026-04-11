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
        ['base' => '2026-02-01', 'a_name' => 'Camila', 'b_name' => 'Dafne', 'tipo' => 'normal'],
        ['base' => '2026-02-03', 'a_name' => 'Andrés', 'b_name' => 'Andrés', 'tipo' => 'normal'],
        ['base' => '2026-02-11', 'a_name' => 'Ricardo', 'b_name' => 'Claudio', 'tipo' => 'seguridad'],
    ];

    public function handle(): int
    {
        $mesParam = $this->option('mes') ?? now()->format('Y-m');
        $start = Carbon::parse($mesParam . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $this->info("Generando turnos para {$start->format('Y-m')} ({$start->format('d')} al {$end->format('d')})...");

        $created = 0;
        $skipped = 0;

        foreach (self::CYCLES as $cycle) {
            $idA = Personal::where('nombre', 'like', $cycle['a_name'] . '%')->where('activo', 1)->value('id');
            $idB = Personal::where('nombre', 'like', $cycle['b_name'] . '%')->where('activo', 1)->value('id');

            if (!$idA || !$idB) {
                $this->warn("  Ciclo {$cycle['a_name']}/{$cycle['b_name']}: personal no encontrado, omitiendo.");
                continue;
            }

            $base = Carbon::parse($cycle['base']);
            $current = $start->copy();

            while ($current->lte($end)) {
                $days = $base->diffInDays($current, false);
                $pos = (($days % 8) + 8) % 8;
                $personalId = ($pos < 4) ? $idA : $idB;
                $fecha = $current->format('Y-m-d');

                // Skip if shift already exists (manual or previously generated)
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

            $this->line("  {$cycle['a_name']}/{$cycle['b_name']} ({$cycle['tipo']}): procesado");
        }

        $this->info("Turnos creados: {$created}, omitidos (ya existían): {$skipped}");
        return self::SUCCESS;
    }
}
