<?php

namespace Robera\AB;

require_once('common.php');

function kpiLabel()
{
    echo '<label for="kpi">' . esc_html__("Select the metrics that you want to use to determine which variant is better:", 'robera-ab-test') . '</label>';
}
?>
<div class="semantic">
    <div class="ui container">
    <h1 style="margin-top: 0.5em;">
        <?php
        if ($mode == "add") {
            esc_html_e('Add New Test', 'robera-ab-test');
            echo "<br><h5>";
            echo wp_kses(__('Check PINKorBLUE user guide on <a href="https://medium.com/@pinkorblue.info/pink-or-blue-a-simple-tool-for-a-b-testing-in-wordpress-475471428b74"> Medium </a> and <a href="https://www.youtube.com/watch?v=oz-6YkeIj-Q"> YouTube</a>.', 'robera-ab-test'), array( 'a' => array('href' => array())));
            echo "</h5>";
        } else {
            esc_html_e('Edit Test', 'robera-ab-test');
        }
        ?>
    </h1>
    <div class="ui form" id="test-form">
        <form id="add-ab-test-form" action="/?rest_route=/robera/v1/ab-test/<?php echo $test["id"];?>" method="post">
            <input type="hidden" name="num_variants" value="0">
            <input type="hidden" name="state" value="<?php echo AB_TEST_STATE_NOT_STARTED ?>">
            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp_rest') ?>">
            <div class="required field">
                <label><?php esc_html_e('Test name', 'robera-ab-test') ?></label>
                <input type="text" name="test-name" value="<?php echo $test["name"]?>">
            </div>
            <div class="field">
                <label><?php esc_html_e('Test description', 'robera-ab-test') ?></label>
                <textarea name="test-desc"><?php echo $test["description"]?></textarea>
            </div>
<!-- this is a problem here! todo -->
            <input type="hidden" name="test-page-id" value="<?php echo $test["post_id"] ?>">
            <?php if ($has_woocommerce) : ?> 
                <div class="inline fields">
                    <label><?php esc_html_e("Select the content type you want to test on:", 'robera-ab-test')?></label>
                    <?php $post_type = $test["type"]; ?>
                        <div class="field">
                          <div class="ui radio checkbox">
                            <input type="radio" name="test-target" <?php echo (($post_type == PAGE_TEST) ? 'checked="checked"' : '')?> tabindex="0" class="hidden" value="<?php echo PAGE_TEST?>">
                            <label><?php esc_html_e("Page or Post", 'robera-ab-test') ?></label>
                          </div>
                        </div>
                        <div class="field">
                          <div class="ui radio checkbox">
                            <input type="radio" name="test-target"  <?php echo (($post_type == PRODUCT_TEST) ? 'checked="checked"' : '')?> tabindex="0" class="hidden" value="<?php echo PRODUCT_TEST?>">
                            <label><?php esc_html_e("Product", 'robera-ab-test') ?></label>
                          </div>
                        </div>
                </div>
                <div class="field">
                    <div class="ui category search field" id="page-search" style='
                    <?php
                    if ($post_type == PAGE_TEST) :
                        echo "display:block";
                    else :
                        echo "display:none";
                    endif; ?>
                        !important;' >
                        <label><?php esc_html_e("Test Object:", "robera-ab-test")?></label>
                        <div class="ui icon input">
                            <input class="prompt" type="text" id="search-pages-input" value="<?php
                             echo get_the_title($test['post_id']);
                            ?>"
                                placeholder="<?php esc_html_e('Search posts and pages ...', "robera-ab-test") ?>">
                            <i class="search icon"></i>
                        </div>
                        <div class="results"></div>
                    </div>
                </div>
                <div class="field">
                    <div class="ui category search field" id="product-search" style='
                    <?php
                    if ($post_type == PRODUCT_TEST) :
                        echo "display:block";
                    else :
                        echo "display:none";
                    endif; ?>
                        !important;' >
                        <label><?php esc_html_e("Test Object:", "robera-ab-test")?></label>
                        <div class="ui icon input">
                            <input class="prompt" type="text" id="search-products-input" value="<?php echo get_the_title($test['post_id'])?>"

                            placeholder="<?php esc_html_e('Search products ...', "robera-ab-test") ?>">
                            <i class="search icon"></i>
                        </div>
                        <div class="results"></div>
                    </div>
                </div>
            <?php else : ?>
                <input type="hidden" name="test-target" value="<?php echo PAGE_TEST?>">
                <div class="field">
                    <label><?php esc_html_e("Test Object:", "robera-ab-test")?></label>
                    <div class="ui category search" id="page-search" style="display:block !important;">
                        <div class="ui icon input">
                            <input class="prompt" type="text" id="search-pages-input" value="<?php
                             echo get_the_title($test['post_id']);
                            ?>"
                                placeholder="<?php esc_html_e('Search posts and pages ...', "robera-ab-test") ?>">
                            <i class="search icon"></i>
                        </div>
                        <div class="results"></div>
                    </div>
                </div>
            <?php endif ?>

            
            <div class="second_level_form" style="display: <?php
                echo ($mode == "add" ? "none" : "block");
            ?>  !important;">
                <div id="variants-field" class="field">
                    <h2 id="variants-header"></h2>
                    <label>
                        <?php echo esc_html__("Create your new variant to test against the original:", "robera-ab-test")?>
                    </label>
                    <div class="ui four column stretched grid padded" style="height: 20rem;">
                        <div class="column" id='empty_variant_block' style="display: none !important;">
                            <div class="ui card">
                                <div class="content">
                                    <input name="variant-page-id" type="hidden">

                                    <div class="header center aligned"  style="display: flex;align-items: center;justify-content: center;">
                                        <div class="variant-name" id="variant-name-display" style="display: inline"></div>
                                        <div class="variant-name" id="variant-name-edit" style="display: none">
                                            <input name="variant-name-edit-input" type="text">
                                        </div>
                                        <button class="ui icon button" id="variant-edit-button"><i class="edit icon"></i></button>
                                        <button class="ui icon button" id="variant-save-button" style="display: none;"><i class="check icon"></i></button>
                                    </div>
                                    <input name="variant-name" type="hidden">

                                    <br>
                                    <div class="ui right labeled aligned centered input" style="width: 4rem">
                                        <input name="variant-percentage" type="text">
                                        <div class="ui label">
                                            % <?php esc_html_e("of users see this.", 'robera-ab-test')?>
                                        </div>
                                    </div>
                                </div>
                                <div id="variant-operations" class="extra content">
                                </div>
                            </div>
                        </div>
                        <div class="column" id="add-variant-button" <?php echo ((count($variants) >= MAX_NUMBER_OF_VARIANTS) ? 'style="display: none !important;"': '')?>>
                            <div class="ui card">
                                <div class="center aligned content add-button-parent">
                                    <div class="ui button add-button"><i class="plus icon"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <h2><?php esc_html_e("Conversion goals", "robera-ab-test")?></h2>
                <div class="grouped required fields" id="page-kpis" style="<?php echo (($test['type'] == PRODUCT_TEST) ? 'display: none;' : '')?>" >
                    <?php kpiLabel() ?>
                    <div class="field" id="page-visit-field" data-tooltip="<?php esc_html_e("Counts the number of times that the targeted page below, is visited. It's a suitable conversion goal to find out how many times people have clicked on a link, leading to a page.",'robera-ab-test')?>" data-position="top left">
                        <div class="ui radio checkbox">
                            <input id="kpi-visiting-input" type="radio" name="kpi" <?php echo (($test["kpi"] == VISIT_KPI) ? 'checked="checked"' : '');?> tabindex="0" class="hidden" value="<?php echo VISIT_KPI?>" >
                            <label><?php esc_html_e("Visit a page", 'robera-ab-test') ?></label>
                        </div>
                        <div class="ui search" id="wc-search">
                            <input name="target-page-id" type="hidden" <?php echo (($test["kpi"] == VISIT_KPI) ? 'value="'.$test["target_page_id"].'"' : '');?> >
                            <div class="ui icon input">
                                <input id="wc-search-input" class="prompt" type="text" placeholder="<?php esc_html_e('Search all over your site to select target page ...', 'robera-ab-test', "robera-ab-test"); ?>" <?php echo (($test["kpi"] == VISIT_KPI) ? 'value="'.get_the_title($test["target_page_id"]).'"' : 'disabled="disabled"');?> >
                                <i class="search icon"></i>
                            </div>
                            <div class="results"></div>
                        </div>
                    </div>
                    <?php if ($has_woocommerce) : ?>
                        <div class="field" id="product-buy-field" data-tooltip="<?php esc_html_e("Shows the number of items of the targeted product below, bought.",'robera-ab-test')?>" data-position="top left">
                            <div class="ui radio checkbox">
                                <input id="kpi-product-buy-input" type="radio" name="kpi" tabindex="0" class="hidden" <?php echo (($test["kpi"] == PRODUCT_BUY_KPI && $post_type == PAGE_TEST) ? 'checked="checked"' : '');?> value="<?php echo PRODUCT_BUY_KPI?>" >
                                <label><?php esc_html_e("Product items bought", 'robera-ab-test') ?></label>
                            </div>
                            <div class="ui search" id="wc-product-search">
                                <input name="target-product-id" type="hidden" value="<?php echo $test['post_id'] ?>">
                                <div class="ui icon input">
                                    <input id="wc-product-search-input" <?php echo (($test["kpi"] == PRODUCT_BUY_KPI) ? 'value="'.get_the_title($test["target_page_id"]).'"' : 'disabled="disabled"') ?> class="prompt" type="text" placeholder="<?php esc_html_e('Search products for the target product ...', 'robera-ab-test'); ?>">
                                    <i class="search icon"></i>
                                </div>
                                <div class="results"></div>
                            </div>
                        </div>
                        <div class="field" data-tooltip="<?php esc_html_e("Measures the 'Total Revenue' of your E-Commerce. It's a useful metric for tests created on pages, specially the main page.", 'robera-ab-test');?>" data-position="top left">
                            <div class="ui radio checkbox">
                                <input type="radio" name="kpi" tabindex="0" class="hidden" <?php echo (($test["kpi"] == REVENUE_KPI && $post_type == PAGE_TEST) ? 'checked="checked"' : '') ?> value="<?php echo REVENUE_KPI ?>">
                                <label><?php esc_html_e("Revenue", "robera-ab-test") ?></label>
                            </div>
                        </div>
                    <?php endif ?>
                    <div class="field" data-tooltip="<?php esc_html_e("Measures the average time a client spends to visit the test object and interact with that. The timer stops whenever the visitor switches between browser tabs.",'robera-ab-test')?>" data-position="top left">
                        <div class="ui radio checkbox" >
                            <input type="radio" name="kpi" tabindex="0" class="hidden" <?php echo (($test["kpi"] == STAY_KPI) ? 'checked="checked"' : '') ?> value="<?php echo STAY_KPI ?>">
                            <label><?php esc_html_e("Average stay time on the test object", 'robera-ab-test') ?></label>
                        </div>
                    </div>
                </div>
                <div class="grouped required fields" id="product-kpis" style="<?php echo ((!$test['type'] || $test['type'] == PAGE_TEST) ? 'display: none;' : '') ?>" >
                    <?php kpiLabel() ?>
                    <div class="field" data-tooltip="<?php esc_html_e("Shows the number of items of the test product bought.",'robera-ab-test')?>" data-position="top left">
                        <input name="target-product-id" type="hidden">
                        <div class="ui radio checkbox">
                            <input type="radio" name="kpi" tabindex="0" class="hidden" <?php echo (($test["kpi"] == PRODUCT_BUY_KPI && $test["type"] == PRODUCT_TEST) ? 'checked="checked"' : '') ?> value="<?php echo PRODUCT_BUY_KPI ?>">
                            <label><?php esc_html_e("Product items bought", 'robera-ab-test') ?></label>
                        </div>
                    </div>
                    <div class="field" data-tooltip="<?php esc_html_e("Counts the number of times that the test product page is visited. It's a suitable conversion goal to find out how many times people have clicked on a link, leading to a product.", 'robera-ab-test') ?>" data-position="top left">
                        <div class="ui radio checkbox">
                            <input type="radio" name="kpi" tabindex="0" class="hidden" <?php echo (($test["kpi"] == VISIT_PRODUCT_KPI) ? 'checked="checked"' : '') ?> value="<?php echo VISIT_PRODUCT_KPI ?>">
                            <label><?php esc_html_e("Visit product page", 'robera-ab-test') ?></label>
                        </div>
                    </div>
                    <div class="field" data-tooltip="<?php esc_html_e("Measures the average time a client spends to visit the test object and interact with that. The timer stops whenever the visitor switches between browser tabs.", 'robera-ab-test')?>" data-position="top left">
                        <div class="ui radio checkbox">
                            <input type="radio" name="kpi" tabindex="0" class="hidden" <?php echo (($test["kpi"] == STAY_KPI && $test["type"] == PRODUCT_TEST) ? 'checked="checked"' : '') ?> value="<?php echo STAY_KPI ?>">
                            <label><?php esc_html_e("Average stay time on the test object", 'robera-ab-test') ?></label>
                        </div>
                    </div>
                </div>
                <input type="submit" name="add-ab-test-submit" class='positive ui button' value="<?php echo esc_html_e('Submit', 'robera-ab-test') ?>" />
                <button class='primary ui button submit-run-button'><?php echo esc_html_e('Submit & Run', 'robera-ab-test') ?></button>
            </div>
            <div class="ui error message"></div>
        </form>
                 <?php addFooterEmailSection(); ?>

    </div>

    <div class="ui tiny modal variant-modal">
        <div class="header">
            <?php esc_html_e('Enter New Variant Information', 'robera-ab-test') ?>
        </div>
        <div class="content">
            <form class="ui form" action="" id="variant-form">
                <div class="required field">
                    <label><?php esc_html_e('Variant name', 'robera-ab-test') ?></label>
                    <input type="text" name="variant-name" placeholder="<?php esc_html_e('Variant name', 'robera-ab-test') ?>">
                </div>
                <div class="required grouped fields">
                    <label for="from"><?php esc_html_e('Start editing from', 'robera-ab-test') ?></label>
                    <div class="field">
                        <div class="ui radio checkbox">
                            <input type="radio" name="from" checked="" value="duplicated" tabindex="0" class="hidden">
                            <label><?php esc_html_e('Copy of the Original', 'robera-ab-test') ?></label>
                        </div>
                        <div class="ui radio checkbox">
                            <input type="radio" name="from" tabindex="0" value="raw" class="hidden">
                            <label><?php esc_html_e('Empty', 'robera-ab-test') ?></label>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="create_variant">
                    <input type="hidden" name="new-variant-test-type" value="<?php echo PAGE_TEST?>">
                    <input type="hidden" name="new-variant-page-id" value="<?php echo $test['post_id'] ?>">
                </div>
                <div class="actions">
                    <button type="submit" class="ui button submit-variant"><?php esc_html_e('Submit', 'robera-ab-test') ?></button>
                </div>
                <div class="ui error message"></div>
                <div class="ui negative message" id="modal-server-error" style="display: none">
                    <i class="close icon"></i>
                    <div class="header">
                        <?php esc_html_e("Something went wrong", 'robera-ab-test') ?>
                    </div>
                    <p id="modal-error-message-text"></p>
                </div>
            </form>
        </div>
    </div>
    <?php
    confirmStartModel("");
    ?>
    </div>
</div>
<script type="text/javascript">
// First, checks if it isn't implemented yet.
if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) {
      return typeof args[number] != 'undefined'
        ? args[number]
        : match
      ;
    });
  };
}
( function( $ ) {
    var num_variants = 0;
    var max_number_of_variants = <?php echo MAX_NUMBER_OF_VARIANTS ?>;

    var empty_block_html = $("#empty_variant_block").html();
    var empty_block_selector_base = $(empty_block_html);
    function add_variant_block(page_id, variant_name, percentage, nameEditable){
        var empty_block_selector = empty_block_selector_base.clone(false);
        var variant_block_id = "variant-" + num_variants + "-block";

        $(empty_block_selector).find('input[name="variant-page-id"]').val(page_id);
        $(empty_block_selector).find('input[name="variant-page-id"]').attr("name", 'variant-' + num_variants + '-page-id');

        if (nameEditable) {
            $(empty_block_selector).find('button#variant-edit-button').css('display', 'inline');
        } else {
            $(empty_block_selector).find('button#variant-edit-button').css('display', 'none');
        }
        $(empty_block_selector).find('div#variant-name-display').html(variant_name);
        $(empty_block_selector).find('input[name="variant-name"]').val(variant_name);
        $(empty_block_selector).find('input[name="variant-name"]').attr("name", 'variant-' + num_variants + '-name');

        var operations_block = "";
        if(num_variants != 0){
            operations_block = 
            '<button id="edit-variant-' + num_variants + '" type="button" class="ui button">' + "<?php esc_html_e('Edit', 'robera-ab-test') ?>" + '</button>' + '<button id="delete-variant-' + num_variants + '" type="button" class="ui red button">' + "<?php esc_html_e('Delete', 'robera-ab-test') ?>" + '</button>';
        }else{
            operations_block = "<?php esc_html_e("To edit original page, use pages admin, menue.", 'robera-ab-test'); ?>";
            $(empty_block_selector).find('input[name="variant-percentage"]').attr("disabled", "disabled");
        }
        $(empty_block_selector).find('#variant-operations').html(operations_block);


        $(empty_block_selector).find('div#variant-name-display').attr("id", 'variant-name-display-' + num_variants);
        $(empty_block_selector).find('div#variant-name-edit').attr("id", 'variant-name-edit-' + num_variants);
        $(empty_block_selector).find('button#variant-edit-button').attr("id", 'variant-edit-button-' + num_variants);
        $(empty_block_selector).find('button#variant-save-button').attr("id", 'variant-save-button-' + num_variants);
        $(empty_block_selector).find('input[name="variant-name-edit-input"]').attr("name", 'variant-name-edit-input-' + num_variants);


        $(empty_block_selector).find('input[name="variant-percentage"]').attr("value", percentage);
        $(empty_block_selector).find('input[name="variant-percentage"]').attr("name", "variant-" + num_variants + "-percentage");

        add_block = '<div class="column" id="' + variant_block_id + '">' + empty_block_selector.wrapAll('<div>').parent().html() +"</div>";
        $(add_block).insertBefore("#add-variant-button");
        $('input[name="variant-' + num_variants + '-percentage"]').on('input', function(){
            var inp = $(this).val();
            if(inp == ""){
                $(this).val("0");
                inp = "0";
            }
            var inp_num = Number(inp);
            $('input[name="variant-0-percentage"]').attr('value', 100 - inp_num);
        });
        $('input[name="variant-' + num_variants + '-percentage"]').keypress(function(event){

            var prev_inp = $(this).val();
            
            if("0123456789".indexOf(event.key) < 0 && event.which != 8)
                return false

            if(prev_inp == "0")
                $(this).val("");
            var input_num = Number($(this).val() + event.key);
            if(input_num > 100)
                return false
            
        });


        $('#edit-variant-' + num_variants).click(function(e) {
            e.preventDefault();

            var idSplit = $(this).attr('id').split('-');
            var currentID = idSplit[idSplit.length - 1];

            var page_id = $('input[name="variant-' + currentID + '-page-id"]').val();
            go_edit_post(page_id, currentID);
        })

        $('#delete-variant-' + num_variants).click(function(e) {
            e.preventDefault();

            var idSplit = $(this).attr('id').split('-');
            var currentID = idSplit[idSplit.length - 1];

            delete_variant_block(currentID);
        })

        $('button#variant-edit-button-' + num_variants).click(function(e) {
            e.preventDefault();

            var idSplit = $(this).attr('id').split('-');
            var currentID = idSplit[idSplit.length - 1];

            $('input[name="variant-name-edit-input-' + currentID + '"]').val(
                $('input[name="variant-' + currentID + '-name"]').val()
            )
            $('div#variant-name-display-' + currentID).css("display", 'none');
            $('div#variant-name-edit-' + currentID).css("display", 'inline');
            $('button#variant-save-button-' + currentID).css('display', 'inline');
            $('button#variant-edit-button-' + currentID).css('display', 'none');
        });

        changeVariantName = function(id){
            var idSplit = id.split('-');
            var currentID = idSplit[idSplit.length - 1];
            $('input[name="variant-' + currentID + '-name"]').attr('value',
                $('input[name="variant-name-edit-input-' + currentID + '"]').val()
            );
            $('div#variant-name-display-' + currentID).html($('input[name="variant-name-edit-input-' + currentID + '"]').val());
            $('div#variant-name-display-' + currentID).css("display", 'inline');
            $('div#variant-name-edit-' + currentID).css("display", 'none');

            $('button#variant-save-button-' + currentID).css('display', 'none');
            $('button#variant-edit-button-' + currentID).css('display', 'inline');
        };

        $('button#variant-save-button-' + num_variants).click(function(e) {
            e.preventDefault();

            changeVariantName($(this).attr('id'));
        });

        $('input[name="variant-name-edit-input-' + num_variants + '"]').on('keydown', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                e.stopPropagation();
                changeVariantName($(this).attr('name'));
            }
        });

        num_variants ++;
        $('input[name="num_variants"]').val(num_variants);

        if(num_variants == max_number_of_variants){
            $("#add-variant-button").css("cssText", "display: none !important");
        }

        if (num_variants >= 2) {
            $("#variants-field").removeClass('error');
        }
    }

    function go_edit_post(page_id, num_variant){
        $("#edit-variant-" + num_variant).addClass("loading");
        $('input[name="variant-0-percentage"]').removeAttr('disabled');
        var tmp = $('#add-ab-test-form').serialize();
        $('input[name="variant-0-percentage"]').attr('disabled', 'disabled');
        $.post({
            <?php
            if ($mode == "add") {
                echo 'url: "/?rest_route=/robera/v1/ab-test/",';
            } else {
                $edit_link = "/?rest_route=/robera/v1/ab-test/".$test["id"];
                echo 'url: "'.home_url($edit_link).'",';
            }
            ?>

            data: tmp, // serializes the form's elements.
            success: function(data)
            {   
                $("#edit-variant-" + num_variant).removeClass("loading");
                $(window).unbind('beforeunload');
                window.location = "post.php?post=" + page_id + "&action=edit";
            }

        });
    }

    function delete_variant_block(variant_id){
        $("#variant-" + variant_id + "-block").remove();
        $("#add-variant-button").css("cssText", "display: block");
        num_variants --;
        $('input[name="num_variants"]').val(num_variants);
        $('input[name="variant-0-percentage"]').attr('value', 100);
    }

    var initial_variants = [
        <?php foreach ($variants as $key => $variant) : ?>
            {
                'name': '<?php echo $variant["name"] ?>',
                'percentage': <?php echo $variant["percentage"] ?>,
                'post_id': <?php echo $variant["post_id"] ?>
            },
        <?php endforeach ?>
    ];
    var initial_page = '<?php echo get_the_title($test["post_id"]) ?>';

    $( document ).ready(function() {
        $('#variants-header').text("<?php esc_html_e('Variants for ', 'robera-ab-test') ?>" + initial_page);
        $('input').on('change', function(){
            $(window).bind("beforeunload",function(event) {
                return true;
            });
        });

        $('.message .close').on('click', function() {
            $(this).closest('.message').transition('fade');
        });

        $.fn.form.settings.rules.greaterThan = function(value, targetValue) {
            return targetValue < value;
        };

        $.fn.form.settings.rules.lessThanEqual = function(value, targetValue) {
            return value <= targetValue;
        };
        $('#test-form').form({
            fields: {
                'test-name': {
                    'identifier': 'test-name',
                    'rules': [{
                        'type': 'empty',
                        'prompt': "<?php esc_html_e('Test name must have a value.', 'robera-ab-test') ?>"
                    }],
                },
                'target-page-id': {
                    'identifier': 'target-page-id',
                    'depends': 'kpi-visiting-input',
                    'rules': [{
                        'type': 'empty',
                        'prompt': "<?php esc_html_e('You should select a target for visit a page conversion goal.', 'robera-ab-test') ?>"
                    }]
                },
                'target-product-id': {
                    'identifier': 'target-product-id',
                    'depends': 'kpi-product-buy-input',
                    'rules': [{
                        'type': 'empty',
                        'prompt': "<?php esc_html_e('You should select a product for product item bought conversion goal.', 'robera-ab-test') ?>"
                    }]
                },
                'kpi': {
                    'identifier': 'kpi',
                    'rules': [{
                        'type': 'checked',
                        'prompt' : "<?php esc_html_e('Please select a conversion goal.', 'robera-ab-test') ?>"
                    }],
                },
                'num_variants': {
                    'identifier': 'num_variants',
                    'rules': [{
                        'type': 'greaterThan[1]',
                        'prompt' : "<?php esc_html_e('Number of variants should be at least two.', 'robera-ab-test') ?>"
                    },
                    {
                        'type': 'lessThanEqual[' + max_number_of_variants + ']',
                        'prompt' : "<?php esc_html_e('Number of variants should be at most {0}.', 'robera-ab-test') ?>".format(max_number_of_variants)
                    }],
                }
            },
            onFailure: function(errors, fields){
                if (! $('#test-form').form('is valid', 'num_variants')) {
                    $('#variants-field').addClass('error');
                }
                if (! $('#test-form').form('is valid', 'target-page-id')) {
                    $('#page-visit-field').addClass('error');
                }
                if (! $('#test-form').form('is valid', 'target-product-id')) {
                    $('#product-buy-field').addClass('error');
                }
                return false;
            },
            onSuccess: function(event, fields) {
                $('input[name="variant-0-percentage"]').removeAttr('disabled');
                $(window).unbind('beforeunload');
            }
        });

        $('.submit-run-button').click(function(event) {
            if ($('#test-form').form('is valid')) {
                event.preventDefault();
                $('.ui.modal.start-test').modal('show');
            }
        });
        $('.start-button-modal').click(function(event) {
            event.preventDefault();
            $('input[name="state"]').attr('value', '<?php echo AB_TEST_STATE_RUNNING ?>');
            $('#add-ab-test-form').submit();
        });

        $('#variant-form').form({
            fields: {
                'variant-name': {
                    'identifier': 'variant-name',
                    'rules': [{
                        'type': 'empty',
                        'prompt': "<?php esc_html_e('Variant name must have a value', 'robera-ab-test') ?>"
                    }],
                },
                'from': {
                    'identifier': 'from',
                    'rules': [{
                        'type': 'checked',
                        'prompt': "<?php esc_html_e('You should select how to initiate variant', 'robera-ab-test') ?>"
                    }],
                },
            },
            onSuccess: function(e) {
                e.preventDefault(); // avoid to execute the actual submit of the form.

                $('.submit-variant').addClass('loading');
                var form = $(this);
                var url = form.attr('action');

                $.post({
                    url: ajaxurl,
                    data: form.serialize(), // serializes the form's elements.
                    success: function(data)
                    {
                        $('.submit-variant').removeClass('loading');
                        $('.ui.tiny.modal.variant-modal').modal('hide');
                        result = jQuery.parseJSON(data);
                        var targetPercent = 50;
                        add_variant_block(result["new_post_id"], form.serializeArray()[0]["value"], targetPercent, true);
                        $('input[name="variant-0-percentage"]').attr('value', 100 - targetPercent);
                        $('#modal-server-error').css('display', 'none');
                    }
                }).fail(function(data) {
                    $('.submit-variant').removeClass('loading');
                    $('#modal-server-error').css('display', 'block');
                    $('#modal-error-message-text').text(data['responseJSON']['data']['message']);
                });

            },
        });


        initial_variants.forEach(function(variant, index){
            add_variant_block(variant['post_id'], variant["name"], variant["percentage"], index != 0);
        });
        var newHeader = function (message, type) {
            var
            html = '';
            if (message !== undefined && type !== undefined) {
                html += '' + '<div class="message ' + type + '">';
                // message type
                if (type == 'empty') {
                    html += '' + '<div class="header"><?php esc_html_e("No Result", "robera-ab-test");?></div class="header">' + '<div class="description">' + message + '</div class="description">';
                } else {
                    html += ' <div class="description">' + message + '</div>';
                }
                html += '</div>';
            }
            return html;
        };
        $.fn.search.settings.templates.message = newHeader;

        $('#page-search')
        .search({
            apiSettings: {
                url: '/?rest_route=/robera/v1/ab-test/search&search={query}&subtype=post,page&_wpnonce=<?php echo wp_create_nonce('wp_rest') ?>',
                onResponse: function(retVal){
                    results = Object.values(retVal);
                    if (results.length == 0)
                        return [];
                    results = results.map(function(x){
                        return {
                            'title': x['title'],
                            'id': x['id'],
                            'category': x['subtype']
                        }
                    });
                    categories = {
                        "page": {
                            "name": "<?php esc_html_e("Page", "robera-ab-test") ?>",
                            "results": []
                        },
                        "post": {
                            "name": "<?php esc_html_e("Post", "robera-ab-test")?>",
                            "results": []
                        }
                    };
                    results.forEach(function(item) {
                        if (item['category'] == "post") {
                            categories["post"]["results"].push(item);
                        } else {
                            categories["page"]["results"].push(item);
                        }
                    })
                    return {
                        'results': categories
                    };
                }
            },
            fields: {
                title   : 'title',
            },
            error: {
                noResults: ''
            },
            type: 'category',
            minCharacters : 2,
            onSelect: function(result){
                $('.second_level_form').css('display', 'block');

                $('div').filter(function() {
                    return this.id.match(/variant-+\d-block/);
                }).remove();
                num_variants = 0;
                $("#add-variant-button").css("cssText", "display: block");
                $('input[name="new-variant-page-id"]').attr('value', result['id']);
                $('input[name="test-page-id"]').attr('value', result['id']);
                $('#variants-header').text("<?php esc_html_e('Variants for {0}', 'robera-ab-test') ?>".format(result['title']));
                add_variant_block(result["id"], "<?php esc_html_e("Original variant", 'robera-ab-test')?>", 100, false);
            }
        });
        
        $('#product-search') 
        .search({
            apiSettings: {
                url: '/?rest_route=/robera/v1/ab-test/search&search={query}&subtype=product&_wpnonce=<?php echo wp_create_nonce('wp_rest') ?>',
                onResponse: function(retVal){
                    results = Object.values(retVal);
                    if (results.length == 0)
                        return [];
                    results = results.map(function(x){
                        return {
                            'title': x['title'],
                            'id': x['id'],
                        }
                    });
                    return {
                        'results': results
                    };
                }
            },
            fields: {
                title   : 'title',
            },
            error: {
                noResults: ''
            },
            minCharacters : 2,
            onSelect: function(result){
                $('.second_level_form').css('display', 'block');

                $('div').filter(function() {
                    return this.id.match(/variant-+\d-block/);
                }).remove();
                num_variants = 0;
                $("#add-variant-button").css("cssText", "display: block");
                $('input[name="new-variant-page-id"]').attr('value', result['id']);
                $('input[name="test-page-id"]').attr('value', result['id']);
                $('input[name="target-product-id"]').attr('value', result['id']);
                $('#variants-header').text("<?php esc_html_e('Variants for {0}', 'robera-ab-test') ?>".format(result['title']));
                add_variant_block(result["id"], "<?php esc_html_e("Original variant", 'robera-ab-test')?>", 100, false);
            }
        });

        $('#wc-search')
        .search({
            apiSettings: {
                url: '/?rest_route=/robera/v1/ab-test/search&search={query}&subtype=post,page,<?php echo $has_woocommerce ? "product" : " " ?>&_wpnonce=<?php echo wp_create_nonce('wp_rest') ?>',
                onResponse: function(retVal){
                    results = Object.values(retVal);
                    if (results.length == 0)
                        return [];
                    results = results.map(function(x){
                        return {
                            'title': x['title'],
                            'id': x['id'],
                            'category': x['subtype']
                        }
                    });
                    categories = {
                        "page": {
                            "name": "<?php esc_html_e("Page", "robera-ab-test")?>",
                            "results": []
                        },
                        "product": {
                            "name": "<?php esc_html_e("Product", "robera-ab-test")?>",
                            "results": []
                        },
                        "post": {
                            "name": "<?php esc_html_e("Post", "robera-ab-test")?>",
                            "results": []
                        }
                    };
                    results.forEach(function(item) {
                        if (item['category'] == "post") {
                            categories["post"]["results"].push(item);
                        } else if (item['category'] == "page"){
                            categories["page"]["results"].push(item);
                        } else {
                            categories["product"]["results"].push(item);
                        }
                    })
                    return {
                        'results': categories
                    };
                }
            },
            fields: {
                title   : 'title',
            },
            error: {
                noResults: ''
            },
            type: "category",
            minCharacters : 2,
            onSelect: function(result){
                $('input[name="target-page-id"]').attr('value', result['id']);
                $('#page-visit-field').removeClass('error');
            }
        });
        $('#wc-product-search')
        .search({
            apiSettings: {
                url: '/?rest_route=/robera/v1/ab-test/search&search={query}&subtype=product&_wpnonce=<?php echo wp_create_nonce('wp_rest') ?>',
                onResponse: function(retVal){
                    results = Object.values(retVal);
                    if (results.length == 0)
                        return [];
                    results = results.map(function(x){
                        return {
                            'title': x['title'],
                            'id': x['id'],
                        }
                    });
                    return {
                        'results': results
                    };
                }
            },
            fields: {
                title   : 'title',
            },
            error: {
                noResults: ''
            },
            minCharacters : 2,
            onSelect: function(result){
                $('input[name="target-product-id"]').attr('value', result['id']);
                $('#product-buy-field').removeClass('error');
            }
        });

        $('.add-button').click(function(){
            $('.ui.tiny.modal.variant-modal').modal('show');
        })
        $('.ui.radio.checkbox').checkbox();

        $("#add-ab-test-form").submit(function(e){
            $("#empty_variant_block").remove();
        });

        $('input[name="kpi"]').on("change", function() {
            if($(this).val() == "<?php echo VISIT_KPI?>"){
                $("#wc-search-input").removeAttr("disabled");                
            }
            else{
                $("#wc-search-input").attr("disabled", "disabled");
            }
            if($(this).val() == "<?php echo PRODUCT_BUY_KPI?>"){
                $("#wc-product-search-input").removeAttr("disabled");
            }
            else{
                $("#wc-product-search-input").attr("disabled", "disabled");
            }
        });

        $('input[name="test-target"]').on("change", function() {
            $('.second_level_form').css('display', 'none');
            $('input[name="kpi"]').prop('checked', false);

            $("#wc-product-search-input").attr("disabled", "disabled");
            $("#wc-search-input").attr("disabled", "disabled");
            if($(this).val() == "<?php echo PRODUCT_TEST?>"){
                $("#page-search").css('display', 'none');                
                $("#product-search").css('display', 'block');

                $("#page-kpis").css('display', 'none');
                $("#product-kpis").css('display', 'block');

                $('#search-products-input').attr('value', '');
                $('input[name="new-variant-test-type"]').attr('value', '<?php echo PRODUCT_TEST?>');
            }
            else{
                $("#product-search").css('display', 'none');                
                $("#page-search").css('display', 'block');

                $("#product-kpis").css('display', 'none');
                $("#page-kpis").css('display', 'block');

                $('#search-pages-input').val('');
                $('input[name="new-variant-test-type"]').attr('value', '<?php echo PAGE_TEST?>');
                $('input[name="target-product-id"]').attr('value', '');
            }
        });
    });
}( jQuery3_1_1 ) );
</script>
