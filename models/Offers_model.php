<?php

use app\services\AbstractKanban;
use app\services\offers\OffersPipeline;

defined('BASEPATH') or exit('No direct script access allowed');

class Offers_model extends App_Model
{
    private $statuses;

    private $copy = false;

    public function __construct()
    {
        parent::__construct();
        $this->statuses = hooks()->apply_filters('before_set_offer_statuses', [
            6,
            4,
            1,
            5,
            2,
            3,
        ]);
    }

    public function get_statuses()
    {
        return $this->statuses;
    }

    public function get_sale_agents()
    {
        return $this->db->query('SELECT DISTINCT(assigned) as sale_agent FROM ' . db_prefix() . 'offers WHERE assigned != 0')->result_array();
    }

    public function get_offers_years()
    {
        return $this->db->query('SELECT DISTINCT(YEAR(date)) as year FROM ' . db_prefix() . 'offers')->result_array();
    }

    /**
     * Inserting new offer function
     * @param mixed $data $_POST data
     */
    public function add($data)
    {
        $data['allow_comments'] = isset($data['allow_comments']) ? 1 : 0;

        $save_and_send = isset($data['save_and_send']);

        $tags = isset($data['tags']) ? $data['tags'] : '';

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }

        $estimateRequestID = false;
        if (isset($data['estimate_request_id'])) {
            $estimateRequestID = $data['estimate_request_id'];
            unset($data['estimate_request_id']);
        }

        $data['address'] = trim($data['address']);
        $data['address'] = nl2br($data['address']);

        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['addedfrom']   = get_staff_user_id();
        $data['hash']        = app_generate_hash();

        if (empty($data['rel_type'])) {
            unset($data['rel_type']);
            unset($data['rel_id']);
        } else {
            if (empty($data['rel_id'])) {
                unset($data['rel_type']);
                unset($data['rel_id']);
            }
        }

        $items = [];
        if (isset($data['newitems'])) {
            $items = $data['newitems'];
            unset($data['newitems']);
        }

        if ($this->copy == false) {
            $data['content'] = '{offer_items}';
        }

        $hook = hooks()->apply_filters('before_create_offer', [
            'data'  => $data,
            'items' => $items,
        ]);

        $data  = $hook['data'];
        $items = $hook['items'];

        $this->db->insert(db_prefix() . 'offers', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            if ($estimateRequestID !== false && $estimateRequestID != '') {
                $this->load->model('estimate_request_model');
                $completedStatus = $this->estimate_request_model->get_status_by_flag('completed');
                $this->estimate_request_model->update_request_status([
                    'requestid' => $estimateRequestID,
                    'status'    => $completedStatus->id,
                ]);
            }

            if (isset($custom_fields)) {
                handle_custom_fields_post($insert_id, $custom_fields);
            }

            handle_tags_save($tags, $insert_id, 'offer');

            foreach ($items as $key => $item) {
                if ($itemid = add_new_sales_item_post($item, $insert_id, 'offer')) {
                    _maybe_insert_post_item_tax($itemid, $item, $insert_id, 'offer');
                }
            }

            $offer = $this->get($insert_id);
            if ($offer->assigned != 0) {
                if ($offer->assigned != get_staff_user_id()) {
                    $notified = add_notification([
                        'description'     => 'not_offer_assigned_to_you',
                        'touserid'        => $offer->assigned,
                        'fromuserid'      => get_staff_user_id(),
                        'link'            => 'offers/list_offers/' . $insert_id,
                        'additional_data' => serialize([
                            $offer->subject,
                        ]),
                    ]);
                    if ($notified) {
                        pusher_trigger_notification([$offer->assigned]);
                    }
                }
            }

            if ($data['rel_type'] == 'lead') {
                $this->load->model('leads_model');
                $this->leads_model->log_lead_activity($data['rel_id'], 'not_lead_activity_created_offer', false, serialize([
                    '<a href="' . admin_url('offers/list_offers/' . $insert_id) . '" target="_blank">' . $data['subject'] . '</a>',
                ]));
            }

            update_sales_total_tax_column($insert_id, 'offer', db_prefix() . 'offers');

            log_activity('New Offer Created [ID: ' . $insert_id . ']');

            if ($save_and_send === true) {
                $this->send_offer_to_email($insert_id);
            }

            hooks()->do_action('offer_created', $insert_id);

            return $insert_id;
        }

        return false;
    }

    /**
     * Update offer
     * @param  mixed $data $_POST data
     * @param  mixed $id   offer id
     * @return boolean
     */
    public function update($data, $id)
    {
        $affectedRows = 0;

        $data['allow_comments'] = isset($data['allow_comments']) ? 1 : 0;

        $current_offer = $this->get($id);

        $save_and_send = isset($data['save_and_send']);

        if (empty($data['rel_type'])) {
            $data['rel_id']   = null;
            $data['rel_type'] = '';
        } else {
            if (empty($data['rel_id'])) {
                $data['rel_id']   = null;
                $data['rel_type'] = '';
            }
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }

        $items = [];
        if (isset($data['items'])) {
            $items = $data['items'];
            unset($data['items']);
        }

        $newitems = [];
        if (isset($data['newitems'])) {
            $newitems = $data['newitems'];
            unset($data['newitems']);
        }

        if (isset($data['tags'])) {
            if (handle_tags_save($data['tags'], $id, 'offer')) {
                $affectedRows++;
            }
        }

        $data['address'] = trim($data['address']);
        $data['address'] = nl2br($data['address']);

        $hook = hooks()->apply_filters('before_offer_updated', [
            'data'          => $data,
            'items'         => $items,
            'newitems'      => $newitems,
            'removed_items' => isset($data['removed_items']) ? $data['removed_items'] : [],
        ], $id);

        $data                  = $hook['data'];
        $data['removed_items'] = $hook['removed_items'];
        $newitems              = $hook['newitems'];
        $items                 = $hook['items'];

        // Delete items checked to be removed from database
        foreach ($data['removed_items'] as $remove_item_id) {
            if (handle_removed_sales_item_post($remove_item_id, 'offer')) {
                $affectedRows++;
            }
        }

        unset($data['removed_items']);
        unset($data['tags']);
        unset($data['item_select']);
        unset($data['description']);
        unset($data['long_description']);
        unset($data['quantity']);
        unset($data['unit']);
        unset($data['rate']);
        unset($data['taxname']);




        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'offers', $data);
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
            $offer_now = $this->get($id);
            if ($current_offer->assigned != $offer_now->assigned) {
                if ($offer_now->assigned != get_staff_user_id()) {
                    $notified = add_notification([
                        'description'     => 'not_offer_assigned_to_you',
                        'touserid'        => $offer_now->assigned,
                        'fromuserid'      => get_staff_user_id(),
                        'link'            => 'offers/list_offers/' . $id,
                        'additional_data' => serialize([
                            $offer_now->subject,
                        ]),
                    ]);
                    if ($notified) {
                        pusher_trigger_notification([$offer_now->assigned]);
                    }
                }
            }
        }

        foreach ($items as $key => $item) {
            if (update_sales_item_post($item['itemid'], $item)) {
                $affectedRows++;
            }

            if (isset($item['custom_fields'])) {
                if (handle_custom_fields_post($item['itemid'], $item['custom_fields'])) {
                    $affectedRows++;
                }
            }

            if (!isset($item['taxname']) || (isset($item['taxname']) && count($item['taxname']) == 0)) {
                if (delete_taxes_from_item($item['itemid'], 'offer')) {
                    $affectedRows++;
                }
            } else {
                $item_taxes        = get_offer_item_taxes($item['itemid']);
                $_item_taxes_names = [];
                foreach ($item_taxes as $_item_tax) {
                    array_push($_item_taxes_names, $_item_tax['taxname']);
                }
                $i = 0;
                foreach ($_item_taxes_names as $_item_tax) {
                    if (!in_array($_item_tax, $item['taxname'])) {
                        $this->db->where('id', $item_taxes[$i]['id'])
                        ->delete(db_prefix() . 'item_tax');
                        if ($this->db->affected_rows() > 0) {
                            $affectedRows++;
                        }
                    }
                    $i++;
                }
                if (_maybe_insert_post_item_tax($item['itemid'], $item, $id, 'offer')) {
                    $affectedRows++;
                }
            }
        }

        foreach ($newitems as $key => $item) {
            if ($new_item_added = add_new_sales_item_post($item, $id, 'offer')) {
                _maybe_insert_post_item_tax($new_item_added, $item, $id, 'offer');
                $affectedRows++;
            }
        }

        if ($affectedRows > 0) {
            update_sales_total_tax_column($id, 'offer', db_prefix() . 'offers');
            log_activity('Offer Updated [ID:' . $id . ']');
        }

        if ($save_and_send === true) {
            $this->send_offer_to_email($id);
        }

        if ($affectedRows > 0) {
            hooks()->do_action('after_offer_updated', $id);

            return true;
        }

        return false;
    }

    /**
     * Get offers
     * @param  mixed $id offer id OPTIONAL
     * @return mixed
     */
    public function get($id = '', $where = [], $for_editor = false)
    {
        $this->db->where($where);

        if (is_client_logged_in()) {
            $this->db->where('status !=', 0);
        }

        $this->db->select('*,' . db_prefix() . 'currencies.id as currencyid, ' . db_prefix() . 'offers.id as id, ' . db_prefix() . 'currencies.name as currency_name');
        $this->db->from(db_prefix() . 'offers');
        $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'offers.currency', 'left');

        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'offers.id', $id);
            $offer = $this->db->get()->row();
            if ($offer) {
                $offer->attachments                           = $this->get_attachments($id);
                $offer->items                                 = get_items_by_type('offer', $id);
                $offer->visible_attachments_to_customer_found = false;
                foreach ($offer->attachments as $attachment) {
                    if ($attachment['visible_to_customer'] == 1) {
                        $offer->visible_attachments_to_customer_found = true;

                        break;
                    }
                }
                /*
                 *next_feature
                if ($for_editor == false) {
                    $offer = parse_offer_content_merge_fields($offer);
                }
                */
            }

            $offer->client = $this->clients_model->get($offer->clientid);

            if (!$offer->client) {
                $offer->client          = new stdClass();
                $offer->client->company = $offer->deleted_customer_name;
            }
            
            return $offer;
        }

        return $this->db->get()->result_array();
    }

    public function clear_signature($id)
    {
        $this->db->select('signature');
        $this->db->where('id', $id);
        $offer = $this->db->get(db_prefix() . 'offers')->row();

        if ($offer) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'offers', ['signature' => null]);

            if (!empty($offer->signature)) {
                unlink(get_upload_path_by_type('offer') . $id . '/' . $offer->signature);
            }

            return true;
        }

        return false;
    }

    public function update_pipeline($data)
    {
        $this->mark_action_status($data['status'], $data['offerid']);
        AbstractKanban::updateOrder($data['order'], 'pipeline_order', 'offers', $data['status']);
    }

    public function get_attachments($offer_id, $id = '')
    {
        // If is passed id get return only 1 attachment
        if (is_numeric($id)) {
            $this->db->where('id', $id);
        } else {
            $this->db->where('rel_id', $offer_id);
        }
        $this->db->where('rel_type', 'offer');
        $result = $this->db->get(db_prefix() . 'files');
        if (is_numeric($id)) {
            return $result->row();
        }

        return $result->result_array();
    }

    /**
     *  Delete offer attachment
     * @param   mixed $id  attachmentid
     * @return  boolean
     */
    public function delete_attachment($id)
    {
        $attachment = $this->get_attachments('', $id);
        $deleted    = false;
        if ($attachment) {
            if (empty($attachment->external)) {
                unlink(get_upload_path_by_type('offer') . $attachment->rel_id . '/' . $attachment->file_name);
            }
            $this->db->where('id', $attachment->id);
            $this->db->delete(db_prefix() . 'files');
            if ($this->db->affected_rows() > 0) {
                $deleted = true;
                log_activity('Offer Attachment Deleted [ID: ' . $attachment->rel_id . ']');
            }
            if (is_dir(get_upload_path_by_type('offer') . $attachment->rel_id)) {
                // Check if no attachments left, so we can delete the folder also
                $other_attachments = list_files(get_upload_path_by_type('offer') . $attachment->rel_id);
                if (count($other_attachments) == 0) {
                    // okey only index.html so we can delete the folder also
                    delete_dir(get_upload_path_by_type('offer') . $attachment->rel_id);
                }
            }
        }

        return $deleted;
    }

    /**
     * Add offer comment
     * @param mixed  $data   $_POST comment data
     * @param boolean $client is request coming from the client side
     */
    public function add_comment($data, $client = false)
    {
        if (is_staff_logged_in()) {
            $client = false;
        }

        if (isset($data['action'])) {
            unset($data['action']);
        }
        $data['dateadded'] = date('Y-m-d H:i:s');
        if ($client == false) {
            $data['staffid'] = get_staff_user_id();
        }
        $data['content'] = nl2br($data['content']);
        $this->db->insert(db_prefix() . 'offer_comments', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            $offer = $this->get($data['offerid']);

            // No notifications client when offer is with draft status
            if ($offer->status == '6' && $client == false) {
                return true;
            }

            if ($client == true) {
                // Get creator and assigned
                $this->db->select('staffid,email,phonenumber');
                $this->db->where('staffid', $offer->addedfrom);
                $this->db->or_where('staffid', $offer->assigned);
                $staff_offer = $this->db->get(db_prefix() . 'staff')->result_array();
                $notifiedUsers  = [];
                foreach ($staff_offer as $member) {
                    $notified = add_notification([
                        'description'     => 'not_offer_comment_from_client',
                        'touserid'        => $member['staffid'],
                        'fromcompany'     => 1,
                        'fromuserid'      => 0,
                        'link'            => 'offers/list_offers/' . $data['offerid'],
                        'additional_data' => serialize([
                            $offer->subject,
                        ]),
                    ]);

                    if ($notified) {
                        array_push($notifiedUsers, $member['staffid']);
                    }

                    $template     = mail_template('offer_comment_to_staff', $offer->id, $member['email']);
                    $merge_fields = $template->get_merge_fields();
                    $template->send();
                    // Send email/sms to admin that client commented
                    $this->app_sms->trigger(SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_STAFF, $member['phonenumber'], $merge_fields);
                }
                pusher_trigger_notification($notifiedUsers);
            } else {
                // Send email/sms to client that admin commented
                $template     = mail_template('offer_comment_to_customer', $offer);
                $merge_fields = $template->get_merge_fields();
                $template->send();
                $this->app_sms->trigger(SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_CUSTOMER, $offer->phone, $merge_fields);
            }

            return true;
        }

        return false;
    }

    public function edit_comment($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'offer_comments', [
            'content' => nl2br($data['content']),
        ]);
        if ($this->db->affected_rows() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Get offer comments
     * @param  mixed $id offer id
     * @return array
     */
    public function get_comments($id)
    {
        $this->db->where('offerid', $id);
        $this->db->order_by('dateadded', 'ASC');

        return $this->db->get(db_prefix() . 'offer_comments')->result_array();
    }

    /**
     * Get offer single comment
     * @param  mixed $id  comment id
     * @return object
     */
    public function get_comment($id)
    {
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'offer_comments')->row();
    }

    /**
     * Remove offer comment
     * @param  mixed $id comment id
     * @return boolean
     */
    public function remove_comment($id)
    {
        $comment = $this->get_comment($id);
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'offer_comments');
        if ($this->db->affected_rows() > 0) {
            log_activity('Offer Comment Removed [OfferID:' . $comment->offerid . ', Comment Content: ' . $comment->content . ']');

            return true;
        }

        return false;
    }

    /**
     * Copy offer
     * @param  mixed $id offer id
     * @return mixed
     */
    public function copy($id)
    {
        $this->copy      = true;
        $offer        = $this->get($id, [], true);
        $not_copy_fields = [
            'addedfrom',
            'id',
            'datecreated',
            'hash',
            'status',
            'invoice_id',
            'estimate_id',
            'is_expiry_notified',
            'date_converted',
            'signature',
            'acceptance_firstname',
            'acceptance_lastname',
            'acceptance_email',
            'acceptance_date',
            'acceptance_ip',
        ];
        $fields      = $this->db->list_fields(db_prefix() . 'offers');
        $insert_data = [];
        foreach ($fields as $field) {
            if (!in_array($field, $not_copy_fields)) {
                $insert_data[$field] = $offer->$field;
            }
        }

        $insert_data['addedfrom']   = get_staff_user_id();
        $insert_data['datecreated'] = date('Y-m-d H:i:s');
        $insert_data['date']        = _d(date('Y-m-d'));
        $insert_data['status']      = 6;
        $insert_data['hash']        = app_generate_hash();

        // in case open till is expired set new 7 days starting from current date
        if ($insert_data['open_till'] && get_option('offer_due_after') != 0) {
            $insert_data['open_till'] = _d(date('Y-m-d', strtotime('+' . get_option('offer_due_after') . ' DAY', strtotime(date('Y-m-d')))));
        }

        $insert_data['newitems'] = [];
        $custom_fields_items     = get_custom_fields('items');
        $key                     = 1;
        foreach ($offer->items as $item) {
            $insert_data['newitems'][$key]['description']      = $item['description'];
            $insert_data['newitems'][$key]['long_description'] = clear_textarea_breaks($item['long_description']);
            $insert_data['newitems'][$key]['qty']              = $item['qty'];
            $insert_data['newitems'][$key]['unit']             = $item['unit'];
            $insert_data['newitems'][$key]['taxname']          = [];
            $taxes                                             = get_offer_item_taxes($item['id']);
            foreach ($taxes as $tax) {
                // tax name is in format TAX1|10.00
                array_push($insert_data['newitems'][$key]['taxname'], $tax['taxname']);
            }
            $insert_data['newitems'][$key]['rate']  = $item['rate'];
            $insert_data['newitems'][$key]['order'] = $item['item_order'];
            foreach ($custom_fields_items as $cf) {
                $insert_data['newitems'][$key]['custom_fields']['items'][$cf['id']] = get_custom_field_value($item['id'], $cf['id'], 'items', false);

                if (!defined('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST')) {
                    define('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST', true);
                }
            }
            $key++;
        }

        $id = $this->add($insert_data);

        if ($id) {
            $custom_fields = get_custom_fields('offer');
            foreach ($custom_fields as $field) {
                $value = get_custom_field_value($offer->id, $field['id'], 'offer', false);
                if ($value == '') {
                    continue;
                }
                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $id,
                    'fieldid' => $field['id'],
                    'fieldto' => 'offer',
                    'value'   => $value,
                ]);
            }

            $tags = get_tags_in($offer->id, 'offer');
            handle_tags_save($tags, $id, 'offer');

            log_activity('Copied Offer ' . format_offer_number($offer->id));

            return $id;
        }

        return false;
    }

    /**
     * Take offer action (change status) manually
     * @param  mixed $status status id
     * @param  mixed  $id     offer id
     * @param  boolean $client is request coming from client side or not
     * @return boolean
     */
    public function mark_action_status($status, $id, $client = false)
    {
        $original_offer = $this->get($id);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'offers', [
            'status' => $status,
        ]);

        if ($this->db->affected_rows() > 0) {
            // Client take action
            if ($client == true) {
                $revert = false;
                // Declined
                if ($status == 2) {
                    $message = 'not_offer_offer_declined';
                } elseif ($status == 3) {
                    $message = 'not_offer_offer_accepted';
                // Accepted
                } else {
                    $revert = true;
                }
                // This is protection that only 3 and 4 statuses can be taken as action from the client side
                if ($revert == true) {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'offers', [
                        'status' => $original_offer->status,
                    ]);

                    return false;
                }

                // Get creator and assigned;
                $this->db->where('staffid', $original_offer->addedfrom);
                $this->db->or_where('staffid', $original_offer->assigned);
                $staff_offer = $this->db->get(db_prefix() . 'staff')->result_array();
                $notifiedUsers  = [];
                foreach ($staff_offer as $member) {
                    $notified = add_notification([
                            'fromcompany'     => true,
                            'touserid'        => $member['staffid'],
                            'description'     => $message,
                            'link'            => 'offers/list_offers/' . $id,
                            'additional_data' => serialize([
                                format_offer_number($id),
                            ]),
                        ]);
                    if ($notified) {
                        array_push($notifiedUsers, $member['staffid']);
                    }
                }

                pusher_trigger_notification($notifiedUsers);

                // Send thank you to the customer email template
                if ($status == 3) {
                    foreach ($staff_offer as $member) {
                        send_mail_template('offer_accepted_to_staff', $original_offer, $member['email']);
                    }

                    send_mail_template('offer_accepted_to_customer', $original_offer);

                    hooks()->do_action('offer_accepted', $id);
                } else {

                    // Client declined send template to admin
                    foreach ($staff_offer as $member) {
                        send_mail_template('offer_declined_to_staff', $original_offer, $member['email']);
                    }

                    hooks()->do_action('offer_declined', $id);
                }
            } else {
                // in case admin mark as open the the open till date is smaller then current date set open till date 7 days more
                if ((date('Y-m-d', strtotime($original_offer->open_till)) < date('Y-m-d')) && $status == 1) {
                    $open_till = date('Y-m-d', strtotime('+7 DAY', strtotime(date('Y-m-d'))));
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'offers', [
                        'open_till' => $open_till,
                    ]);
                }
            }

            log_activity('Offer Status Changes [OfferID:' . $id . ', Status:' . format_offer_status($status, '', false) . ',Client Action: ' . (int) $client . ']');

            return true;
        }

        return false;
    }

    /**
     * Delete offer
     * @param  mixed $id offer id
     * @return boolean
     */
    public function delete($id)
    {
        $this->clear_signature($id);
        $offer = $this->get($id);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'offers');
        if ($this->db->affected_rows() > 0) {
            if (!is_null($offer->short_link)) {
                app_archive_short_link($offer->short_link);
            }

            delete_tracked_emails($id, 'offer');

            $this->db->where('offerid', $id);
            $this->db->delete(db_prefix() . 'offer_comments');
            // Get related tasks
            $this->db->where('rel_type', 'offer');
            $this->db->where('rel_id', $id);

            $tasks = $this->db->get(db_prefix() . 'tasks')->result_array();
            foreach ($tasks as $task) {
                $this->tasks_model->delete_task($task['id']);
            }

            $attachments = $this->get_attachments($id);
            foreach ($attachments as $attachment) {
                $this->delete_attachment($attachment['id']);
            }

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'offer');
            $this->db->delete(db_prefix() . 'notes');

            $this->db->where('relid IN (SELECT id from ' . db_prefix() . 'itemable WHERE rel_type="offer" AND rel_id="' . $this->db->escape_str($id) . '")');
            $this->db->where('fieldto', 'items');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'offer');
            $this->db->delete(db_prefix() . 'itemable');


            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'offer');
            $this->db->delete(db_prefix() . 'item_tax');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'offer');
            $this->db->delete(db_prefix() . 'taggables');

            // Delete the custom field values
            $this->db->where('relid', $id);
            $this->db->where('fieldto', 'offer');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $this->db->where('rel_type', 'offer');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'reminders');

            $this->db->where('rel_type', 'offer');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'views_tracking');

            log_activity('Offer Deleted [OfferID:' . $id . ']');

            return true;
        }

        return false;
    }

    /**
     * Get relation offer data. Ex lead or customer will return the necesary db fields
     * @param  mixed $rel_id
     * @param  string $rel_type customer/lead
     * @return object
     */
    public function get_relation_data_values($rel_id, $rel_type)
    {
        $data = new StdClass();
        if ($rel_type == 'customer') {
            $this->db->where('userid', $rel_id);
            $_data = $this->db->get(db_prefix() . 'clients')->row();

            $primary_contact_id = get_primary_contact_user_id($rel_id);

            if ($primary_contact_id) {
                $contact     = $this->clients_model->get_contact($primary_contact_id);
                $data->email = $contact->email;
            }

            $data->phone            = $_data->phonenumber;
            $data->is_using_company = false;
            if (isset($contact)) {
                $data->to = $contact->firstname . ' ' . $contact->lastname;
            } else {
                if (!empty($_data->company)) {
                    $data->to               = $_data->company;
                    $data->is_using_company = true;
                }
            }
            $data->company = $_data->company;
            $data->address = clear_textarea_breaks($_data->address);
            $data->zip     = $_data->zip;
            $data->country = $_data->country;
            $data->state   = $_data->state;
            $data->city    = $_data->city;

            $default_currency = $this->clients_model->get_customer_default_currency($rel_id);
            if ($default_currency != 0) {
                $data->currency = $default_currency;
            }
        } elseif ($rel_type = 'lead') {
            $this->db->where('id', $rel_id);
            $_data       = $this->db->get(db_prefix() . 'leads')->row();
            $data->phone = $_data->phonenumber;

            $data->is_using_company = false;

            if (empty($_data->company)) {
                $data->to = $_data->name;
            } else {
                $data->to               = $_data->company;
                $data->is_using_company = true;
            }

            $data->company = $_data->company;
            $data->address = $_data->address;
            $data->email   = $_data->email;
            $data->zip     = $_data->zip;
            $data->country = $_data->country;
            $data->state   = $_data->state;
            $data->city    = $_data->city;
        }

        return $data;
    }

    /**
     * Sent offer to email
     * @param  mixed  $id        offerid
     * @param  string  $template  email template to sent
     * @param  boolean $attachpdf attach offer pdf or not
     * @return boolean
     */
    public function send_expiry_reminder($id)
    {
        $offer = $this->get($id);

        // For all cases update this to prevent sending multiple reminders eq on fail
        $this->db->where('id', $offer->id);
        $this->db->update(db_prefix() . 'offers', [
            'is_expiry_notified' => 1,
        ]);

        $template     = mail_template('offer_expiration_reminder', $offer);
        $merge_fields = $template->get_merge_fields();

        $template->send();

        if (can_send_sms_based_on_creation_date($offer->datecreated)) {
            $sms_sent = $this->app_sms->trigger(SMS_TRIGGER_PROPOSAL_EXP_REMINDER, $offer->phone, $merge_fields);
        }

        return true;
    }

    public function send_offer_to_email($id, $attachpdf = true, $cc = '')
    {
        // Offer status is draft update to sent
        if (total_rows(db_prefix() . 'offers', ['id' => $id, 'status' => 6]) > 0) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'offers', ['status' => 4]);
        }

        $offer = $this->get($id);

        $sent = send_mail_template('offer_send_to_customer', $offer, $attachpdf, $cc);

        if ($sent) {

            // Set to status sent
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'offers', [
                'status' => 4,
            ]);

            hooks()->do_action('offer_sent', $id);

            return true;
        }

        return false;
    }

    public function do_kanban_query($status, $search = '', $page = 1, $sort = [], $count = false)
    {
        _deprecated_function('Offer_model::do_kanban_query', '2.9.2', 'OffersPipeline class');

        $kanBan = (new OffersPipeline($status))
            ->search($search)
            ->page($page)
            ->sortBy($sort['sort'] ?? null, $sort['sort_by'] ?? null);

        if ($count) {
            return $kanBan->countAll();
        }

        return $kanBan->get();
    }
}
