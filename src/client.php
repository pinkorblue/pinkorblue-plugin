<?php

namespace Robera\AB;

use \Firebase\JWT\JWT;

if (class_exists('PinkOrBlueClient')) {
    return;
}

class PinkOrBlueClient
{
    public static $CLIENT_SECRET_OPTION_NAME = AB_PLUGIN_PREFIX . "_client_secret";
    public static $CLIENT_SECRET_SENT_SUCCESS_OPTION_NAME = AB_PLUGIN_PREFIX . "_client_secret_success";

    public static $TIMEOUT = 5;

    public static function sendSiteInfo()
    {
        $client_secret = get_option(self::$CLIENT_SECRET_OPTION_NAME);

        $data = array(
            'site_name' => self::getSiteName(),
            'client_secret' => $client_secret,
        );
        $response = wp_remote_post(
            self::getAuthUrl(),
            array(
                'timeout' => self::$TIMEOUT,
                'headers' => self::getHeader(false),
                'body' => json_encode($data)
            )
        );

        if (is_wp_error($response)) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending site info");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
            return false;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($status_code != 201 && $body != '{"error":"Client is duplicated."}') {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending site info");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $body);
            return false;
        }
        $res = json_decode(wp_remote_retrieve_body($response), true);
        add_option(self::$CLIENT_SECRET_SENT_SUCCESS_OPTION_NAME, true);
        self::updateNextPlanOptions($res['next_plan']['name'], $res['next_plan']['price_toman'], $res['next_plan']['number_of_days']);
        return true;
    }

    private static function updateNextPlanOptions($name, $price_toman, $number_of_days)
    {
        update_option(RoberaABPlugin::$NEXT_PLAN_NAME_OPTION_NAME, $name);
        update_option(RoberaABPlugin::$NEXT_PLAN_PRICE_TOMAN_OPTION_NAME, $price_toman);
        update_option(RoberaABPlugin::$NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME, $number_of_days);
    }

    public static function getRegisterTargetUrl()
    {
        return sprintf('%s/auth/%s/client/register_email/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getVerifyTargetUrl()
    {
        return sprintf('%s/auth/%s/client/verify/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getPaymentUrl()
    {
        return sprintf('%s/payment/%s/plan_payment?site_name=%s', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION, self::getSiteName());
    }

    public static function getNextPlanUrl()
    {
        return sprintf('%s/auth/%s/client/next_plan/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getSiteStatsUrl()
    {
        return sprintf('%s/core/%s/interaction/site_stats/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getInteractionUrl()
    {
        return sprintf('%s/core/%s/interaction/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getInteractionEditUrl($interaction_id)
    {
        return sprintf('%s/core/%s/interaction/%s/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION, $interaction_id);
    }

    public static function getAuthUrl()
    {
        return sprintf('%s/auth/%s/client/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getStartTrialUrl()
    {
        return sprintf('%s/auth/%s/client/set_start/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getExpirationDateUrl()
    {
        return sprintf('%s/auth/%s/client/expiration_date/', ROBERA_SERVER_ADDRESS, ROBERA_SERVER_API_VERSION);
    }

    public static function getSiteName()
    {
        return site_url() . get_option(RoberaABPlugin::$INSTALLATION_RANDOM_VALUE_OPTION_NAME);
    }

    public static function getInteractionAuth($interaction_id)
    {
        $key = get_option(self::$CLIENT_SECRET_OPTION_NAME);
        $payload = array(
            'interaction_id' => $interaction_id,
            'site_name' => self::getSiteName()
        );

        $jwt = JWT::encode($payload, $key);
        return sprintf('JWT %s', $jwt);
    }

    public static function getSecretAuth()
    {
        return sprintf('SECRET %s#%s', self::getSiteName(), get_option(self::$CLIENT_SECRET_OPTION_NAME));
    }

    public static function getHeader($authenticate = true, $interaction_id = null)
    {
        global $wp_version;
        $user_agent = 'WordPress/' . $wp_version . ' - ' .
                            AB_PLUGIN_PREFIX . '/' . ROBERA_AB_PLUGIN_VERSION;

        if (!$authenticate) {
            $auth = "";
        } else if ($interaction_id) {
            $auth = self::getInteractionAuth($interaction_id);
        } else {
            $auth = self::getSecretAuth();
        }

        return array(
                'Content-Type' => 'application/json',
                'User-Agent' =>  $user_agent,
                'Accept-Encoding' => 'gzip',
                'Authorization' => $auth
        );
    }

    public static function updateExpirationDate()
    {
        if (get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
            $response = wp_remote_post(
                self::getExpirationDateUrl(),
                array(
                    'timeout' => self::$TIMEOUT,
                    'headers' => self::getHeader(),
                    'body' => json_encode(new \stdClass)
                )
            );

            if (is_wp_error($response)) {
                error_log("[" . AB_PLUGIN_PREFIX . "] --- getting expiration date.");
                error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
                return true;
            }
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code != 200) {
                $error_body = wp_remote_retrieve_body($response);
                error_log("[" . AB_PLUGIN_PREFIX . "] --- getting expiration date.");
                error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $error_body);
                return true;
            }
            $res = json_decode(wp_remote_retrieve_body($response), true);
            $expiration_date = $res["expiration_date"];
            if ($expiration_date) {
                update_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME, $expiration_date);
            }
        }
    }

    public static function getNextPlan()
    {
        if (!get_option(self::$CLIENT_SECRET_SENT_SUCCESS_OPTION_NAME)) {
            if (!self::sendSiteInfo()) {
                return false;
            }
        }
        $response = wp_remote_get(
            self::getNextPlanUrl(),
            array(
                'timeout' => self::$TIMEOUT,
                'headers' => self::getHeader(),
            )
        );

        if (is_wp_error($response)) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- get next plan.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
            return true;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[" . AB_PLUGIN_PREFIX . "] --- get next plan.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $error_body);
            return true;
        }
        $res = json_decode(wp_remote_retrieve_body($response), true);
        self::updateNextPlanOptions($res['name'], $res['price_toman'], $res['number_of_days']);
        return false;
    }

    public static function sendStartTrial()
    {
        if (!get_option(self::$CLIENT_SECRET_SENT_SUCCESS_OPTION_NAME)) {
            if (!self::sendSiteInfo()) {
                return false;
            }
        }
        $response = wp_remote_post(
            self::getStartTrialUrl(),
            array(
                'timeout' => self::$TIMEOUT,
                'headers' => self::getHeader(),
                'body' => json_encode(new \stdClass)
            )
        );

        if (is_wp_error($response)) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending start trial.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
            return true;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending start trial.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $error_body);
            return true;
        }
        $expiration_date = json_decode(wp_remote_retrieve_body($response), true)["expiration_date"];
        update_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME, $expiration_date);
        return false;
    }

    public static function handleRemoteRequest($request)
    {
        $data = json_decode($request->data, true);
        $data['site_name'] = self::getSiteName();
        $data['interaction_type'] = $request->type;
        $data['interaction_id'] = $request->iid;

        $response = wp_remote_post(
            self::getInteractionUrl(),
            array(
                'timeout' => self::$TIMEOUT,
                'headers' => self::getHeader(true, $data['interaction_id']),
                'body' => json_encode($data)
            )
        );
        if (is_wp_error($response)) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending interaction.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
            return true;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($status_code != 201 && $body != '{"error":"Interaction is duplicated."}') {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending interaction.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $body);
            return true;
        }
        return false;
    }

    public static function handleRegisterRequest($email)
    {
        $data = array(
            'email' => $email,
            'site_name' => self::getSiteName()
        );

        $response = wp_remote_post(
            self::getRegisterTargetUrl(),
            array(
                'timeout' => self::$TIMEOUT,
                'headers' => self::getHeader(true),
                'body' => json_encode($data)
            )
        );
        if (is_wp_error($response)) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- register email.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
            return AdminPage::$REGISTRATION_STATUS_GENERAL_ERROR;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status_code != 201) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- sending email to register failed.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . json_encode($body));
            return $body["status"];
        }
        return AdminPage::$REGISTRATION_STATUS_SUCCESS;
    }

    public static function handleVerifyRequest($email, $verification_code)
    {
        $data = array(
            'email' => $email,
            'verification_code' => $verification_code,
            'site_name' => self::getSiteName()
        );

        $response = wp_remote_post(
            self::getVerifyTargetUrl(),
            array(
                'timeout' => self::$TIMEOUT,
                'headers' => self::getHeader(true),
                'body' => json_encode($data)
            )
        );
        if (is_wp_error($response)) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- verify code.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . $response->get_error_message());
            return AdminPage::$VERIFICATION_STATUS_GENERAL_ERROR;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status_code != 200) {
            error_log("[" . AB_PLUGIN_PREFIX . "] --- verification code failed.");
            error_log("[" . AB_PLUGIN_PREFIX . "] --- " . json_encode($body));
            return $body["status"];
        }
        return AdminPage::$VERIFICATION_STATUS_SUCCESS;
    }
}
