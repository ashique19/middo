<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nid_number', 17)->nullable()->after('address');
            $table->string('nid_front_path')->nullable()->after('nid_number');
            $table->string('nid_back_path')->nullable()->after('nid_front_path');
            // Chef selfie. This is also the kitchen profile photo.
            $table->string('profile_photo_path')->nullable()->after('nid_back_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nid_number',
                'nid_front_path',
                'nid_back_path',
                'profile_photo_path',
            ]);
        });
    }
};
