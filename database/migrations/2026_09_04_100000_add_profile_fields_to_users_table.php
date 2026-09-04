<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('password');
            $table->string('avatar_path')->nullable()->after('role');
            $table->string('theme', 10)->default('auto')->after('avatar_path');
            $table->string('locale', 10)->default('fr')->after('theme');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->json('preferences')->nullable()->after('is_active');
            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'avatar_path', 'theme', 'locale', 'is_active', 'preferences']);
        });
    }
};
