<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronExecution extends Model
{
    protected $fillable = [
        'command', 'name', 'status', 'output',
        'duration_seconds', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'float',
    ];

    public static function log(string $command, string $name, string $status, ?string $output = null, ?float $duration = null, ?\DateTimeInterface $startedAt = null): self
    {
        return self::create([
            'command' => $command,
            'name' => $name,
            'status' => $status,
            'output' => $output ? mb_substr($output, 0, 500) : null,
            'duration_seconds' => $duration,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }
}
