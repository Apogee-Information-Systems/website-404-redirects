<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_404_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('path', 512)->unique();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('redirect_to', 2048)->nullable();
            $table->unsignedSmallInteger('redirect_status')->default(301);
            $table->boolean('is_ignored')->default(false);
            $table->text('notes')->nullable();
            $table->string('last_referer', 2048)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('hit_count');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_404_redirects');
    }
};
