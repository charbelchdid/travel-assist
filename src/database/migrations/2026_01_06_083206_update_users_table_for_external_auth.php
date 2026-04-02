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
        Schema::table('users', function (Blueprint $table) {
            // Add external auth fields
            $table->unsignedBigInteger('external_id')->unique()->after('id');
            $table->string('username')->unique()->after('external_id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_admin')->default(false)->after('phone');
            $table->string('role')->default('user')->after('is_admin');
            $table->string('department')->nullable()->after('role');
            $table->unsignedInteger('branch_id')->nullable()->after('department');
            $table->string('branch_name')->nullable()->after('branch_id');
            $table->json('external_data')->nullable()->after('branch_name');
            $table->timestamp('last_login_at')->nullable()->after('external_data');
            $table->string('device_id')->nullable()->after('last_login_at');

            // Make password nullable since we don't store passwords locally
            $table->string('password')->nullable()->change();

            // Add indexes for performance
            $table->index('external_id');
            $table->index('username');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['username']);
            $table->dropIndex(['last_login_at']);

            $table->dropColumn([
                'external_id',
                'username',
                'first_name',
                'last_name',
                'phone',
                'is_admin',
                'role',
                'department',
                'branch_id',
                'branch_name',
                'external_data',
                'last_login_at',
                'device_id'
            ]);

            // Revert password to non-nullable
            $table->string('password')->nullable(false)->change();
        });
    }
};
