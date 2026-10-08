<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->after('password');
            $table->string('status')->default('pending')->after('role');
            $table->string('student_id')->nullable()->after('status');
            $table->string('phone')->nullable();
            $table->string('programme')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('avatar')->nullable();
            $table->string('telegram_chat_id')->nullable()->unique();
            $table->string('telegram_link_code')->nullable()->unique();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropUnique(['telegram_link_code']);
            $table->dropColumn([
                'role', 'status', 'student_id', 'phone', 'programme', 'semester',
                'avatar', 'telegram_chat_id', 'telegram_link_code', 'approved_at',
            ]);
        });
    }
};
