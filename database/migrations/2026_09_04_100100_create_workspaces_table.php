<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon', 32)->default('🗂️');
            $table->string('color', 16)->default('yellow');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_template')->default(false);
            $table->json('canvas_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_archived']);
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
