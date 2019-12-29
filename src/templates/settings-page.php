<?php
namespace Robera\AB;

require_once('common.php');

?>
<div class="semantic">
    <div class="ui container" style="margin-top: 1rem">
        <div class="ui segment">
            <h2 class="ui header">
                <?php esc_html_e("Pink or Blue Settings", "robera-ab-test")?>
            </h2>
            <div class="content">
                <div class="ui form">
                    <form action="/?rest_route=/robera/v1/settings" method="post">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp_rest') ?>">
                        <div class="grouped fields">
                            <label for="metas"><?php
                            esc_html_e("By default we only support some fields in A/B testing like `title`, `content`, `excerpt`, `featured_image`, and `product_gallery`. If your website uses another metas that you want to A/B test on them select them bellow.", "robera-ab-test")
                            ?></label>
                            <?php foreach ($all_metas as $meta_key) : ?>
                            <div class="field">
                                <div class="ui checkbox">
                                    <input type="checkbox" tabindex="0" class="hidden" name="metas[]" value="<?php echo $meta_key ?>" <?php echo (in_array($meta_key, $current_metas) ? 'checked="checked?"' : '') ?> >
                                    <label><?php echo $meta_key ?></label>
                                </div>
                            </div>
                            <?php endforeach ?>
                        </div>
                        <input type="submit" class='positive ui button' value="<?php echo esc_html_e('Submit', 'robera-ab-test') ?>" />
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php addFooterEmailSection(); ?>
</div>

<script type="text/javascript">
( function( $ ) {
    $( document ).ready(function() {
        $('.ui.checkbox')
          .checkbox()
        ;
    });
}( jQuery3_1_1 ) );
</script>
