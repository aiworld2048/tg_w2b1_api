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
        Schema::table('custom_transactions', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_reason')->nullable();
            
            // Add foreign key constraint
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            
            // Add index for better performance
            $table->index('deleted_at');
            $table->index('deleted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_transactions', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deleted_reason']);
        });
    }
};