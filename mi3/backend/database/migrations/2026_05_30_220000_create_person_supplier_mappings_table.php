<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_supplier_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('person_name', 255)->comment('Nombre como aparece en comprobante');
            $table->string('supplier_name', 255)->comment('Proveedor normalizado');
            $table->string('item_name', 255)->nullable()->comment('Item a registrar');
            $table->string('tipo_compra', 50)->nullable()->comment('tipo de compra');
            $table->string('source', 50)->default('manual')->comment('manual o learned');
            $table->unsignedInteger('times_used')->default(1);
            $table->timestamps();
            $table->index('person_name');
            $table->index('supplier_name');
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('person_supplier_mappings');
    }

    private function seedDefaults(): void
    {
        DB::table('person_supplier_mappings')->insert([
            // ARIAKA delivery riders
            ['person_name' => 'karen miranda olmedo',          'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'karen miranda',                 'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'elcia vilca',                   'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'eliana vilca',                  'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'cecilia rojas hinojosa',        'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'cecilia rojas',                 'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'maria mondañez mamani',         'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'maria mondanez mamani',         'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'giovanna loza salas',           'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'giovanna loza',                 'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'ariel araya',                   'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'ariel aliro araya villalobos',  'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'karina roco',                   'supplier_name' => 'ARIAKA', 'item_name' => 'Servicios Delivery', 'tipo_compra' => 'otros', 'source' => 'manual', 'times_used' => 10],

            // Abastible
            ['person_name' => 'elton san martin',              'supplier_name' => 'Abastible', 'item_name' => 'gas 15', 'tipo_compra' => 'ingredientes', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'elton san martín',              'supplier_name' => 'Abastible', 'item_name' => 'gas 15', 'tipo_compra' => 'ingredientes', 'source' => 'manual', 'times_used' => 10],

            // Ariztía
            ['person_name' => 'karina andrea muñoz ahumada',   'supplier_name' => 'Ariztía (proveedor)', 'item_name' => null, 'tipo_compra' => 'ingredientes', 'source' => 'manual', 'times_used' => 10],
            ['person_name' => 'karina muñoz',                  'supplier_name' => 'Ariztía (proveedor)', 'item_name' => null, 'tipo_compra' => 'ingredientes', 'source' => 'manual', 'times_used' => 10],

            // Agro Lucila
            ['person_name' => 'lucila cacera',                 'supplier_name' => 'agro-lucila', 'item_name' => null, 'tipo_compra' => 'ingredientes', 'source' => 'manual', 'times_used' => 10],
        ]);
    }
};