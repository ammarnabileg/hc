<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('post_votes', function (Blueprint $table) {
            $table->id();
            $table->morphs('votable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value'); // +1 / -1
            $table->timestamps();
            $table->unique(['votable_type', 'votable_id', 'user_id'], 'post_votes_unique');
        });
    }
};
