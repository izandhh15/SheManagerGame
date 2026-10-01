<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracking de visitas para el panel "En directo":
     * - visitor_heartbeats: latido por visitante (quién está online ahora)
     * - traffic_daily / traffic_hourly: contadores de visitas y únicos
     * - traffic_visitor_days: claves de visitante por día (para únicos diarios)
     *
     * No se guarda la IP en crudo, solo su hash (privacidad).
     */
    public function up(): void
    {
        Schema::create('visitor_heartbeats', function (Blueprint $table) {
            $table->string('visitor_key', 64)->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('path', 255)->default('/');
            $table->string('device', 16)->default('desktop');
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent();
            $table->index('last_seen');
        });

        Schema::create('traffic_daily', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('uniques')->default(0);
        });

        Schema::create('traffic_hourly', function (Blueprint $table) {
            $table->timestamp('hour')->primary();
            $table->unsignedInteger('visits')->default(0);
        });

        Schema::create('traffic_visitor_days', function (Blueprint $table) {
            $table->date('date');
            $table->string('visitor_key', 64);
            $table->primary(['date', 'visitor_key']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_visitor_days');
        Schema::dropIfExists('traffic_hourly');
        Schema::dropIfExists('traffic_daily');
        Schema::dropIfExists('visitor_heartbeats');
    }
};
