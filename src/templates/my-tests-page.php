<?php
namespace Robera\AB;

require_once('common.php');

RoberaABPlugin::checkTests();

?>
<div class="semantic">
    <?php
    function showTestCards($combo_tests, $empty_message)
    {
        if (count($combo_tests) == 0) {
            ?>
            <h4 style="margin: 1rem 0;">
                <?php echo $empty_message ?>
            </h4>
            <?php
        }
        $kpi_title_mapping = array(
            REVENUE_KPI => esc_html__('Average revenue', 'robera-ab-test'),
            VISIT_KPI   => esc_html__('Percentage visits of target', 'robera-ab-test'),
            STAY_KPI    => esc_html__('Average stay on test object', 'robera-ab-test'),
            PRODUCT_BUY_KPI => esc_html__('Average number of items bought', 'robera-ab-test'),
            VISIT_PRODUCT_KPI => esc_html__('Percentage visits of target product', 'robera-ab-test')
        );
        foreach ($combo_tests as $key => $test) {
            $edit_link = esc_url(add_query_arg("test", $test[0]["id"], "admin.php?page=robera-edit-test"));
            $delete_link = "/?rest_route=/robera/v1/ab-test/delete/".$test[0]["id"]."&_method=DELETE";

            if ($test[0]["state"] == AB_TEST_STATE_RUNNING && $test[0]["started_at"]) {
                $started_date = new \DateTime();
                $started_date->setTimestamp(mysql2date('U', $test[0]["started_at"]));
                $end_date = $started_date->add(new \DateInterval(sprintf("P%dD", AB_TEST_MAXIMUM_DAYS)));
                $expiration_date_str = get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME);
                if ($expiration_date_str) {
                    $expiration_date = new \DateTime();
                    $expiration_date->setTimestamp(mysql2date('U', $expiration_date_str));

                    if ($expiration_date < $end_date) {
                        $human_time_remaining = human_time_diff(current_time('timestamp', true), $expiration_date->getTimestamp());
                    } else {
                        $human_time_remaining = human_time_diff(current_time('timestamp', true), $end_date->getTimestamp());
                    }
                } else {
                    $human_time_remaining = human_time_diff(current_time('timestamp', true), $end_date->getTimestamp());
                }
            }
            ?>
            <div class="five wide column">
                <div class="ui card" style="overflow-x: auto;">
                    <div class="content">
                        <div class="header">
                            <?php echo $test[0]["name"] ?>
                            <?php if ($test[0]["type"] == PAGE_TEST) : ?>
                                <div class="test-type" data-content="<?php esc_html_e('Page test', 'robera-ab-test') ?>"><i class="file alternate icon"></i></div>
                            <?php else : ?>
                                <div class="test-type" data-content="<?php esc_html_e('Product test', 'robera-ab-test') ?>"><i class="shopping bag icon"></i></div>
                            <?php endif ?>
                        </div>
                        <?php esc_html_e("Test object:", "robera-ab-test")?> <a href="<?php echo get_permalink($test[0]["post_id"]);?>"> <?php echo get_the_title($test[0]["post_id"]);
                        ?> </a>
                    </div>
                    <div class="content">
                        <h4><?php printf(esc_html__('Description: %1$s', 'robera-ab-test'), $test[0]["description"]) ?></h4>
                        <?php
                        if (isset($human_time_remaining)) :
                            printf(esc_html__("This test will be running for %s.", 'robera-ab-test'), $human_time_remaining);
                        endif
                        ?>
                        <div class="ui small feed">
                            <div class="event">
                                <div class="content">
                                    <div class="summary">
                                        <?php
                                        printf(esc_html__('Conversion goal: %1$s', 'robera-ab-test'), $kpi_title_mapping[$test[0]["kpi"]]);
                                        if ($test[0]["kpi"] == VISIT_KPI || $test[0]["kpi"] == PRODUCT_BUY_KPI) {
                                            echo "<br>";
                                            printf(esc_html__("Target: %s", 'robera-ab-test'), get_the_title($test[0]["target_page_id"]));
                                        }
                                        ?>

                                    </div>
                                </div>
                            </div>
                            <table class="ui celled table test-<?php echo $test[0]['id']?>">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Variant', 'robera-ab-test')?></th>
                                        <th><?php esc_html_e('% of users', 'robera-ab-test')?></th>
                                        <?php if ($test[0]["state"] !== AB_TEST_STATE_NOT_STARTED) : ?>
                                            <th><?php esc_html_e('Variant visit', 'robera-ab-test')?></th>
                                            <th><?php echo $kpi_title_mapping[$test[0]['kpi']] ?></th>
                                        <?php endif ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($test[1] as $key => $variant) : ?>
                                    <tr>
                                        <td data-label="Variant"><?php echo $variant["name"] ?></td>
                                        <td data-label="%"><?php echo $variant["percentage"] ?></td>
                                        <?php if ($test[0]["state"] !== AB_TEST_STATE_NOT_STARTED) : ?>
                                            <td data-label="Variant Visit" class="variant-<?php echo $variant['post_id']?>-visits">0</td>
                                            <td data-label="<?php echo $kpi_title_mapping[$test[0]['kpi']] ?>" class="variant-<?php echo $variant['post_id']?>-value">NA</td>
                                        <?php endif ?>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php if ($test[0]["state"] == AB_TEST_STATE_NOT_STARTED) : ?>
                    <div class="extra content" align="center">

                        <?php if ($test[0]["can_start"]["can"]) : ?>
                            <div class="ui primary button start-test-button" data-test-id="<?php echo $test[0]["id"] ?>"><?php esc_html_e("Start", 'robera-ab-test')?>
                            </div>
                        <?php else : ?>
                            <div class="start-test-button-parent" style="display: inline" data-tooltip="<?php echo $test[0]["can_start"]["message"] ?>" data-position="top left">
                                <div class="ui disabled primary button start-test-button" data-test-id="<?php echo $test[0]["id"] ?>"><?php esc_html_e("Start", 'robera-ab-test') ?></div>
                            </div>
                        <?php endif ?>
                        <button type="button" class="ui button" onclick="window.location.href='<?php echo $edit_link ?>'"><?php esc_html_e("Edit", 'robera-ab-test')?></button>
                        <div class="ui red button delete-test-button" data-test-id='<?php echo $test[0]["id"] ?>' data-target-url='<?php echo $delete_link ?>'><?php esc_html_e("Delete", 'robera-ab-test')?></div>
                    </div>
                <?php elseif ($test[0]["state"] == AB_TEST_STATE_RUNNING) : ?>
                    <div class="extra content" align="center">
                        <div class="ui button stop-test-button" data-test-id='<?php echo $test[0]["id"]?>'><?php esc_html_e("Stop", 'robera-ab-test')?></div>
                    </div>
                <?php endif ?>
                </div>
            </div>
            <?php
        }
    }
    ?>

    <div class="ui container" style="margin-top: 1rem">
        <?php if (!count($unbegun_tests) && !count($running_tests) && !count($finished_tests)) : ?>
            <div class="ui segment">
                <h2 class="ui header">
                  <img class="ui image" src="<?php echo plugins_url('../static/pinkORblue.svg', __FILE__);?>">
                    <?php esc_html_e("Pink or Blue A/B Test", "robera-ab-test")?>
                </h2>
                <div class="content">
                        <?php
                            $current_user =  wp_get_current_user();
                            printf(esc_html__('Hi %1$s!', 'robera-ab-test'), $current_user->user_login);
                        ?>
                        <br>
                    <?php esc_html_e('This is Pink or Blue A/B test plugin!', 'robera-ab-test') ?>
                    <br>
                    <?php
                    $plan_num_days = get_option(RoberaABPlugin::$NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME);
                    printf(esc_html__('After starting your first test you will have %s days trial to use the plugin.', 'robera-ab-test'), $plan_num_days);
                    ?>
                    <br>
                    <?php esc_html_e('To add new A/B test click on Add New Test button bellow.', 'robera-ab-test') ?>
                    <br>
                    <?php esc_html_e("Hope it can help you discover your users' preferences.", 'robera-ab-test') ?>
                </div>
            </div>
        <?php endif ?>
        <?php if (RoberaABPlugin::isExpired()) : ?>
        <div class="ui segment">
            <?php esc_html_e("Your trial is finished if you want to continue using Pink or Blue you can use My Acount page.", "robera-ab-test") ?>
        </div>
        <?php else : ?>
             <?php
                if ($error) {
                    echo '<div class="ui negative message">';
                        echo '<i class="close icon"></i>';
                        echo '<div class="header" id="page-error-header">';
                        echo $error["header"];
                        echo '</div>';
                        echo '<p id="page-error-text">'.$error['text'].'</p>';
                    echo '</div>';
                }
                ?>
        <button class="ui primary button" onclick="window.location.href='admin.php?page=robera-add-test'">
            <?php esc_html_e('Add New Test', 'robera-ab-test') ?>
        </button>
        <div class="ui segment">
            <h1>
                <?php esc_html_e("Unbegun Tests", "robera-ab-test") ?>
            </h1>
            <div class="ui grid">
            <?php showTestCards($unbegun_tests, esc_html__("You don't have any unbegun tests.", 'robera-ab-test')) ?>
            </div>
        </div>

        <div class="ui segment <?php echo ($running_tests ? 'loading' : '') ?>">
            <h1>
                <?php esc_html_e("Running Tests", "robera-ab-test") ?>
            </h1>
            <div class="ui grid">
            <?php showTestCards($running_tests, esc_html__("You don't have any running tests.", 'robera-ab-test')) ?>
            </div>
        </div>
        <?php endif ?>
        <div class="ui segment <?php echo ($finished_tests ? 'loading' : '') ?>">
            <h1>
                <?php esc_html_e("Finished Tests", "robera-ab-test") ?>
            </h1>
            <div class="ui grid">
            <?php showTestCards($finished_tests, esc_html__("You don't have any finished tests.", 'robera-ab-test')) ?>
            </div>
        </div>
        <?php addFooterEmailSection(); ?>
    </div>

    <?php
    confirmationModal(
        "stop-test",
        esc_html__("Confirm Stopping Test", 'robera-ab-test'),
        esc_html__("After stopping A/B test you can't start it again.", 'robera-ab-test'),
        "/?rest_route=/robera/v1/ab-test/stop",
        "",
        esc_html__("Stop Test", 'robera-ab-test')
    );

    confirmStartModel("/?rest_route=/robera/v1/ab-test/start");

    confirmationModal(
        "delete-test",
        esc_html__("Confirm Deleting Test", "robera-ab-test"),
        esc_html__("After deleting A/B test all its content will be lost.", "robera-ab-test"),
        "",
        "red",
        esc_html__("Delete Test", 'robera-ab-test')
    );
    ?>
</div>
<script>
( function( $ ) {
    $(document).ready(function(){
        $('.start-test-button-parent').popup({
            inline: true
        });
        $('.stop-test-button').click(function() {
            $('input[name="test_id"]').val($(this).attr('data-test-id'));
            $('.ui.modal.stop-test').modal('show');
        });
        $('.start-test-button').click(function() {
            $('input[name="test_id"]').val($(this).attr('data-test-id'));
            $('.ui.modal.start-test').modal('show');
        });
        $('.delete-test-button').click(function() {
            $('input[name="test_id"]').val($(this).attr('data-test-id'));
            $('.modal-form.delete-test').attr('action', $(this).attr('data-target-url'));
            $('.ui.modal.delete-test').modal('show');
        });
        $('.test-type').popup({
            inline: true,
            position   : 'bottom center'
        });

        

    });
    $.ajax({
        url: "<?php echo PinkOrBlueClient::getSiteStatsUrl() ?>",
        type: 'post',
        data: {"site_name": "<?php echo PinkOrBlueClient::getSiteName()?>"},
        headers: {
            "Authorization": "<?php echo PinkOrBlueClient::getSecretAuth() ?>"
        },
        dataType: 'json',
        success: function (data) {
            data.forEach(function(item) {
                var tabel_item_el = $('.test-' + item['test_id']);
                Object.keys(item["stats"]).forEach(function(key) {
                    tabel_item_el.find('.variant-' + key + '-visits').html(item["stats"][key]['visits']);
                    tabel_item_el.find('.variant-' + key + '-value').html(item["stats"][key]['value']);
                });
            })
            $('.loading').removeClass('loading');
        }
    });
}( jQuery3_1_1 ) );
</script>