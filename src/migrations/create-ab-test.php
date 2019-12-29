<?php

namespace Robera\AB;

// use \Illuminate\Support\Facades\Schema;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;

use \Illuminate\Database\Capsule\Manager as Capsule;

class CreateABTestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        global $wpdb;
        if (Capsule::schema()->hasTable($wpdb->prefix . AB_PLUGIN_PREFIX . '_ab_tests')) {
            return;
        }
        Capsule::schema()->create($wpdb->prefix . AB_PLUGIN_PREFIX . '_ab_tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->nullable(true);
            $table->unsignedBigInteger('post_id')->nullable(false);
            $table->string('description', 2000)->nullable(true);
            $table->string('state', 100)->nullable(false);
            $table->string('type', 100)->nullable(false);
            $table->string('kpi', 100)->nullable(true);
            $table->unsignedBigInteger('target_page_id');
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
        Capsule::schema()->dropIfExists($wpdb->prefix . AB_PLUGIN_PREFIX . '_ab_tests');
    }
}
