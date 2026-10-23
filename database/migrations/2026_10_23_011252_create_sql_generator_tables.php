<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firebird_connections', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('host')->default('127.0.0.1');
            $table->unsignedSmallInteger('port')->default(3050);
            $table->string('database_path', 1024);
            $table->string('username');
            $table->text('password');
            $table->string('charset')->default('UTF8');
            $table->string('status')->default('disconnected');
            $table->string('server_version')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schema_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firebird_connection_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->longText('catalog');
            $table->timestamps();
            $table->index(['firebird_connection_id', 'fingerprint']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firebird_connection_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('sql_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->string('schema_fingerprint', 64);
            $table->longText('sql')->nullable();
            $table->text('explanation')->nullable();
            $table->json('parameters')->nullable();
            $table->json('warnings')->nullable();
            $table->string('validation_status')->default('failed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sql_generations');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('schema_snapshots');
        Schema::dropIfExists('firebird_connections');
    }
};
