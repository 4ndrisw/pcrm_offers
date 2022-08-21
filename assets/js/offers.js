// Init single offer
function init_offer(id) {
    load_small_table_item(id, '#offer', 'offerid', 'offers/get_offer_data_ajax', '.table-offers');
}


// Used when estimate is updated from pipeline. eq changed order or moved to another status
function estimates_pipeline_update(ui, object) {
    if (object === ui.item.parent()[0]) {
        var data = {
            estimateid: $(ui.item).attr('data-estimate-id'),
            status: $(ui.item.parent()[0]).attr('data-status-id'),
            order: [],
        };

        $.each($(ui.item).parents('.pipeline-status').find('li'), function (idx, el) {
            var id = $(el).attr('data-estimate-id');
            if(id){
                data.order.push([id, idx+1]);
            }
        });

        check_kanban_empty_col('[data-estimate-id]');

        setTimeout(function () {
             $.post(admin_url + 'estimates/update_pipeline', data).done(function (response) {
                update_kan_ban_total_when_moving(ui,data.status);
                estimate_pipeline();
            });
        }, 200);
    }
}

// Used when offer is updated from pipeline. eq changed order or moved to another status
function offers_pipeline_update(ui, object) {
    if (object === ui.item.parent()[0]) {
        var data = {
            order: [],
            status: $(ui.item.parent()[0]).attr('data-status-id'),
            offerid: $(ui.item).attr('data-offer-id'),
        };

        $.each($(ui.item).parents('.pipeline-status').find('li'), function (idx, el) {
            var id = $(el).attr('data-offer-id');
            if(id){
                data.order.push([id, idx+1]);
            }
        });

        check_kanban_empty_col('[data-offer-id]');

        setTimeout(function () {
            $.post(admin_url + 'offers/update_pipeline', data).done(function (response) {
                update_kan_ban_total_when_moving(ui,data.status);
                offers_pipeline();
            });
        }, 200);
    }
}

// Init offers pipeline
function offers_pipeline() {
    init_kanban('offers/get_pipeline', offers_pipeline_update, '.pipeline-status', 347, 360);
}

// Open single offer in pipeline
function offer_pipeline_open(id) {
    if (id === '') {
        return;
    }
    requestGet('offers/pipeline_open/' + id).done(function (response) {
        var visible = $('.offer-pipeline-modal:visible').length > 0;
        $('#offer').html(response);
        if (!visible) {
            $('.offer-pipeline-modal').modal({
                show: true,
                backdrop: 'static',
                keyboard: false
            });
        } else {
            $('#offer').find('.modal.offer-pipeline-modal')
                .removeClass('fade')
                .addClass('in')
                .css('display', 'block');
        }
    });
}

// Sort offers in the pipeline view / switching sort type by click
function offer_pipeline_sort(type) {
    kan_ban_sort(type, offers_pipeline);
}

// Validates offer add/edit form
function validate_offer_form(selector) {

    selector = typeof (selector) == 'undefined' ? '#offer-form' : selector;

    appValidateForm($(selector), {
        clientid: {
            required: {
                depends: function () {
                    var customerRemoved = $('select#clientid').hasClass('customer-removed');
                    return !customerRemoved;
                }
            }
        },
        date: 'required',
        office_id: 'required',
        number: {
            required: true
        }
    });

    $("body").find('input[name="number"]').rules('add', {
        remote: {
            url: admin_url + "offers/validate_offer_number",
            type: 'post',
            data: {
                number: function () {
                    return $('input[name="number"]').val();
                },
                isedit: function () {
                    return $('input[name="number"]').data('isedit');
                },
                original_number: function () {
                    return $('input[name="number"]').data('original-number');
                },
                date: function () {
                    return $('body').find('.offer input[name="date"]').val();
                },
            }
        },
        messages: {
            remote: app.lang.offer_number_exists,
        }
    });

}


// Get the preview main values
function get_offer_item_preview_values() {
    var response = {};
    response.description = $('.main textarea[name="description"]').val();
    response.long_description = $('.main textarea[name="long_description"]').val();
    response.qty = $('.main input[name="quantity"]').val();
    return response;
}

// Append the added items to the preview to the table as items
function add_offer_item_to_table(data, itemid){

  // If not custom data passed get from the preview
  data = typeof (data) == 'undefined' || data == 'undefined' ? get_offer_item_preview_values() : data;
  if (data.description === "" && data.long_description === "") {
     return;
  }

  var table_row = '';
  var item_key = lastAddedItemKey ? lastAddedItemKey += 1 : $("body").find('tbody .item').length + 1;
  lastAddedItemKey = item_key;

  table_row += '<tr class="sortable item">';

  table_row += '<td class="dragger">';

  // Check if quantity is number
  if (isNaN(data.qty)) {
     data.qty = 1;
  }

  $("body").append('<div class="dt-loader"></div>');
  var regex = /<br[^>]*>/gi;

     table_row += '<input type="hidden" class="order" name="newitems[' + item_key + '][order]">';

     table_row += '</td>';

     table_row += '<td class="bold description"><textarea name="newitems[' + item_key + '][description]" class="form-control" rows="5">' + data.description + '</textarea></td>';

     table_row += '<td><textarea name="newitems[' + item_key + '][long_description]" class="form-control item_long_description" rows="5">' + data.long_description.replace(regex, "\n") + '</textarea></td>';
   //table_row += '<td><textarea name="newitems[' + item_key + '][long_description]" class="form-control item_long_description" rows="5">' + data.long_description + '</textarea></td>';


     table_row += '<td><input type="number" min="0" onblur="calculate_total();" onchange="calculate_total();" data-quantity name="newitems[' + item_key + '][qty]" value="' + data.qty + '" class="form-control">';

     if (!data.unit || typeof (data.unit) == 'undefined') {
        data.unit = '';
     }

     table_row += '<input type="text" placeholder="' + app.lang.unit + '" name="newitems[' + item_key + '][unit]" class="form-control input-transparent text-right" value="' + data.unit + '">';

     table_row += '</td>';


     table_row += '<td><a href="#" class="btn btn-danger pull-left" onclick="delete_item(this,' + itemid + '); return false;"><i class="fa fa-trash"></i></a></td>';

     table_row += '</tr>';

     $('table.items tbody').append(table_row);

     $(document).trigger({
        type: "item-added-to-table",
        data: data,
        row: table_row
     });


     clear_item_preview_values();
     reorder_items();

     $('body').find('#items-warning').remove();
     $("body").find('.dt-loader').remove();

  return false;
}


// From offer table mark as
function offer_mark_as(status_id, offer_id) {
    var data = {};
    data.status = status_id;
    data.offerid = offer_id;
    $.post(admin_url + 'offers/update_offer_status', data).done(function (response) {
        //table_offers.DataTable().ajax.reload(null, false);
        reload_offers_tables();
    });
}

// Reload all offers possible table where the table data needs to be refreshed after an action is performed on task.
function reload_offers_tables() {
    var av_offers_tables = ['.table-offers', '.table-rel-offers'];
    $.each(av_offers_tables, function (i, selector) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().ajax.reload(null, false);
        }
    });
}
