<?php

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
        Schema::table('projects', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('description');
            $table->timestamp('ai_summary_at')->nullable()->after('ai_summary');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('ai_generated')->default(false)->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'ai_summary_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('ai_generated');
        });
    }
};
