<?php

namespace Robera\AB;

if (function_exists('confirmationModal')) {
    return;
}

function confirmationModal($modal_class, $header_text, $content_text, $action_url, $action_class, $action_text)
{
    ?>
    <div class="ui tiny modal <?php echo $modal_class ?>">
        <div class="header"><?php echo $header_text ?></div>
        <div class="content">
            <p><?php echo $content_text ?></p>
            <p><?php esc_html_e("Are you sure to continue?", "robera-ab-test")?></p>
        </div>
        <div class="actions">
            <div class="ui cancel button"><?php esc_html_e("Cancel", "robera-ab-test")?></div>
            <form class="modal-form <?php echo $modal_class ?>" action="<?php echo $action_url ?>" method="post" style="display: inline">
                <input type="hidden" name="test_id" value="" >
                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp_rest') ?>">
                <input type="submit" class="ui approve <?php echo $action_class ?> button" value="<?php echo $action_text ?>" />
            </form>
        </div>
    </div>
    <?php
}

function confirmStartModel($action_url)
{
    $modal_text = wp_kses(sprintf(__('Starting the A/B test test will effect how users see the target page.
            <br />
            Your test will be running for up to %1$s days and you can stop it whenever you want.
            ', "robera-ab-test"), AB_TEST_MAXIMUM_DAYS), array('br' => array()));
    if (!get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
        $plan_num_days = get_option(RoberaABPlugin::$NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME);
        $plan_name = get_option(RoberaABPlugin::$NEXT_PLAN_NAME_OPTION_NAME);
        $modal_text = wp_kses(sprintf(__('After starting this test, your %s plan will start and work for %s days.', 'robera-ab-test'), $plan_name, $plan_num_days), array('br' => array())) . $modal_text;
    }
    confirmationModal(
        "start-test",
        esc_html__("Confirm Starting Test", "robera-ab-test"),
        $modal_text,
        $action_url,
        "primary start-button-modal",
        esc_html__("Start Test", "robera-ab-test")
    );
}

function addFooterEmailSection()
{
    ?>
     <div class="ui center aligned container" style="margin-top: 4em;"> 
            <div class="ui celled list">
                <hr style="height:1px; width: 50%;  background-color:#4183c4;">
                <p> <?php esc_html_e("Contact us whenever you need:", "robera-ab-test"); ?> <a class="header" href="mailto:contact@pinkorblue.info?subject=contact from pink or blue plugin page" style="font-size: 15px; font-weight: bold" >
                <i class="envelope outline icon"></i>contact@pinkorblue.info
                </a></p>
            </div>
        </div>
    <?php
}
