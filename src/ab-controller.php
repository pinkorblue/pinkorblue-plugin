<?php
namespace Robera\AB;

if (class_exists('ABController')) {
    return;
}

class ABController
{
    private static $USER_ID_COOKIE_NAME_PREFIX = AB_PLUGIN_PREFIX . '_user_id';
    private static $NONCE_PREFIX = 'stay-tracker-';

    private static $REQUEST_LOCK_KEY = 'remote_request_process_lock';

    public function __construct()
    {
        try {
            $this->client = (new \Raven_Client(ROBERA_SENTRY_URL))->install();
        } catch (\Exception $ex) {
            error_log($ex->getMessage());
        }

        try {
            $this->running_tests = ABTest::where('state', AB_TEST_STATE_RUNNING)->select('id', 'post_id', 'kpi', 'type', 'target_page_id')->get();
            $this->running_tests_map = array();
            foreach ($this->running_tests as $test) {
                $this->running_tests_map[$test->post_id] = $test;
            }

            $this->installation_random_value = get_option(RoberaABPlugin::$INSTALLATION_RANDOM_VALUE_OPTION_NAME);

            $this->has_woocommerce = in_array(
                'woocommerce/woocommerce.php',
                apply_filters('active_plugins', get_option('active_plugins'))
            );

            $this->user_id_cookie = ABController::$USER_ID_COOKIE_NAME_PREFIX . '_' . $this->installation_random_value;

            add_action('init', array(&$this, 'setCookies'), 10);

            add_filter('the_posts', array(&$this, 'editThePosts'), 100);
            add_action('the_post', array(&$this, 'editThePost'), 100);
            add_filter('get_post_metadata', array(&$this, 'editMetadata'), 10, 4);

            add_action('wp', array(&$this, 'countViews'), 10);

            add_filter('document_title_parts', array(&$this, 'editHTMLTitle'), 100, 1);

            add_action('wp_enqueue_scripts', array(&$this, 'addABTestScripts'));
            add_action('rest_api_init', array(&$this, 'restApisRegisteration'));

            if ($this->has_woocommerce) {
                add_action('woocommerce_thankyou', array(&$this, 'handleOrderComplete'), 10, 1);
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    private function fixAltPost($alt, $post)
    {
        if (!$post) {
            return;
        }
        $alt->ID = $post->ID;
        $alt->post_name = $post->post_name;
        $alt->comment_count = $post->comment_count;
        $alt->comment_status = $post->comment_status;
        $alt->post_status = "publish";
        $alt->post_type = $post->post_type;
        $alt->guid = $post->guid;
    }

    public function editThePosts($posts)
    {
        try {
            foreach (array_values($posts) as $i => $post) {
                if (array_key_exists($post->ID, $this->running_tests_map)) {
                    $test = $this->running_tests_map[$post->ID];
                    $test_id = $test->id;
                    $alt_post_id = $_COOKIE[self::getTestCookieName($test_id)];
                    if ($post->ID != $alt_post_id) {
                        $alt = get_post($alt_post_id);
                        if ($alt) {
                            $this->fixAltPost($alt, $post);
                            $posts[$i] = $alt;
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
        return $posts;
    }


    public function editThePost($post)
    {
        try {
            // first: Handle visit counts of item
            global $current_test;
            if (array_key_exists($post->ID, $this->running_tests_map)) {
                $test = $this->running_tests_map[$post->ID];

                $test_id = $test->id;
                $alt_post_id = $_COOKIE[self::getTestCookieName($test_id)];

                $variant = Variant::where('ab_test_id', $test_id)->
                                where('post_id', $alt_post_id)->
                                first();
                if ((((!$current_test || $current_test->id != $test_id) && $test->kpi == VISIT_PRODUCT_KPI) || in_array($test->kpi, [REVENUE_KPI, PRODUCT_BUY_KPI])) && $variant) {
                    $user_visit = UserVisit::where('user_id', $_COOKIE[$this->user_id_cookie])->
                                    where('variant_id', $variant->id)->first();
                    if (!$user_visit) {
                        $remote_request = new RemoteRequest;
                        $remote_request->populate(
                            'user_visit',
                            json_encode(array(
                                'test_kpi' => $test->kpi,
                                'test_id' => $test->id,
                                'variant_post_id' => $variant->post_id,
                                'user_id' => $_COOKIE[$this->user_id_cookie],
                                'interaction_time' => current_time(DATE_ISO8601),
                                'interaction_value' => 1,
                            ))
                        );

                        $user_visit = new UserVisit();
                        $user_visit->populate($_COOKIE[$this->user_id_cookie], $variant->id);
                    }
                }

                // then: Handle changing post fields
                if ($post->ID != $alt_post_id) {
                    global $post;
                    $alt = get_post($alt_post_id);
                    if ($alt) {
                        $this->fixAltPost($alt, $post);
                        $post = $alt;
                        return $alt;
                    }
                }
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
        return $post;
    }

    public function editMetadata($value, $object_id, $meta_key, $single)
    {
        try {
            $setting_metas = explode(",", get_option(RoberaABPlugin::$META_KEYS_OPTION_NAME));

            $supported_keys = array_merge($setting_metas, RoberaABPlugin::$DEFAULT_SUPPORTED_META_KEYS);

            if ($meta_key == '') { // It wants all meta_keys for a post
                if (array_key_exists($object_id, $this->running_tests_map)) {
                    $test = $this->running_tests_map[$object_id];
                    $test_id = $test->id;
                    $alt_post_id = $_COOKIE[self::getTestCookieName($test_id)];
                    if ($alt_post_id !== $object_id) {
                        remove_filter(current_filter(), array(&$this, 'editMetadata'), 10);
                        $value = get_post_meta($object_id, $meta_key, $single);
                        add_filter(current_filter(), array(&$this, 'editMetadata'), 10, 4);
                        foreach ($supported_keys as $key) {
                            if (array_key_exists($key, $value)) {
                                $value[$key] = get_post_meta($alt_post_id, $key);
                            }
                        }
                    }
                }
            } else if (in_array($meta_key, $supported_keys)) {
                if (array_key_exists($object_id, $this->running_tests_map)) {
                    $test = $this->running_tests_map[$object_id];
                    $test_id = $test->id;
                    $alt_post_id = $_COOKIE[self::getTestCookieName($test_id)];
                    if ($alt_post_id !== $object_id) {
                        remove_filter(current_filter(), array(&$this, 'editMetadata'), 10);
                        $value = get_post_meta($alt_post_id, $meta_key, $single);
                        add_filter(current_filter(), array(&$this, 'editMetadata'), 10, 4);
                    }
                }
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
        return $value;
    }

    public function getTestCookieName($test_name, $suffix = "")
    {
        return AB_PLUGIN_PREFIX . "_test_" . $this->installation_random_value . '_' . $test_name . $suffix;
    }

    private function getCurrentLocation()
    {
        try {
            if (isset($_SERVER['HTTPS']) &&
                ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
                isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
                $protocol = 'https://';
            } else {
                $protocol = 'http://';
            }
            return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    private function getPostIDByURL($url)
    {
        try {
            if (strpos($url, get_site_url()) === false) {
                return null;
            }
            $path = str_replace(get_site_url() . "/", "", $url);
            if ($path == "") {
                return get_option('page_on_front');
            }

            $post_id = url_to_postid($path);
            if ($post_id) {
                return $post_id;
            }

            $page = get_page_by_path($path);
            if ($page) {
                return $page->ID;
            }
            return null;
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    public function restApisRegisteration()
    {
        try {
            register_rest_route(AB_PLUGIN_PREFIX.'/v1', '/ab-test/add-time', array(
                'methods' => 'POST',
                'permission_callback' => function ($request) {
                    $data = $request->get_json_params();
                    if (!array_key_exists("nonce", $data) || !array_key_exists("test_id", $data)) {
                        return false;
                    }
                    return wp_verify_nonce($data["nonce"], ABController::$NONCE_PREFIX . $data["test_id"]);
                },
                'callback' => array($this, 'addStayTime')
            ));
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    public function addABTestScripts()
    {
        try {
            global $current_test;

            wp_enqueue_script(AB_PLUGIN_PREFIX . '_check', plugins_url('static/check.js', __FILE__));

            wp_register_script(AB_PLUGIN_PREFIX . '_set_cookies', plugins_url('static/set-cookies.js', __FILE__));
            wp_localize_script(AB_PLUGIN_PREFIX . '_set_cookies', 'cookie_info', array(
                'cookies' => $this->should_set_cookies
            ));
            wp_enqueue_script(AB_PLUGIN_PREFIX . '_set_cookies');
            
            if ($current_test && $current_test->kpi == STAY_KPI) {
                wp_enqueue_script('timeme', plugins_url('static/timeme.min.js', __FILE__));
                wp_register_script(AB_PLUGIN_PREFIX . '_stay_tracker', plugins_url('static/stay-tracker.js', __FILE__));
                $interaction_id = RemoteRequest::generateInteractionID();

                wp_localize_script(AB_PLUGIN_PREFIX . '_stay_tracker', 'test_info', array(
                    'target_url_create' => PinkOrBlueClient::getInteractionUrl(),
                    'target_url_edit' => PinkOrBlueClient::getInteractionEditUrl($interaction_id),
                    'user_id' => $_COOKIE[$this->user_id_cookie],
                    'site_name' => PinkOrBlueClient::getSiteName(),
                    'test_id' => $current_test->id,
                    'test_kpi' => $current_test->kpi,
                    'interaction_id' => $interaction_id,
                    'authorize' => PinkOrBlueClient::getInteractionAuth($interaction_id),
                    'variant_post_id' => $_COOKIE[self::getTestCookieName($current_test->id)]
                ));
                wp_enqueue_script(AB_PLUGIN_PREFIX . '_stay_tracker');
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    public function countViews()
    {
        try {
            global $wp_query;
            global $wp;

            if ($wp_query->is_single || $wp_query->is_page) {
                $post_id = $wp_query->post->ID;
            } else if ($wp_query->queried_object && $wp_query->queried_object->ID) {
                $post_id = $wp_query->queried_object->ID;
            } else if ($wp->request) {
                $post_id = $this->getPostIDByURL(home_url($wp->request));
            } else {
                $post_id = $this->getPostIDByURL($this->getCurrentLocation());
            }

            global $current_test;
            if (array_key_exists($post_id, $this->running_tests_map)) {
                $current_test = $this->running_tests_map[$post_id];
            }

            $referer_id = $this->getPostIDByURL(wp_get_referer());

            $referer_test = null;
            if (array_key_exists($referer_id, $this->running_tests_map)) {
                $referer_test = $this->running_tests_map[$referer_id];
            }

            if ($referer_test && $referer_test->kpi == VISIT_KPI && $post_id == $referer_test->target_page_id) {
                $variant = Variant::where('ab_test_id', $referer_test->id)->
                            where('post_id', $_COOKIE[self::getTestCookieName($referer_test->id)])->
                            first();
                if ($variant) {
                    $remote_request = new RemoteRequest;
                    $remote_request->populate(
                        'target_visit',
                        json_encode(array(
                            'test_kpi' => $referer_test->kpi,
                            'test_id' => $referer_test->id,
                            'variant_post_id' => $variant->post_id,
                            'user_id' => $_COOKIE[$this->user_id_cookie],
                            'interaction_time' => current_time(DATE_ISO8601),
                            'interaction_value' => 1,
                        ))
                    );
                }
            }

            if (!$current_test || !in_array($current_test->kpi, [VISIT_KPI, VISIT_PRODUCT_KPI])) {
                return;
            }

            $variant = Variant::where('ab_test_id', $current_test->id)->
                            where('post_id', $_COOKIE[self::getTestCookieName($current_test->id)])->
                            first();
            if (!$variant) {
                return;
            }

            if ($current_test->kpi == VISIT_KPI) {
                $remote_request = new RemoteRequest;
                $remote_request->populate(
                    'visit',
                    json_encode(array(
                        'test_kpi' => $current_test->kpi,
                        'test_id' => $current_test->id,
                        'variant_post_id' => $variant->post_id,
                        'user_id' => $_COOKIE[$this->user_id_cookie],
                        'interaction_time' => current_time(DATE_ISO8601),
                        'interaction_value' => 1,
                    ))
                );
            } elseif ($current_test->kpi == VISIT_PRODUCT_KPI) {
                $user_visit = UserVisit::where('user_id', $_COOKIE[$this->user_id_cookie])->
                                where('variant_id', $variant->id)->first();
                if ($user_visit && !$user_visit->did_interact) {
                    $user_visit->did_interact = true;
                    $user_visit->save();

                    $remote_request = new RemoteRequest;
                    $remote_request->populate(
                        'visit_product',
                        json_encode(array(
                            'test_kpi' => $current_test->kpi,
                            'test_id' => $current_test->id,
                            'variant_post_id' => $variant->post_id,
                            'user_id' => $_COOKIE[$this->user_id_cookie],
                            'interaction_time' => current_time(DATE_ISO8601),
                            'interaction_value' => 1,
                        ))
                    );
                }
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    public function setCookies()
    {
        try {
            $should_set_cookies = array();
            $cookie_name = $this->user_id_cookie;
            if (!isset($_COOKIE[$cookie_name])) {
                $cookie_value = bin2hex(random_bytes(12));
                // setcookie($cookie_name, $cookie_value, 0, "/");
                $should_set_cookies[] = array(
                    'name' => $cookie_name,
                    'value' => $cookie_value,
                    'exp' => 0,
                    'path'=> '/'
                );
                $_COOKIE[$cookie_name] = $cookie_value;
            }

            $running_tests = ABTest::where('state', AB_TEST_STATE_RUNNING)->select('id')->get();

            $exp_time = 3600 * 24 * (AB_TEST_MAXIMUM_DAYS + 1);
            foreach ($running_tests as $test) {
                $cookie_name = self::getTestCookieName($test->id);
                if (!isset($_COOKIE[$cookie_name])) {
                    $variants = Variant::where('ab_test_id', $test->id)->select('post_id', 'percentage')->get();

                    $number_of_variants = count($variants);
                    $rand = random_int(0, 99);

                    // TODO -> fix here if number of variants greater than 2
                    if ($rand < $variants[0]["percentage"]) {
                        $cookie_value = $variants[0]["post_id"];
                    } else {
                        $cookie_value = $variants[1]["post_id"];
                    }

                    // setcookie($cookie_name, $cookie_value, time() + $exp_time, "/");
                    $should_set_cookies[] = array(
                        'name' => $cookie_name,
                        'value' => $cookie_value,
                        'exp' => time() + $exp_time,
                        'path'=> '/'
                    );
                    $_COOKIE[$cookie_name] = $cookie_value;
                }
            }
            $this->should_set_cookies = $should_set_cookies;
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    private function getVariantPost($test_id)
    {
        try {
            $cookie_name = $this->getTestCookieName($test_id);
            $variant_post_id = $_COOKIE[$cookie_name];
            return get_post($variant_post_id);
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    public function editHTMLTitle($title)
    {
        try {
            global $current_test;

            if (!$current_test) {
                return $title;
            }
            $variant_post = $this->getVariantPost($current_test->id);
            if ($variant_post && array_key_exists('title', $title)) {
                $title['title'] = $variant_post->post_title;
            }
            return $title;
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }

    public function handleOrderComplete($order_id)
    {
        try {
            if (! $order_id) {
                return;
            }
            $order = wc_get_order($order_id);
            $total = $order->get_total();

            $running_tests = ABTest::where('state', AB_TEST_STATE_RUNNING)->
                                whereIn('kpi', [REVENUE_KPI, PRODUCT_BUY_KPI])->select('id', 'kpi', 'target_page_id')->get();
            foreach ($running_tests as $test) {
                $cookie_name = self::getTestCookieName($test->id);
                $variant_post_id = $_COOKIE[$cookie_name];
                $variant = Variant::where('ab_test_id', $test->id)->
                                where('post_id', $variant_post_id)->
                                first();
                if (!$variant) {
                    continue;
                }
                $user_visit = UserVisit::where('user_id', $_COOKIE[$this->user_id_cookie])->
                                where('variant_id', $variant->id)->first();
                if ($user_visit) {
                    if ($test->kpi == REVENUE_KPI) {
                        $remote_request = new RemoteRequest;
                        $remote_request->populate(
                            'revenue',
                            json_encode(array(
                                'test_id' => $test->id,
                                'test_kpi' => $test->kpi,
                                'variant_post_id' => $variant->post_id,
                                'user_id' => $_COOKIE[$this->user_id_cookie],
                                'interaction_time' => current_time(DATE_ISO8601),
                                'interaction_value' => $total,
                            ))
                        );
                    } elseif ($test->kpi == PRODUCT_BUY_KPI) {
                        $items = $order->get_items();
                        foreach ($items as $item_id => $item_data) {
                            $product = $item_data->get_product();
                            if ($test->target_page_id == $product->get_id()) {
                                $remote_request = new RemoteRequest;
                                $remote_request->populate(
                                    'item_buy',
                                    json_encode(array(
                                        'test_id' => $test->id,
                                        'test_kpi' => $test->kpi,
                                        'variant_post_id' => $variant->post_id,
                                        'user_id' => $_COOKIE[$this->user_id_cookie],
                                        'interaction_time' => current_time(DATE_ISO8601),
                                        'interaction_value' => $item_data->get_quantity(),
                                    ))
                                );
                            }
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            $event_id = $this->client->captureException($ex);
        }
    }
}
