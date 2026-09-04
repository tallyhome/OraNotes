<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');
            $table->string('permission', 16)->default('read');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['token', 'is_revoked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
