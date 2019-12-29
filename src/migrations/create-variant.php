<?php

namespace Robera\AB;

// use \Illuminate\Support\Facades\Schema;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;

use \Illuminate\Database\Capsule\Manager as Capsule;

class CreateVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        global $wpdb;
        if (Capsule::schema()->hasTable($wpdb->prefix . AB_PLUGIN_PREFIX . '_variants')) {
            return;
        }
        Capsule::schema()->create($wpdb->prefix . AB_PLUGIN_PREFIX . '_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('post_id')->nullable(false);
            $table->unsignedBigInteger('ab_test_id')->nullable(false);
            $table->string('name', 100)->nullable(false);
            $table->unsignedInteger('percentage')->nullable(false);
            $table->double('kpi_value', 16, 3);
            $table->unsignedBigInteger('visits');
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
        Capsule::schema()->dropIfExists($wpdb->prefix . AB_PLUGIN_PREFIX . '_variants');
    }
}
