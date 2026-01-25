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
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->nullable(); // NULL for special editions
            $table->string('name'); // "NOW That's What I Call Music! 45"
            $table->date('release_date');
            $table->string('cover_art_url')->nullable();
            $table->enum('type', ['regular', 'christmas', 'summer', 'special'])->default('regular');
            $table->string('spotify_url')->nullable();
            $table->string('apple_music_url')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('release_date');
            $table->index(['type', 'release_date']);
            $table->unique(['number', 'type']); // Prevent duplicate numbered albums of same type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
