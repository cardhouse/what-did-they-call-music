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
        Schema::create('album_song', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->integer('track_number');
            $table->integer('chart_position')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('album_id');
            $table->index(['album_id', 'track_number']);
            $table->unique(['album_id', 'song_id']); // Prevent duplicate songs on same album
            $table->unique(['album_id', 'track_number']); // Prevent duplicate track numbers
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_song');
    }
};
