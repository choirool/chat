<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Musonza\Chat\ConfigurationManager;

class AddDeletedAtOnMessagesAndConversationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(ConfigurationManager::CONVERSATIONS_TABLE, function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table(ConfigurationManager::MESSAGES_TABLE, function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(ConfigurationManager::CONVERSATIONS_TABLE, function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table(ConfigurationManager::MESSAGES_TABLE, function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
