<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->enum('type', ['note', 'template', 'sop', 'reference'])->default('note');
            $table->json('tags')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_pinned']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX ft_title_content ON knowledge_documents USING gin(to_tsvector('english', coalesce(title,'') || ' ' || coalesce(content,'')))");
        } else {
            DB::statement('ALTER TABLE knowledge_documents ADD FULLTEXT ft_title_content (title, content)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
