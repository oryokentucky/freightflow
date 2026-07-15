<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();          // e.g. WH-KUL-01
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('capacity_pallets')->default(0);
            $table->timestamps();

            // Composite index powers the bounding-box pre-filter.
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();     // e.g. FF-2026-000123
            $table->foreignId('origin_warehouse_id')->constrained('warehouses');
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->string('status', 32)->default('booked'); // booked|in_transit|arrived|delivered|cancelled
            $table->unsignedInteger('lock_version')->default(0); // optimistic locking
            $table->decimal('weight_kg', 10, 2);
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'destination_warehouse_id']);
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
        });

        // Transactional outbox: events are written in the SAME transaction
        // as the domain change, then relayed to the queue by a worker.
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();          // idempotency key
            $table->string('type');                        // e.g. shipment.arrived
            $table->json('payload');
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['dispatched_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('warehouses');
    }
};
