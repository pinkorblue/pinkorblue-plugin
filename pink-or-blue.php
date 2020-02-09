<?php
/**
 * robera-ab-test
 *
 * @package   Pink or Blue A/B Test
 * @author    Erfan Loghmani
 * @copyright 2019 Rebora
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Pink or Blue A/B Test
 * Description:       This Plugin helps admin to create ab test to analyse users' behavior toward different contents and user interfaces.
 * Text Domain:       robera-ab-test
 * Version:           0.1.12
 */

namespace Robera\AB;

require_once(__DIR__ . '/vendor/autoload.php');

if (!defined('ROBERA_AB_PLUGIN_PATH')) {
    define('ROBERA_AB_PLUGIN_PATH', dirname(__FILE__) . '/');
    define('ROBERA_AB_PLUGIN_VERSION', '0.1.12');
    define('ROBERA_AB_PLUGIN_FILE_PATH', __FILE__);
    define('AB_PLUGIN_PREFIX', 'robera');

    define('AB_TEST_STATE_NOT_STARTED', 'nost');
    define('AB_TEST_STATE_RUNNING', 'run');
    define('AB_TEST_STATE_FINISHED', 'fnsh');

    define('STAY_KPI', 'sty');
    define('REVENUE_KPI', 'rev');
    define('VISIT_KPI', 'vis');
    define('PRODUCT_BUY_KPI', 'prd');
    define('VISIT_PRODUCT_KPI', 'vsp');

    define('PAGE_TEST', 'pgt');
    define('PRODUCT_TEST', 'prt');
    define('MAX_NUMBER_OF_VARIANTS', 2);

    define('AB_TEST_MAXIMUM_DAYS', 30);

    define('ROBERA_SERVER_ADDRESS', 'https://pinkorblue.info/api');
    define('ROBERA_SERVER_API_VERSION', 'v1');
    define('ROBERA_SENTRY_URL', 'https://42b2ee43b8bb42eaac01067a50353bb3@sentry.io/1852498');

    define('MY_TEST_PAGE_ERROR_RUNNING_TEST_EXISTS', 'running_test_exists');
}

try {
    require 'vendor/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = \Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/pinkorblue/pinkorblue-plugin',
        __FILE__,
        'pinkorblue-ab-test'
    );
} catch (\Exception $ex) {
    $client = (new \Raven_Client(ROBERA_SENTRY_URL))->install();
    $event_id = $client->captureException($ex);
}

try {
    $capsuleClassName = '\Illuminate\Database\Capsule\Manager';
    if (class_exists($capsuleClassName)) {
        $capsule =  new $capsuleClassName;
        $capsule->addConnection(array(
            "driver" => "mysql",
            "host" => DB_HOST,
            "database" => DB_NAME,
            "username" => DB_USER,
            "password" => DB_PASSWORD,
            "charset"   => "utf8",
            "collation" => "utf8_unicode_ci",
        ));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    } else {
        return new \Exception("Capsule class does not exists");
    }
} catch (\Exception $ex) {
    $client = (new \Raven_Client(ROBERA_SENTRY_URL))->install();
    $event_id = $client->captureException($ex);
}

// Capture an exception if its from Robera namespace
set_exception_handler(function ($ex) {
    foreach ($ex->getTrace() as $trace_item) {
        if (array_key_exists("class", $trace_item) && strpos($trace_item["class"], "Robera") !== false) {
            $client = (new \Raven_Client(ROBERA_SENTRY_URL))->install();
            $event_id = $client->captureException($ex);
            return;
        }
    }
    throw $ex;
});

try {
    require_once(__DIR__ . '/src/models/variant.php');
    require_once(__DIR__ . '/src/models/ab-test.php');
    require_once(__DIR__ . '/src/models/user-visit.php');
    require_once(__DIR__ . '/src/models/remote-request.php');

    require_once(__DIR__ . '/src/client.php');
    require_once(__DIR__ . '/src/plugin.php');
    $plugin = new RoberaABPlugin();
    require_once(__DIR__ . '/src/admin-page.php');
    $admin_page = new AdminPage();

    if (!is_admin()) {
            require_once('src/ab-controller.php');
            $ab_controller = new ABController();
    }
} catch (\Exception $ex) {
    $client = (new \Raven_Client(ROBERA_SENTRY_URL))->install();
    $event_id = $client->captureException($ex);
}
