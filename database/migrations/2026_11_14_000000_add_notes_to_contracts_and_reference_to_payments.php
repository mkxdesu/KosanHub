<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('reference');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
