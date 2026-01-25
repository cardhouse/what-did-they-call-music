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
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('original_release_year')->nullable();
            $table->string('spotify_id')->nullable();
            $table->string('apple_music_id')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('title');
            $table->unique('spotify_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
