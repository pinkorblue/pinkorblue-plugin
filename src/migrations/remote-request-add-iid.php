<?php

namespace Robera\AB;

// use \Illuminate\Support\Facades\Schema;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;

use \Illuminate\Database\Capsule\Manager as Capsule;

class AddIID extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        global $wpdb;
        if (Capsule::schema()->hasColumn($wpdb->prefix . AB_PLUGIN_PREFIX . '_remote_requests', 'iid')) {
            return;
        }
        Capsule::schema()->table($wpdb->prefix . AB_PLUGIN_PREFIX . '_remote_requests', function (Blueprint $table) {
            $table->string('iid', 50)->nullable(false);
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
        Capsule::schema()->table($wpdb->prefix . AB_PLUGIN_PREFIX . '_remote_requests', function (Blueprint $table) {
            $table->dropColumn('iid');
        });
    }
}
