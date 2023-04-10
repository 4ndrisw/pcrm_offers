// Init single offer
function init_offer(id) {
    load_small_table_item(id, '#offer', 'offer_id', 'offers/get_offer_data_ajax', '.table-offers');
}

/*
if ($("body").hasClass('offers-pipeline')) {
    var offer_id = $('input[name="offerid"]').val();
    offer_pipeline_open(offer_id);
}
*/


function add_offer_comment() {
    var comment = $('#comment').val();
    if (comment == '') {
        return;
    }
    var data = {};
    data.content = comment;
    data.offerid = offer_id;
    $('body').append('<div class="dt-loader"></div>');
    $.post(admin_url + 'offers/add_offer_comment', data).done(function (response) {
        response = JSON.parse(response);
        $('body').find('.dt-loader').remove();
        if (response.success == true) {
            $('#comment').val('');
            get_offer_comments();
        }
    });
}

function get_offer_comments() {
    if (typeof (offer_id) == 'undefined') {
        return;
    }
    requestGet('offers/get_offer_comments/' + offer_id).done(function (response) {
        $('body').find('#offer-comments').html(response);
        update_comments_count('offer')
    });
}

function remove_offer_comment(commentid) {
    if (confirm_delete()) {
        requestGetJSON('offers/remove_comment/' + commentid).done(function (response) {
            if (response.success == true) {
                $('[data-commentid="' + commentid + '"]').remove();
                update_comments_count('offer')
            }
        });
    }
}

function edit_offer_comment(id) {
    var content = $('body').find('[data-offer-comment-edit-textarea="' + id + '"] textarea').val();
    if (content != '') {
        $.post(admin_url + 'offers/edit_comment/' + id, {
            content: content
        }).done(function (response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
                $('body').find('[data-offer-comment="' + id + '"]').html(nl2br(content));
            }
        });
        toggle_offer_comment_edit(id);
    }
}

function toggle_offer_comment_edit(id) {
    $('body').find('[data-offer-comment="' + id + '"]').toggleClass('hide');
    $('body').find('[data-offer-comment-edit-textarea="' + id + '"]').toggleClass('hide');
}

function offer_convert_template(invoker) {
    var template = $(invoker).data('template');
    var html_helper_selector;
    if (template == 'offer') {
        html_helper_selector = 'offer';
    } else if (template == 'invoice') {
        html_helper_selector = 'invoice';
    } else {
        return false;
    }

    requestGet('offers/get_' + html_helper_selector + '_convert_data/' + offer_id).done(function (data) {
        if ($('.offer-pipeline-modal').is(':visible')) {
            $('.offer-pipeline-modal').modal('hide');
        }
        $('#convert_helper').html(data);
        $('#convert_to_' + html_helper_selector).modal({
            show: true,
            backdrop: 'static'
        });
        reorder_items();
    });

}

function save_offer_content(manual) {
    var editor = tinyMCE.activeEditor;
    var data = {};
    data.offer_id = offer_id;
    data.content = editor.getContent();
    $.post(admin_url + 'offers/save_offer_data', data).done(function (response) {
        response = JSON.parse(response);
        if (typeof (manual) != 'undefined') {
            // Show some message to the user if saved via CTRL + S
            alert_float('success', response.message);
        }
        // Invokes to set dirty to false
        editor.save();
    }).fail(function (error) {
        var response = JSON.parse(error.responseText);
        alert_float('danger', response.message);
    });
}

// Proposal sync data in case eq mail is changed, shown for lead and customers.
function sync_offers_data(rel_id, rel_type) {
    var data = {};
    var modal_sync = $('#sync_data_offer_data');
    data.country = modal_sync.find('select[name="country"]').val();
    data.zip = modal_sync.find('input[name="zip"]').val();
    data.state = modal_sync.find('input[name="state"]').val();
    data.city = modal_sync.find('input[name="city"]').val();
    data.address = modal_sync.find('textarea[name="address"]').val();
    data.phone = modal_sync.find('input[name="phone"]').val();
    data.rel_id = rel_id;
    data.rel_type = rel_type;
    $.post(admin_url + 'offers/sync_data', data).done(function (response) {
        response = JSON.parse(response);
        alert_float('success', response.message);
        modal_sync.modal('hide');
    });
}


// Delete offer attachment
function delete_offer_attachment(id) {
    if (confirm_delete()) {
        requestGet('offers/delete_attachment/' + id).done(function (success) {
            if (success == 1) {
                var rel_id = $("body").find('input[name="_attachment_sale_id"]').val();
                $("body").find('[data-attachment-id="' + id + '"]').remove();
                $("body").hasClass('offers-pipeline') ? offer_pipeline_open(rel_id) : init_offer(rel_id);
            }
        }).fail(function (error) {
            alert_float('danger', error.responseText);
        });
    }
}

// Used when offer is updated from pipeline. eq changed order or moved to another status
function offers_pipeline_update(ui, object) {
    if (object === ui.item.parent()[0]) {
        var data = {
            offerid: $(ui.item).attr('data-offer-id'),
            status: $(ui.item.parent()[0]).attr('data-status-id'),
            order: [],
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
                offer_pipeline();
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
        client_id: {
            required: {
                depends: function () {
                    var customerRemoved = $('select#client_id').hasClass('customer-removed');
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
