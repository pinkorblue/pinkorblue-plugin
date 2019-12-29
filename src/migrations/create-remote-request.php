<?php

namespace Robera\AB;

// use \Illuminate\Support\Facades\Schema;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;

use \Illuminate\Database\Capsule\Manager as Capsule;

class CreateRemoteRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        global $wpdb;
        if (Capsule::schema()->hasTable($wpdb->prefix . AB_PLUGIN_PREFIX . '_remote_requests')) {
            return;
        }
        Capsule::schema()->create($wpdb->prefix . AB_PLUGIN_PREFIX . '_remote_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 200)->nullable(false);
            $table->string('data', 10000)->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public static function down()
    {
        global $wpdb;
        Capsule::schema()->dropIfExists($wpdb->prefix . AB_PLUGIN_PREFIX . '_remote_requests');
    }
}
