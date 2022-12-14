<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade offer-convert-modal" id="convert_to_estimate" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-xxl" role="document">
        <?php echo form_open('admin/offers/convert_to_estimate/'.$offer->id,array('id'=>'offer_convert_to_estimate_form','class'=>'_transaction_form disable-on-submit')); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" onclick="close_modal_manually('#convert_to_estimate')" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">
                    <span class="edit-title"><?php echo _l('offer_convert_to_estimate'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php $this->load->view('admin/estimates/estimate_template'); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" onclick="close_modal_manually('#convert_to_estimate')">
                    <?php echo _l('close'); ?>
                </button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>
<?php $this->load->view('admin/invoice_items/item'); ?>
<script>
    init_ajax_search('customer','#client_id.ajax-search');
    init_ajax_search('items','#item_select.ajax-search',undefined,admin_url+'items/search');
    custom_fields_hyperlink();
    init_selectpicker();
    init_datepicker();
    init_color_pickers();
    init_items_sortable();
    init_tags_inputs();
    validate_estimate_form('#offer_convert_to_estimate_form');
    <?php if($offer->assigned != 0){ ?>
    $('#convert_to_estimate #sale_agent').selectpicker('val',<?php echo $offer->assigned; ?>);
    <?php } ?>
    $('select[name="discount_type"]').selectpicker('val','<?php echo $offer->discount_type; ?>');
    $('input[name="discount_percent"]').val('<?php echo $offer->discount_percent; ?>');
    $('input[name="discount_total"]').val('<?php echo $offer->discount_total; ?>');
    <?php if(is_sale_discount($offer,'fixed')) { ?>
        $('.discount-total-type.discount-type-fixed').click();
    <?php } ?>
    $('input[name="adjustment"]').val('<?php echo $offer->adjustment; ?>');
    $('input[name="show_quantity_as"][value="<?php echo $offer->show_quantity_as; ?>"]').prop('checked',true).change();
    $('#convert_to_estimate #client_id').change();
    // Trigger item select width fix
    $('#convert_to_estimate').on('shown.bs.modal', function(){
        $('#item_select').trigger('change')
    })

</script>
