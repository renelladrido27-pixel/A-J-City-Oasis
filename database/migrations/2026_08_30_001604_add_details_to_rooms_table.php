<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('size_sqm')->nullable()->after('type');
            $table->text('description')->nullable()->after('size_sqm');
            $table->json('inclusions')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['size_sqm', 'description', 'inclusions']);
        });
    }
};
