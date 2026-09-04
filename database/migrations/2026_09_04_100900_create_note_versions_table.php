<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('document');
            $table->string('title')->default('');
            $table->timestamps();

            $table->index(['note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_versions');
    }
};
