<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rifa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('number')->unsigned();
            $table->enum('status', ['available', 'reserved', 'paid', 'drawn'])->default('available');
            $table->decimal('price', 10, 2);
            $table->timestamps();
            
            $table->unique(['rifa_id', 'number']);
            $table->index(['status', 'rifa_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tickets');
    }
};
