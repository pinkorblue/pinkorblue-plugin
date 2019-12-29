<?php

namespace Robera\AB;

// use \Illuminate\Support\Facades\Schema;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;

use \Illuminate\Database\Capsule\Manager as Capsule;

class AddStartDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        global $wpdb;
        if (Capsule::schema()->hasColumn($wpdb->prefix . AB_PLUGIN_PREFIX . '_ab_tests', 'started_at')) {
            return;
        }
        Capsule::schema()->table($wpdb->prefix . AB_PLUGIN_PREFIX . '_ab_tests', function (Blueprint $table) {
            $table->dateTimeTz('started_at')->default(null)->nullable(true);
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
        Capsule::schema()->table($wpdb->prefix . AB_PLUGIN_PREFIX . '_ab_tests', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
}
