<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('authenticable_id');
            $table->string('authenticable_type');
            $table->longText('public_key');
            $table->string('challenge')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            $table->index(['authenticable_type', 'authenticable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        $table = config('biometric-auth.table', 'biometrics');

        return is_string($table) && $table !== '' ? $table : 'biometrics';
    }
};
