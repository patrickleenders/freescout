<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateKnBoardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kn_boards', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 75);
            // Associated mailbox
            $table->unsignedInteger('mailbox_id');
            $table->text('columns'); // JSON
            $table->text('swimlanes'); // JSON
            $table->unsignedInteger('created_by_user_id');

            $table->index(['created_by_user_id', 'mailbox_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kn_boards');
    }
}
