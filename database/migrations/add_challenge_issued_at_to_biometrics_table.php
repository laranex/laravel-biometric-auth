<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/*
 * Upgrades a table created by v3 of the package, which had no challenge_issued_at column.
 * Tables created by the current create_biometrics_table migration already have it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable($this->table()) || Schema::hasColumn($this->table(), 'challenge_issued_at')) {
            return;
        }

        Schema::table($this->table(), function (Blueprint $table): void {
            $table->timestamp('challenge_issued_at')->nullable()->after('challenge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The column belongs to the create_biometrics_table migration, whose rollback drops the table.
    }

    private function table(): string
    {
        $table = Config::get('biometric-auth.table', 'biometrics');

        return is_string($table) && $table !== '' ? $table : 'biometrics';
    }
};
