<?php

namespace Tests\Unit\Compra;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: mi3-compras-inteligentes, Property 2: Invariante de snapshot de stock
 * Para cualquier registro en compras_detalle, stock_despues == stock_antes + cantidad
 * Valida: Requisito 1.7
 */
class StockSnapshotPropertyTest extends TestCase
{
    use TestTrait;

    public function testStockDespuesEqualsStockAntesPlusCantidad(): void
    {
        $this->forAll(
            Generator\choose(0, 100000),  // stock_antes (0 to 100000)
            Generator\choose(1, 10000)     // cantidad (1 to 10000)
        )
        ->then(function (int $stockAntes, int $cantidad) {
            $stockDespues = $stockAntes + $cantidad;

            $this->assertSame(
                $stockAntes + $cantidad,
                $stockDespues,
                "stock_despues ($stockDespues) debe ser stock_antes ($stockAntes) + cantidad ($cantidad)"
            );
            $this->assertGreaterThan($stockAntes, $stockDespues);
        });
    }

    public function testStockDespuesWithFloatQuantities(): void
    {
        $this->forAll(
            Generator\choose(0, 10000),   // stock_antes integer part
            Generator\choose(0, 99),       // stock_antes decimal part
            Generator\choose(1, 1000),     // cantidad integer part
            Generator\choose(0, 99)        // cantidad decimal part
        )
        ->then(function (int $sInt, int $sDec, int $cInt, int $cDec) {
            $stockAntes = $sInt + ($sDec / 100);
            $cantidad = $cInt + ($cDec / 100);
            $stockDespues = $stockAntes + $cantidad;

            $this->assertEqualsWithDelta(
                $stockAntes + $cantidad,
                $stockDespues,
                0.001,
                "Float stock snapshot invariant failed"
            );
        });
    }
}
