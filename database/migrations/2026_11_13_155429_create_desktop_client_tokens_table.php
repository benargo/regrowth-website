<?php

use App\Models\DesktopClient;
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
        Schema::create('desktop_client_tokens', function (Blueprint $table) {
            $table->string('access_token_id', 100)->primary();
            $table->foreign('access_token_id')->references('id')->on('oauth_access_tokens')->cascadeOnDelete();
            $table->foreignIdFor(DesktopClient::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('desktop_client_tokens');
    }
};
