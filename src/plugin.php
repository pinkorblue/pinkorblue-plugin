<?php
namespace Robera\AB;

require_once('migrations/create-variant.php');
require_once('migrations/create-ab-test.php');
require_once('migrations/create-user-visit.php');
require_once('migrations/ab-test-add-start-date.php');
require_once('migrations/create-remote-request.php');
require_once('migrations/remote-request-add-iid.php');

if (class_exists('RoberaABPlugin')) {
    return;
}

class RoberaABPlugin
{
    public static $AB_POST_STATUS = AB_PLUGIN_PREFIX;

    public static $INSTALLATION_RANDOM_VALUE_OPTION_NAME = AB_PLUGIN_PREFIX . "_installation_random";
    public static $EXPIRATION_DATE_OPTION_NAME = AB_PLUGIN_PREFIX . "_expiration_date";

    public static $ACCOUNT_STATUS_OPTION_NOT_REGISTERED = AB_PLUGIN_PREFIX . "_account_not_registered";
    public static $ACCOUNT_STATUS_OPTION_WAITING_VERIFY = AB_PLUGIN_PREFIX . "_account_waiting_verify";
    public static $ACCOUNT_STATUS_OPTION_VERIFIED = AB_PLUGIN_PREFIX . "_account_verified";
    public static $ACCOUNT_STATUS_OPTION_NAME = AB_PLUGIN_PREFIX . "_account_status";

    public static $ACCOUNT_EMAIL_OPTION_NAME = AB_PLUGIN_PREFIX . "_account_email";

    public static $NEXT_PLAN_NAME_OPTION_NAME = AB_PLUGIN_PREFIX."_next_plan_name";
    public static $NEXT_PLAN_PRICE_TOMAN_OPTION_NAME = AB_PLUGIN_PREFIX."_next_plan_price_toman";
    public static $NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME = AB_PLUGIN_PREFIX."_next_plan_number_of_days";
    public static $META_KEYS_OPTION_NAME = AB_PLUGIN_PREFIX . '_meta_settings';

    private static $REQUEST_LOCK_KEY = 'remote_request_process_lock';
    private static $CHECK_LOCK_KEY = 'check_process_lock';

    private static $SCHEDULE_NAME = AB_PLUGIN_PREFIX . "_schedule";


    public static $ROBERA_MIGRATIONS = array(
        'CreateABTestTable',
        'CreateVariantsTable',
        'CreateUserVisitsTable',
        'AddStartDate',
        'CreateRemoteRequestTable',
        'AddIID'
    );
    public static $IS_VARIANT_POST_META_KEY = '_' . AB_PLUGIN_PREFIX . '_is_variant';

    public static $DEFAULT_SUPPORTED_META_KEYS = array('_thumbnail_id', '_product_image_gallery', '_wp_page_template');
    public static $DEFAULT_BLACKLIST_META_KEYS = array(
        '_' . AB_PLUGIN_PREFIX . '_is_variant',
        '_price',
        '_sale_price',
        '_regular_price',
        '_stock',
        '_stock_status',
        '_manage_stock',
        '_sold_individually',
        '_virtual',
        '_downloadable',
        '_download_limit',
        '_download_expiry'
    );

    public function __construct()
    {
        $this->has_woocommerce = in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins'))
        );

        register_activation_hook(ROBERA_AB_PLUGIN_FILE_PATH, array($this, 'activate'));
        register_deactivation_hook(ROBERA_AB_PLUGIN_FILE_PATH, array($this, 'deactivate'));
        register_uninstall_hook(ROBERA_AB_PLUGIN_FILE_PATH, array('\Robera\AB\RoberaABPlugin', 'uninstall'));

        add_action('init', array($this, 'registerABTestPostTypes'));

        add_action('plugins_loaded', array($this, 'checkUpdate'));
        add_action('plugins_loaded', array($this, 'loadTranslationFiles'));

        /*
            Dont schedule while using get requests
         */
        // add_filter('cron_schedules', array($this, 'addCheckRequestsSchedule'));

        // add_action('check_tests_cron_hook', array('\Robera\AB\RoberaABPlugin', 'checkTests'));

        // if (! wp_next_scheduled('check_tests_cron_hook')) {
        //     wp_schedule_event(time(), self::$SCHEDULE_NAME, 'check_tests_cron_hook');
        // }


        // add_action('check_requests_cron_hook', array('\Robera\AB\RoberaABPlugin', 'checkRequests'));
        // if (! wp_next_scheduled('check_requests_cron_hook')) {
        //     wp_schedule_event(time(), self::$SCHEDULE_NAME, 'check_requests_cron_hook');
        // }
    }

    protected static function lockProcess($key = None, $duration = 60)
    {
        set_site_transient($key, microtime(), $duration);
    }

    protected static function unlockProcess($key)
    {
        delete_site_transient($key);
    }

    public static function checkAll()
    {
        if (get_site_transient(self::$CHECK_LOCK_KEY)) {
            return;
        }
        self::lockProcess(self::$CHECK_LOCK_KEY, 10);
        try {
            self::checkRequests();
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        try {
            self::checkTests();
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    public static function checkRequests()
    {
        if (!get_option(PinkOrBlueClient::$CLIENT_SECRET_SENT_SUCCESS_OPTION_NAME)) {
            if (!PinkOrBlueClient::sendSiteInfo()) {
                return;
            }
        }
        if (!get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME) &&
                ABTest::where('state', AB_TEST_STATE_RUNNING)->first()) {
            if (!PinkOrBlueClient::sendStartTrial()) {
                return;
            }
        }
        if (RoberaABPlugin::isExpired()) {
            return;
        }

        if (get_site_transient(self::$REQUEST_LOCK_KEY)) {
            return;
        }
        self::lockProcess(self::$REQUEST_LOCK_KEY, 600);
        $not_sent_requests = RemoteRequest::all();
        foreach ($not_sent_requests as $request) {
            $repeat = PinkOrBlueClient::handleRemoteRequest($request);
            if (! $repeat) {
                $request->delete();
            }
        }
        self::unlockProcess(self::$REQUEST_LOCK_KEY);
    }


    public function addCheckRequestsSchedule($schedules)
    {
        $schedules[self::$SCHEDULE_NAME] = array(
            'interval' => 5,
            'display'  => esc_html__('Every Five Seconds'),
        );
        return $schedules;
    }

    public function loadTranslationFiles()
    {
        load_plugin_textdomain('robera-ab-test', false, basename(dirname(ROBERA_AB_PLUGIN_FILE_PATH)) . '/languages');
    }

    public static function checkTests()
    {
        $running_tests = ABTest::where('state', AB_TEST_STATE_RUNNING)->
                            select('id', 'started_at')->get();
        foreach ($running_tests as $test) {
            if (($test->started_at && current_time('timestamp', true) - mysql2date('U', $test->started_at) > 3600 * 24 * AB_TEST_MAXIMUM_DAYS) || RoberaABPlugin::isExpired()) {
                $test->state = AB_TEST_STATE_FINISHED;
                $test->save();
            }
        }
    }

    public static function isExpired()
    {
        $expiration_date_str = get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME);
        return ($expiration_date_str && current_time('timestamp', true) -  mysql2date('U', $expiration_date_str) >= 0);
    }

    public function checkUpdate()
    {
        if (get_site_option('robera_plugin_version') != ROBERA_AB_PLUGIN_VERSION) {
            $this->activate(false);
            update_option('robera_plugin_version', ROBERA_AB_PLUGIN_VERSION);
        }
    }

    public function registerABTestPostTypes()
    {
        register_post_status(RoberaABPlugin::$AB_POST_STATUS, array(
            'label'                     => 'Variant',
            'public'                    => (isset($_GET['elementor-preview']) || isset($_GET['preview_id'])) && current_user_can('edit_others_posts'),
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => false,
            'show_in_admin_status_list' => false,
            'post_type'                 => array('post', 'product', 'page'),
        ));
    }

    public function activate($should_register = true)
    {
        $random_value = bin2hex(random_bytes(6));
        $client_secret = bin2hex(random_bytes(12));

        add_option(RoberaABPlugin::$INSTALLATION_RANDOM_VALUE_OPTION_NAME, $random_value);
        add_option(RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME, RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NOT_REGISTERED);
        add_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME, null);
        add_option(PinkOrBlueClient::$CLIENT_SECRET_OPTION_NAME, $client_secret);

        PinkOrBlueClient::sendSiteInfo();

        foreach (RoberaABPlugin::$ROBERA_MIGRATIONS as $migration) {
            $up_function = array('\Robera\AB\\' . $migration, 'up');
            $up_function();
        }

        if ($should_register) {
            $this->registerABTestPostTypes();
            flush_rewrite_rules();
        }
    }

    public function deactivate()
    {
        /*
            Dont unschedule while using get requests
         */
        // $timestamp = wp_next_scheduled('check_requests_cron_hook');
        // wp_unschedule_event($timestamp, 'check_requests_cron_hook');
        
        // $timestamp = wp_next_scheduled('check_tests_cron_hook');
        // wp_unschedule_event($timestamp, 'check_tests_cron_hook');
    }

    public static function uninstall()
    {
        foreach (array_reverse(RoberaABPlugin::$ROBERA_MIGRATIONS) as $migration) {
            $down_function = array('\Robera\AB\\' . $migration, 'down');
            $down_function();
        }

        delete_option(RoberaABPlugin::$INSTALLATION_RANDOM_VALUE_OPTION_NAME);
        delete_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME);
        delete_option(PinkOrBlueClient::$CLIENT_SECRET_OPTION_NAME);
        delete_option(PinkOrBlueClient::$CLIENT_SECRET_SENT_SUCCESS_OPTION_NAME);
        delete_option(RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME);
        delete_option(RoberaABPlugin::$ACCOUNT_EMAIL_OPTION_NAME);
        delete_option(RoberaABPlugin::$NEXT_PLAN_NAME_OPTION_NAME);
        delete_option(RoberaABPlugin::$NEXT_PLAN_PRICE_TOMAN_OPTION_NAME);
        delete_option(RoberaABPlugin::$NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME);
        delete_option(RoberaABPlugin::$META_KEYS_OPTION_NAME);
    }
}
