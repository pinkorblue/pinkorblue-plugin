<?php
namespace Robera\AB;

require_once('utils/post-utils.php');
require_once('utils/time-utils.php');
require_once('models/ab-test.php');
require_once('models/variant.php');

if (class_exists('AdminPage')) {
    return;
}


class AdminPage
{
    private static $MY_TESTS_PAGE_NAME = AB_PLUGIN_PREFIX . '-my-tests';
    private static $EDIT_TEST_PAGE_NAME = AB_PLUGIN_PREFIX . '-edit-test';
    private static $ACCOUNT_PAGE_NAME = AB_PLUGIN_PREFIX.'-account';
    private static $SETTINGS_PAGE_NAME = AB_PLUGIN_PREFIX.'-settings';

    public static $REGISTRATION_STATUS_SUCCESS = '100';
    public static $REGISTRATION_STATUS_GENERAL_ERROR = '110';

    public static $VERIFICATION_STATUS_SUCCESS = '200';
    public static $VERIFICATION_STATUS_GENERAL_ERROR = '210';
    public static $VERIFICATION_STATUS_NOT_VALID = '211';
    public static $VERIFICATION_STATUS_EXPIRED = '212';

    public static $PAYMENT_STATUS_SUCCESS = '300';
    public static $PAYMENT_STATUS_ERROR = '310';


    public function __construct()
    {

        if (is_admin()) {
            add_action('admin_menu', array(&$this, 'createMenus'));
            add_action('admin_enqueue_scripts', array(&$this, 'enqueueScripts'));
        }

        add_action('wp_ajax_create_variant', array(&$this, 'createVariant'));

        add_action('rest_api_init', array(&$this, 'restApisRegisteration'));

        add_action('save_post', array(&$this, 'redirectEditedVariant'), 100);
        add_action('do_meta_boxes', array(&$this, 'configureMetaboxes'));
    }

    public function redirectEditedVariant($post_id)
    {

        $post = get_post($post_id);

        if (!get_post_meta($post_id, RoberaABPlugin::$IS_VARIANT_POST_META_KEY, true)) {
            return;
        }
        remove_action('save_post', array(&$this, 'redirectEditedVariant'), 100);
        $query = array(
            'ID' => $post_id,
            'post_status' => RoberaABPlugin::$AB_POST_STATUS,
        );
        wp_update_post( $query, true );

        if (!PostUtils::isGutenburgForPostType($post->post_type)) {
            $test = Variant::where("post_id", $post_id)->select("ab_test_id")->get()->toArray();
            $edit_link = "/wp-admin/admin.php?page=robera-edit-test&test=".$test[0]["ab_test_id"];
            wp_redirect(home_url($edit_link));
            exit();
        }
    }

    private function getPostTypesMetaKeys($post_types)
    {
        global $wpdb;
        $query = "
            SELECT DISTINCT($wpdb->postmeta.meta_key)
            FROM $wpdb->posts
            LEFT JOIN $wpdb->postmeta
            ON $wpdb->posts.ID = $wpdb->postmeta.post_id
            WHERE $wpdb->posts.post_type IN (" . implode(', ', array_fill(0, count($post_types), '%s')) . ")
            AND $wpdb->postmeta.meta_key != ''
        ";
        $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($query), $post_types));
        $meta_keys = $wpdb->get_col($query);
        return $meta_keys;
    }

    private function getRedirectToMyTestsResponse()
    {
        $response = new \WP_REST_Response();
        $response->set_status(302);
        $response->header('Location', '/wp-admin/admin.php?page=' . AdminPage::$MY_TESTS_PAGE_NAME);
        return $response;
    }

    private function getRedirectToSettingsResponse()
    {
        $response = new \WP_REST_Response();
        $response->set_status(302);
        $response->header('Location', '/wp-admin/admin.php?page=' . AdminPage::$SETTINGS_PAGE_NAME);
        return $response;
    }

    private function getRedirectToAccountResponse($status = null)
    {
        $response = new \WP_REST_Response();
        $response->set_status(302);
        if ($status) {
            $response->header('Location', sprintf('/wp-admin/admin.php?page=%s&status=%s', AdminPage::$ACCOUNT_PAGE_NAME, $status));
        } else {
            $response->header('Location', sprintf('/wp-admin/admin.php?page=%s', AdminPage::$ACCOUNT_PAGE_NAME));
        }
        return $response;
    }

    private function getRedirectToMyTestsWithErrorResponse($test_id, $error)
    {
        $response = new \WP_REST_Response();
        $response->set_status(302);
        $url = add_query_arg(
            array(
                "test" => $test_id,
                "error" => $error
            ),
            "wp-admin/admin.php?page=".AdminPage::$MY_TESTS_PAGE_NAME
        );
        $response->header('Location', $url);
        return $response;
    }

    private function sendJsonError($text)
    {
        wp_send_json_error(array(
            'message' => $text
        ), 500);
    }

    public function restApisRegisteration()
    {
        // TODO -> It may be better permissioning if we permit each users edit tests of pages that he can edit
        $can_edit_others = function () {
            return current_user_can('edit_others_posts');
        };
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/account/register', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'registerEmail')
        ));

        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/account/verify', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'verifyAccount')
        ));

        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/account/ignore-registeration', array(
            'methods' => 'GET',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'ignoreRegisteration')
        ));

        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/search', array(
            'methods' => 'GET',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'searchInPosts')
        ));

        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/start', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'abTestStart')
        ));
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/stop', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'abTestStop')
        ));
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/delete/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'abTestDelete')
        ));
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/(?P<id>\d+)', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'abTestEdit')
        ));
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'abTestAdd')
        ));
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/settings', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'changeSettings')
        ));
        register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/check', array(
            'methods' => 'GET',
            'callback' => array('\Robera\AB\RoberaABPlugin', 'checkAll')
        ));
    }

    public function registerEmail($request)
    {
        $data = $request->get_body_params();
        $status = PinkOrBlueClient::handleRegisterRequest($data["email"]);
        if ($status == self::$REGISTRATION_STATUS_SUCCESS) {
            update_option(RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME, RoberaABPlugin::$ACCOUNT_STATUS_OPTION_WAITING_VERIFY);
            update_option(RoberaABPlugin::$ACCOUNT_EMAIL_OPTION_NAME, $data["email"]);
        }
        return $this->getRedirectToAccountResponse($status);
    }

    public function verifyAccount($request)
    {
        $data = $request->get_body_params();
        $status = PinkOrBlueClient::handleVerifyRequest(get_option(RoberaABPlugin::$ACCOUNT_EMAIL_OPTION_NAME), $data["verification-code"]);
        if ($status == self::$VERIFICATION_STATUS_SUCCESS) {
            update_option(
                RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME,
                RoberaABPlugin::$ACCOUNT_STATUS_OPTION_VERIFIED
            );
        }
        return $this->getRedirectToAccountResponse($status);
    }

    public function ignoreRegisteration($request)
    {
        update_option(
            RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME,
            RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NOT_REGISTERED
        );
        return $this->getRedirectToAccountResponse();
    }

    public function searchInPosts($request)
    {
        $results = [];
        $posts = get_posts([
            'numberposts' => 20,
            'post_type' => explode(",", $request["subtype"]),
            's' => $request["search"],
            'orderby' => 'relevance'
        ]);
        foreach ($posts as $post) {
            $results[] = [
                'title' => $post->post_title,
                'id' => $post->ID,
                'subtype' => $post->post_type
            ];
        }
        return $results;
    }

    public function abTestAdd($request)
    {
        $data = $request->get_body_params();
        $num_variants = (int)$data["num_variants"];

        if ($data["kpi"] == PRODUCT_BUY_KPI) {
            $target_page_id = (int)$data["target-product-id"];
        } else {
            $target_page_id = (int)$data["target-page-id"];
        }

        $ab_test = new ABTest();

        $ab_test->populate((int)$data["test-page-id"], $data["test-name"], $data["test-desc"], $data["kpi"], $target_page_id, $data['test-target'], $data['state']);
        $variants = array();
        for ($i = 0; $i < $num_variants; $i++) {
            $pref = "variant-".$i."-";
            $variant_new = new Variant();
            $variant_new->populate((int)$data[$pref."page-id"], (int)$ab_test->ID, $data[$pref."name"], 0, 0, (int)$data[$pref."percentage"]);
            $variants[] = $variant_new;
        }
        if ($data["state"] == AB_TEST_STATE_RUNNING) {
            $running_test = ABTest::where('post_id', (int)$data["test-page-id"])->where('state', AB_TEST_STATE_RUNNING)->first();
            if ($running_test && $running_test->id != $ab_test->id) {
                $ab_test->state = AB_TEST_STATE_NOT_STARTED;
                $ab_test->save();
                return $this->getRedirectToMyTestsWithErrorResponse($ab_test->ID, MY_TEST_PAGE_ERROR_RUNNING_TEST_EXISTS);
            }
            $ab_test->started_at = current_time('mysql', true);
            $ab_test->save();
        }
        if ($data["state"] == AB_TEST_STATE_RUNNING && !get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
            PinkOrBlueClient::sendStartTrial();
        }
        return $this->getRedirectToMyTestsResponse();
    }

    public function changeSettings($request)
    {
        $data = $request->get_body_params();
        $metas = join(",", $data["metas"]);
        update_option(RoberaABPlugin::$META_KEYS_OPTION_NAME, $metas);
        return $this->getRedirectToSettingsResponse();
    }

    public function abTestEdit($request)
    {
        $test_id = $request["id"];
        $data = $request->get_body_params();
        $num_variants = (int)$data["num_variants"];

        if ($data["kpi"] == PRODUCT_BUY_KPI) {
            $target_page_id = (int)$data["target-product-id"];
        } else {
            $target_page_id = (int)$data["target-page-id"];
        }

        Variant::where('ab_test_id', $test_id)->delete();
        $ab_test = ABTest::where("ID", $test_id)->first();
        $ab_test->update([
            'name' => $data["test-name"],
            'post_id' => $data["test-page-id"],
            'description' => $data["test-desc"],
            'kpi' => $data["kpi"],
            'target_page_id' => $target_page_id,
            'type' => $data["test-target"],
            'state' => $data["state"],
        ]);
        $variants = array();
        for ($i = 0; $i < $num_variants; $i++) {
            $pref = "variant-".$i."-";
            $variant_new = new Variant();
            $variant_new->populate((int)$data[$pref."page-id"], $test_id, $data[$pref."name"], 0, 0, (int)$data[$pref."percentage"]);
            $variants[] = $variant_new;
        }
        if ($data["state"] == AB_TEST_STATE_RUNNING) {
            $running_test = ABTest::where('post_id', (int)$data["test-page-id"])->where('state', AB_TEST_STATE_RUNNING)->first();
            if ($running_test && $running_test->id != $ab_test->id) {
                $ab_test->state = AB_TEST_STATE_NOT_STARTED;
                $ab_test->save();
                return $this->getRedirectToMyTestsWithErrorResponse($ab_test->ID, MY_TEST_PAGE_ERROR_RUNNING_TEST_EXISTS);
            }
            $ab_test->started_at = current_time('mysql', true);
            $ab_test->save();
        }
        if ($data["state"] == AB_TEST_STATE_RUNNING && !get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
            PinkOrBlueClient::sendStartTrial();
        }
        return $this->getRedirectToMyTestsResponse();
    }

    public function abTestDelete($request)
    {
        $test_id = $request["id"];
        ABTest::where('ID', $test_id)->delete();
        Variant::where('ab_test_id', $test["id"])->delete();
        return $this->getRedirectToMyTestsResponse();
    }

    public function abTestStart($request)
    {
        $data = $request->get_body_params();

        $test_id = (int)$data["test_id"];

        $test = ABTest::find($test_id);
        $test->state = AB_TEST_STATE_RUNNING;
        $test->started_at = current_time('mysql', true);
        $test->save();

        if (!get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
            PinkOrBlueClient::sendStartTrial();
        }

        return $this->getRedirectToMyTestsResponse();
    }

    public function abTestStop($request)
    {
        $data = $request->get_body_params();

        $test_id = (int)$data["test_id"];
        $test = ABTest::find($test_id);
        $test->state = AB_TEST_STATE_FINISHED;
        $test->save();

        return $this->getRedirectToMyTestsResponse();
    }

    public function enqueueScripts($hook_suffix)
    {
        wp_enqueue_script(AB_PLUGIN_PREFIX . '_check', plugins_url('static/check.js', __FILE__));

        if (strpos($hook_suffix, AB_PLUGIN_PREFIX) !== false) {
            wp_enqueue_style('robera-ab-style', plugins_url('static/style.css', __FILE__));
            if (is_rtl()) {
                wp_enqueue_style('semantic-style-rtl', plugins_url('static/semantic.rtl.min.css', __FILE__));
                wp_enqueue_style('rtl-style', plugins_url('static/style-rtl.css', __FILE__));
            } else {
                wp_enqueue_style('semantic-style', plugins_url('static/semantic.min.css', __FILE__));
                 wp_enqueue_style('ltr-style', plugins_url('static/style-ltr.css', __FILE__));
            }
            wp_enqueue_style('fix-style', plugins_url('static/fix.css', __FILE__));

            wp_register_script('jquery3.1.1', plugins_url('static/jquery.min.js', __FILE__), array(), null, false);
            wp_add_inline_script('jquery3.1.1', 'var jQuery3_1_1 = $.noConflict(true);');
            wp_enqueue_script('semantic-js', plugins_url('static/semantic.min.js', __FILE__), array('jquery3.1.1'));
        }
        wp_enqueue_style('robera-ab-overall-style', plugins_url('static/overall.style.css', __FILE__));

        if ($hook_suffix == "post.php") {
            global $post;
            if (get_post_meta($post->ID, RoberaABPlugin::$IS_VARIANT_POST_META_KEY, true)  && PostUtils::isGutenburgForPostType($post->post_type)) {
                $test = Variant::where("post_id", $post->ID)->select("ab_test_id")->get()->toArray();
                wp_enqueue_script('gutenberg-changes', plugins_url('static/gutenberg-changes.js', __FILE__), array('wp-data', 'wp-editor'));
                wp_localize_script(
                    'gutenberg-changes',
                    'admin_info',
                    array(
                        'test_id' => $test[0]["ab_test_id"]
                    )
                );
                wp_enqueue_script('redirect-after-save-variant');
            }
        }
    }

    public function configureMetaboxes() {
        if (! isset($_REQUEST['post']))
            return;
        $post_id = absint($_REQUEST['post']);

        $post = get_post($post_id);
        if (isset($post) && count(get_post_meta($post_id, RoberaABPlugin::$IS_VARIANT_POST_META_KEY)) == 0) {
            return;
        }

        if ($post->post_type == 'product') {
            remove_meta_box('woocommerce-product-data', $post->post_type, 'normal');
            remove_meta_box('product_catdiv', $post->post_type, 'side');
            remove_meta_box('tagsdiv-product_tag', $post->post_type, 'side');
        } else if (in_array($post->post_type, array('page', 'post'))) {
            remove_meta_box('commentstatusdiv', $post->post_type, 'side');
            remove_meta_box('slugdiv', $post->post_type, 'normal');
            remove_meta_box('categorydiv', $post->post_type, 'side');
        }
    }

    public function createMenus()
    {
        add_menu_page(esc_html__('Pink or Blue', 'robera-ab-test'), esc_html__('Pink or Blue', 'robera-ab-test'), 'manage_options', AdminPage::$MY_TESTS_PAGE_NAME, array($this, 'myTestsPage'), plugins_url('static/pinkorblueadmin.png', __FILE__), 6);

        add_submenu_page(AdminPage::$MY_TESTS_PAGE_NAME, esc_html__('My Tests', 'robera-ab-test'), esc_html__('All Tests', "robera-ab-test"), 'manage_options', AdminPage::$MY_TESTS_PAGE_NAME, array($this, 'myTestsPage'));

        if (!RoberaABPlugin::isExpired()) {
            add_submenu_page(AdminPage::$MY_TESTS_PAGE_NAME, esc_html__('Add A/B test', 'robera-ab-test'), esc_html__('Add New', "robera-ab-test"), 'manage_options', AB_PLUGIN_PREFIX.'-add-test', array($this, 'addPage'));
            add_submenu_page(null, esc_html__('Edit Test', 'robera-ab-test'), esc_html__('Edit Test', 'robera-ab-test'), 'manage_options', AdminPage::$EDIT_TEST_PAGE_NAME, array($this, 'editTestPage'));
        }
        
        add_submenu_page(AdminPage::$MY_TESTS_PAGE_NAME, esc_html__('Pink or Blue Account', 'rebora-ab-test'), esc_html__('Account', "robera-ab-test"), 'manage_options', AdminPage::$ACCOUNT_PAGE_NAME, array($this, 'accountPage'));

        add_submenu_page(AdminPage::$MY_TESTS_PAGE_NAME, esc_html__('Pink or Blue Settings', 'rebora-ab-test'), esc_html__('Settings', "robera-ab-test"), 'manage_options', AdminPage::$SETTINGS_PAGE_NAME, array($this, 'settingsPage'));
    }

    public function myTestsPage()
    {
        $all_tests = ABTest::all();
        $unbegun_tests = array();
        $running_tests = array();
        $finished_tests = array();
        $error = $this->getErrorMessage(@$_GET['error'] ?: '', @$_GET['test'] ?: '');
        foreach ($all_tests as $test) {
            $test_variants = Variant::where('ab_test_id', $test["id"])->get()->toArray();
            $combo_test = array($test, $test_variants);

            if ($test['state'] == AB_TEST_STATE_NOT_STARTED) {
                $combo_test[0]["can_start"] = $test->canStart();
                $unbegun_tests[] = $combo_test;
            } elseif ($test['state'] == AB_TEST_STATE_RUNNING) {
                $running_tests[] = $combo_test;
            } else {
                $finished_tests[] = $combo_test;
            }
        }
        require_once('templates/my-tests-page.php');
    }

    public function addPage()
    {
        $mode = "add";
        $test = new ABTest;
        $variants = array();
        $has_woocommerce = in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins'))
        );
        require_once('templates/edit-test-page.php');
    }

    public function getErrorMessage($error, $test_id)
    {
        if ($error == MY_TEST_PAGE_ERROR_RUNNING_TEST_EXISTS) {
            $test_name = ABTest::where("ID", $test_id)->first()->name;
            return array(
                "header" => sprintf(esc_html__("Running %s test failed", 'robera-ab-test'), $test_name),
                "text" => esc_html__("There is running test on this content. You can't run multiple test on a content.", 'robera-ab-test')
            );
        }
        return null;
    }

    public function editTestPage()
    {
        if (isset($_GET['test'])) {
            $mode = "edit";
            $test_id = $_GET['test'];
            $test = ABTest::find($test_id);
            if ($test["state"] != AB_TEST_STATE_NOT_STARTED) {
                wp_redirect('admin.php?page=robera-my-tests');
                exit;
            } else {
                $variants = Variant::where('ab_test_id', (int)$test_id)->get()->toArray();
                $has_woocommerce = in_array(
                    'woocommerce/woocommerce.php',
                    apply_filters('active_plugins', get_option('active_plugins'))
                );
                require_once('templates/edit-test-page.php');
            }
        }
    }

    public function accountPage()
    {
        $messages = array(
            self::$REGISTRATION_STATUS_SUCCESS => esc_html__('Registering email was successfull.', 'robera-ab-test'),
            self::$REGISTRATION_STATUS_GENERAL_ERROR => esc_html__('Registering email was not successfull.', 'robera-ab-test'),
            self::$VERIFICATION_STATUS_SUCCESS => esc_html__('Verifying email was successfull.', 'robera-ab-test'),
            self::$VERIFICATION_STATUS_GENERAL_ERROR => esc_html__('Verifying email was not successfull.', 'robera-ab-test'),
            self::$VERIFICATION_STATUS_EXPIRED => esc_html__('Your verification code is expired.', 'robera-ab-test'),
            self::$VERIFICATION_STATUS_NOT_VALID => esc_html__('Your verification code is not valid.', 'robera-ab-test'),
            self::$PAYMENT_STATUS_ERROR => esc_html__('Payment was not successfull.', 'robera-ab-test'),
            self::$PAYMENT_STATUS_SUCCESS => esc_html__('Payment succeeded. Reference ID is %s.', 'robera-ab-test'),
        );
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $success = in_array($status, array(
            self::$REGISTRATION_STATUS_SUCCESS, self::$VERIFICATION_STATUS_SUCCESS, self::$PAYMENT_STATUS_SUCCESS
        ));
        $refID = isset($_GET['refid']) ? $_GET['refid'] : '';

        $message = sprintf(@$messages[$status] ?: '', $refID);

        PinkOrBlueClient::updateExpirationDate();
        PinkOrBlueClient::getNextPlan();
        require_once('templates/account-page.php');
    }

    public function settingsPage()
    {
        $all_metas = array_values($this->getPostTypesMetaKeys(array('post', 'page', 'product')));
        $all_metas = array_diff($all_metas, RoberaABPlugin::$DEFAULT_SUPPORTED_META_KEYS,
            RoberaABPlugin::$DEFAULT_BLACKLIST_META_KEYS);
        $current_metas = explode(",", get_option(RoberaABPlugin::$META_KEYS_OPTION_NAME));
        require_once('templates/settings-page.php');
    }

    public function createVariant()
    {
        try {
            $page_id = $_POST['new-variant-page-id'];
            $is_duplicated = ($_POST['from'] == "duplicated");
            $name = $_POST['variant-name'];
            if ($is_duplicated) {
                $new_post_id = PostUtils::createDuplicatedPost($page_id, $name);
            } else {
                $new_post_id = PostUtils::createEmptyPost($page_id, $name);
            }
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
        wp_die(json_encode(array(
            'new_post_id' => $new_post_id
        )));
    }
}
