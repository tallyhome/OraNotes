<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('');
            $table->json('document');
            $table->longText('html_preview')->nullable();
            $table->longText('text_content')->nullable();
            $table->string('color', 16)->default('yellow');
            $table->string('icon', 32)->nullable();
            $table->float('x')->default(80);
            $table->float('y')->default(80);
            $table->float('width')->default(260);
            $table->float('height')->default(220);
            $table->float('rotation')->default(0);
            $table->unsignedInteger('z_index')->default(1);
            $table->string('status', 24)->default('idea');
            $table->string('priority', 16)->default('normal');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'deleted_at']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['workspace_id', 'is_archived']);
            $table->index('status');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
