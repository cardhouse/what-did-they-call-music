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
        Schema::create('artist_song', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(true); // Main artist vs featured
            $table->timestamps();

            // Indexes for performance
            $table->index(['song_id', 'is_primary']);
            $table->unique(['artist_id', 'song_id']); // Prevent duplicate artist-song pairs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_song');
    }
};
