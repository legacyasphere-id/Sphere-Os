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
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('task_type', 50);
            $table->string('model_hint', 100)->nullable();
            $table->text('system_message');
            $table->text('user_template');
            $table->enum('output_format', ['json', 'markdown', 'text'])->default('json');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['task_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
