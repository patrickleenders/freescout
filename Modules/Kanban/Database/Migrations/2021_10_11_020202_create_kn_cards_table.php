<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateKnCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kn_cards', function (Blueprint $table) {
            $table->increments('id');
            $table->text('name');
            $table->unsignedInteger('kn_board_id');
            $table->boolean('linked')->default(false);
            $table->unsignedInteger('conversation_id')->index();
            $table->unsignedInteger('kn_column_id');
            $table->unsignedInteger('kn_swimlane_id');
            $table->integer('sort_order')->default(1);

            // Indexes: board_id, column_id
            $table->index(['kn_board_id', 'kn_column_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kn_cards');
    }
}
