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
        Schema::create('log_buffalo_bets', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Batch-level data
            $table->string('member_account');
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('player_agent_id')->nullable();
            $table->string('buffalo_game_id');
            $table->timestamp('request_time')->nullable();
            // Transaction-level 
            $table->decimal('bet_amount', 20, 4)->nullable();
            $table->decimal('win_amount', 20, 4)->nullable();
            $table->json('payload')->nullable();
            $table->string('game_name')->nullable();
            $table->string('status')->default('pending'); 
            $table->decimal('before_balance', 20, 4)->nullable();
            $table->decimal('balance', 20, 4)->nullable();

            $table->timestamps();

            // Indexes for better query performance
            $table->index('player_id');
            $table->index('player_agent_id');
            $table->index('member_account');
            $table->index('buffalo_game_id');
            $table->index('status');
            $table->index('request_time');
            $table->index('created_at');
            // Composite index for common queries
            $table->index(['player_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_buffalo_bets');
    }
};
