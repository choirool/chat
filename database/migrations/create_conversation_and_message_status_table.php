<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Musonza\Chat\ConfigurationManager;

class CreateConversationAndMessageStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(ConfigurationManager::CONVERSATIONS_TABLE . '_deleted', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('conversation_id')->unsigned();
            $table->bigInteger('participation_id')->unsigned();
            $table->timestamps();

            $table->foreign('participation_id')
                ->references('id')
                ->on(ConfigurationManager::PARTICIPATION_TABLE)
                ->onDelete('cascade');

            $table->foreign('conversation_id')
                ->references('id')
                ->on(ConfigurationManager::CONVERSATIONS_TABLE)
                ->onDelete('cascade');
        });

        Schema::create(ConfigurationManager::MESSAGES_TABLE . '_deleted', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('message_id')->unsigned();
            $table->bigInteger('participation_id')->unsigned();
            $table->timestamps();

            $table->foreign('participation_id')
                ->references('id')
                ->on(ConfigurationManager::PARTICIPATION_TABLE)
                ->onDelete('cascade');

            $table->foreign('message_id')
                ->references('id')
                ->on(ConfigurationManager::MESSAGES_TABLE)
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(ConfigurationManager::MESSAGES_TABLE . '_deleted');
        Schema::dropIfExists(ConfigurationManager::PARTICIPATION_TABLE . '_deleted');
    }
}
