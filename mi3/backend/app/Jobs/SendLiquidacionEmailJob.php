<?php

namespace App\Jobs;

use App\Models\Personal;
use App\Services\Email\GmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLiquidacionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $personalId,
        public string $mes,
        public array $liquidacionData,
    ) {}

    public function handle(GmailService $gmailService): void
    {
        $persona = Personal::findOrFail($this->personalId);

        $gmailService->sendLiquidacionEmail($persona, $this->mes, $this->liquidacionData);
    }
}
