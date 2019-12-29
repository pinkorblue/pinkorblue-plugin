<?php
namespace Robera\AB;

require_once('common.php');


?>
<div class="semantic">
    <div class="ui container" style="margin-top: 1rem">
        <div class="ui segment">
            <h2 class="ui header">
              <img class="ui image" src="<?php echo plugins_url('../static/pinkORblue.svg', __FILE__);?>">
                <?php esc_html_e("Pink or Blue A/B Test", "robera-ab-test")?>
            </h2>
            <div class="content">
                <?php if ($status) : ?>
                    <div class="ui <?php echo $success ? 'positive' : 'negative' ?> message">
                        <i class="close icon"></i>
                        <div class="header">
                            <?php echo $message ?>
                        </div>
                    </div>
                <?php endif ?>
                <?php
                $current_user =  wp_get_current_user();
                $plan_num_days = get_option(RoberaABPlugin::$NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME);
                $plan_name = get_option(RoberaABPlugin::$NEXT_PLAN_NAME_OPTION_NAME);
                printf(esc_html__('Dear %s, ', 'robera-ab-test'), $current_user->user_login);
                if (! get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
                    printf(esc_html__('After starting your first test, your %s plan will start and work for %s days.', 'robera-ab-test'), $plan_name, $plan_num_days);
                } elseif (! RoberaABPlugin::isExpired()) {
                    $expiration_date_str = get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME);
                    $expiration_date = new \DateTime();
                    $expiration_date->setTimestamp(mysql2date('U', $expiration_date_str));
                    $date1 = new \DateTime("now", new \DateTimeZone("UTC"));
                    $interval = $date1->diff($expiration_date);
                    $remaining_time = $interval->format('%a ');
                    if ($remaining_time > 1) {
                        printf(esc_html__('your plugin is active for %1$s days.', 'robera-ab-test'), $remaining_time);
                    } else {
                        printf(esc_html__('your plugin is active for one day.', 'robera-ab-test'), $remaining_time);
                    }
                } else {
                    esc_html_e("your plugin has been expired. To continue using Pink or Blue please purchase a plan.", "robera-ab-test");
                }
                ?>
                <br>
                <br>
                <?php if (get_option(RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME) == RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NOT_REGISTERED) : ?>
                    <?php
                    esc_html_e('To sibscribe to Pink or Blue plugin, you should verify your email first.', 'robera-ab-test');
                    ?>
                    <br><br>
                    <form class="ui form" action="/?rest_route=/robera/v1/ab-test/account/register" method="post">
                        <div class="field">
                            <input type="text" name="email" placeholder="<?php esc_html_e('Email')?>">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp_rest') ?>">
                        </div>
                        <input class="ui primary submit button" value="<?php esc_html_e('Submit');?>" type=submit>
                        <div class="ui error message">
                        </div>
                    </form>
                <?php elseif (get_option(RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME) === RoberaABPlugin::$ACCOUNT_STATUS_OPTION_WAITING_VERIFY) :?>
                    <?php
                    $current_user =  wp_get_current_user();
                    printf(
                        esc_html__(
                            'Enter the verification code that sent to %1$s. To change your email or resend verification code ',
                            'robera-ab-test'
                        ),
                        get_option(RoberaABPlugin::$ACCOUNT_EMAIL_OPTION_NAME)
                    );
                    ?>
                    <a href="/?rest_route=/robera/v1/ab-test/account/ignore-registeration&_wpnonce=<?php echo wp_create_nonce('wp_rest') ?>"><?php esc_html_e('click here.', 'robera-ab-test');?></a>
                    <br>
                    <br>
                    <form class="ui form" action="/?rest_route=/robera/v1/ab-test/account/verify" method="post">
                        <div class="field">
                            <input type="text" name="verification-code" placeholder="<?php esc_html_e('Verification Code', 'robera-ab-test')?>">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp_rest') ?>">
                        </div>
                        <input class="ui primary submit button" value="<?php esc_html_e('Submit', 'robera-ab-test');?>" type=submit>
                        <div class="ui error message">
                        </div>
                    </form>
                <?php elseif (get_option(RoberaABPlugin::$ACCOUNT_STATUS_OPTION_NAME) === RoberaABPlugin::$ACCOUNT_STATUS_OPTION_VERIFIED) :?>
                    <?php
                    $plan_num_days = get_option(RoberaABPlugin::$NEXT_PLAN_NUMBER_OF_DAYS_OPTION_NAME);
                    $plan_name = get_option(RoberaABPlugin::$NEXT_PLAN_NAME_OPTION_NAME);
                    $plan_price = get_option(RoberaABPlugin::$NEXT_PLAN_PRICE_TOMAN_OPTION_NAME);
                    if (get_option(RoberaABPlugin::$EXPIRATION_DATE_OPTION_NAME)) {
                        printf(esc_html__('To purchase %s plan for %s Tomans,', 'robera-ab-test'), $plan_name, $plan_price);
                        echo '<a href="'.PinkOrBlueClient::getPaymentUrl().'">'.esc_html__('click on this link.', 'robera-ab-test').'</a>'.esc_html__(' If your plugin is active now, perchase will extend expiration date.', 'robera-ab-test');
                    }
                    ?>
                <?php endif;?>
            </div>
        </div>
    </div>
    <?php addFooterEmailSection(); ?>
</div>

<script type="text/javascript">
( function( $ ) {
    $( document ).ready(function() {
        $('.message .close').on('click', function() {
            $(this).closest('.message').transition('fade');
        });
        $('.ui.form').form({
            fields: {
                name: {
                    identifier: 'special-name',
                    rules: [
                    {
                        type: 'email',
                        prompt: "<?php esc_html_e('Please enter valid email.', 'robera-ab-test') ?>"
                    }
                ]
              }
            }
        });
    });
}( jQuery3_1_1 ) );
</script>
