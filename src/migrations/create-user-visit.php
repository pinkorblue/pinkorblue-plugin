<?php

namespace Robera\AB;

// use \Illuminate\Support\Facades\Schema;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;

use \Illuminate\Database\Capsule\Manager as Capsule;

class CreateUserVisitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        global $wpdb;
        if (Capsule::schema()->hasTable($wpdb->prefix . AB_PLUGIN_PREFIX . '_user_visits')) {
            return;
        }
        Capsule::schema()->create($wpdb->prefix . AB_PLUGIN_PREFIX . '_user_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_id', 100)->nullable(false);
            $table->unsignedBigInteger('variant_id')->nullable(false);
            $table->boolean('did_interact');

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
        Capsule::schema()->dropIfExists($wpdb->prefix . AB_PLUGIN_PREFIX . '_user_visits');
    }
}
