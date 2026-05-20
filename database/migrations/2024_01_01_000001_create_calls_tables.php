<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 16)->default('available');
            $table->unsignedInteger('active_calls')->default(0);
            $table->unsignedInteger('max_concurrent_calls')->default(1);
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'active_calls', 'last_assigned_at']);
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('incoming');
            $table->string('external_id')->nullable()->unique();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('call_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->string('level', 16)->default('info');
            $table->json('context')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['call_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_processing_logs');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('operators');
        Schema::dropIfExists('clients');
    }
};
