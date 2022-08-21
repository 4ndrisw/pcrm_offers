<?php

use modules\offers\services\offers\OffersPipeline;

defined('BASEPATH') or exit('No direct script access allowed');

class Offers extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('offers_model');
        $this->load->model('currencies_model');
    }

    public function index($offer_id = '')
    {
        $this->list_offers($offer_id);
    }

    public function list_offers($offer_id = '')
    {
        close_setup_menu();

        if (!has_permission('offers', '', 'view') && !has_permission('offers', '', 'view_own') && get_option('allow_staff_view_estimates_assigned') == 0) {
            access_denied('offers');
        }

        $isPipeline = $this->session->userdata('offers_pipeline') == 'true';

        if ($isPipeline && !$this->input->get('status')) {
            $data['title']           = _l('offers_pipeline');
            $data['bodyclass']       = 'offers-pipeline';
            $data['switch_pipeline'] = false;
            // Direct access
            if (is_numeric($offer_id)) {
                $data['offerid'] = $offer_id;
            } else {
                $data['offerid'] = $this->session->flashdata('offerid');
            }

            $this->load->view('admin/offers/pipeline/manage', $data);
        } else {

            // Pipeline was initiated but user click from home page and need to show table only to filter
            if ($this->input->get('status') && $isPipeline) {
                $this->pipeline(0, true);
            }

            $data['offer_id']           = $offer_id;
            $data['switch_pipeline']       = true;
            $data['title']                 = _l('offers');
            $data['statuses']              = $this->offers_model->get_statuses();
            $data['offers_sale_agents'] = $this->offers_model->get_sale_agents();
            $data['years']                 = $this->offers_model->get_offers_years();
            $this->load->view('admin/offers/manage', $data);
        }
    }

    public function table()
    {
        if (
            !has_permission('offers', '', 'view')
            && !has_permission('offers', '', 'view_own')
            && get_option('allow_staff_view_offers_assigned') == 0
        ) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('offers', 'tables/offers'));
        
    }

    public function offer_relations($rel_id, $rel_type)
    {
        $this->app->get_table_data(module_views_path('offers', 'tables/proposals_relations', [
            'rel_id'   => $rel_id,
            'rel_type' => $rel_type,
        ]));
    }

    public function delete_attachment($id)
    {
        $file = $this->misc_model->get_file($id);
        if ($file->staffid == get_staff_user_id() || is_admin()) {
            echo $this->offers_model->delete_attachment($id);
        } else {
            ajax_access_denied();
        }
    }

    public function clear_signature($id)
    {
        if (has_permission('offers', '', 'delete')) {
            $this->offers_model->clear_signature($id);
        }

        redirect(admin_url('offers/list_offers/' . $id));
    }

    public function sync_data()
    {
        if (has_permission('offers', '', 'create') || has_permission('offers', '', 'edit')) {
            $has_permission_view = has_permission('offers', '', 'view');

            $this->db->where('rel_id', $this->input->post('rel_id'));
            $this->db->where('rel_type', $this->input->post('rel_type'));

            if (!$has_permission_view) {
                $this->db->where('addedfrom', get_staff_user_id());
            }

            $address = trim($this->input->post('address'));
            $address = nl2br($address);
            $this->db->update(db_prefix() . 'offers', [
                'phone'   => $this->input->post('phone'),
                'zip'     => $this->input->post('zip'),
                'country' => $this->input->post('country'),
                'state'   => $this->input->post('state'),
                'address' => $address,
                'city'    => $this->input->post('city'),
            ]);

            if ($this->db->affected_rows() > 0) {
                echo json_encode([
                    'message' => _l('all_data_synced_successfully'),
                ]);
            } else {
                echo json_encode([
                    'message' => _l('sync_offers_up_to_date'),
                ]);
            }
        }
    }

    public function offer($id = '')
    {
        if ($this->input->post()) {
            $offer_data = $this->input->post();
            if ($id == '') {
                if (!has_permission('offers', '', 'create')) {
                    access_denied('offers');
                }
                $id = $this->offers_model->add($offer_data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('offer')));
                    if ($this->set_offer_pipeline_autoload($id)) {
                        redirect(admin_url('offers'));
                    } else {
                        redirect(admin_url('offers/list_offers/' . $id));
                    }
                }
            } else {
                if (!has_permission('offers', '', 'edit')) {
                    access_denied('offers');
                }
                $success = $this->offers_model->update($offer_data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('offer')));
                }
                if ($this->set_offer_pipeline_autoload($id)) {
                    redirect(admin_url('offers'));
                } else {
                    redirect(admin_url('offers/list_offers/' . $id));
                }
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('offer_lowercase'));
        } else {
            $data['offer'] = $this->offers_model->get($id);

            if (!$data['offer'] || !user_can_view_offer($id)) {
                blank_page(_l('offer_not_found'));
            }

            $data['estimate']    = $data['offer'];
            $data['is_offer'] = true;
            $title               = _l('edit', _l('offer_lowercase'));
        }

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();
        $this->load->model('invoice_items_model');
        $data['ajaxItems'] = false;
        if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
            $data['items'] = $this->invoice_items_model->get_grouped();
        } else {
            $data['items']     = [];
            $data['ajaxItems'] = true;
        }
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $data['statuses']      = $this->offers_model->get_statuses();
        $data['staff']         = $this->staff_model->get('', ['active' => 1]);
        $data['currencies']    = $this->currencies_model->get();
        $data['base_currency'] = $this->currencies_model->get_base_currency();

        $data['title'] = $title;
        $this->load->view('admin/offers/offer', $data);
    }

    public function get_template()
    {
        $name = $this->input->get('name');
        echo $this->load->view('admin/offers/templates/' . $name, [], true);
    }

    public function send_expiry_reminder($id)
    {
        $canView = user_can_view_offer($id);
        if (!$canView) {
            access_denied('offers');
        } else {
            if (!has_permission('offers', '', 'view') && !has_permission('offers', '', 'view_own') && $canView == false) {
                access_denied('offers');
            }
        }

        $success = $this->offers_model->send_expiry_reminder($id);
        if ($success) {
            set_alert('success', _l('sent_expiry_reminder_success'));
        } else {
            set_alert('danger', _l('sent_expiry_reminder_fail'));
        }
        if ($this->set_offer_pipeline_autoload($id)) {
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('offers/list_offers/' . $id));
        }
    }

    public function clear_acceptance_info($id)
    {
        if (is_admin()) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'offers', get_acceptance_info_array(true));
        }

        redirect(admin_url('offers/list_offers/' . $id));
    }

    public function pdf($id)
    {
        if (!$id) {
            redirect(admin_url('offers'));
        }

        $canView = user_can_view_offer($id);
        if (!$canView) {
            access_denied('offers');
        } else {
            if (!has_permission('offers', '', 'view') && !has_permission('offers', '', 'view_own') && $canView == false) {
                access_denied('offers');
            }
        }

        $offer = $this->offers_model->get($id);

        try {
            $pdf = offer_pdf($offer);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $offer_number = format_offer_number($id);
        $pdf->Output($offer_number . '.pdf', $type);
    }

    public function get_offer_data_ajax($id, $to_return = false)
    {
        if (!has_permission('offers', '', 'view') && !has_permission('offers', '', 'view_own') && get_option('allow_staff_view_offers_assigned') == 0) {
            echo _l('access_denied');
            die;
        }

        $offer = $this->offers_model->get($id, [], true);

        if (!$offer || !user_can_view_offer($id)) {
            echo _l('offer_not_found');
            die;
        }

        //$this->app_mail_template->set_rel_id($offer->id);
        //$data = prepare_mail_preview_data('offer_send_to_customer', $offer->email);

        $merge_fields = [];

        $merge_fields[] = [
            [
                'name' => 'Items Table',
                'key'  => '{offer_items}',
            ],
        ];

        $merge_fields = array_merge($merge_fields, $this->app_merge_fields->get_flat('offers', 'other', '{email_signature}'));

        $data['offer_statuses']     = $this->offers_model->get_statuses();
        $data['members']               = $this->staff_model->get('', ['active' => 1]);
        $data['offer_merge_fields'] = $merge_fields;
        $data['offer']              = $offer;
        $data['totalNotes']            = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'offer']);
        log_activity($to_return);
        if ($to_return == false) {
            $this->load->view('admin/offers/offers_preview_template', $data);
        } else {
            return $this->load->view('admin/offers/offers_preview_template', $data, true);
        }
    }

    public function add_note($rel_id)
    {
        if ($this->input->post() && user_can_view_offer($rel_id)) {
            $this->misc_model->add_note($this->input->post(), 'offer', $rel_id);
            echo $rel_id;
        }
    }

    public function get_notes($id)
    {
        if (user_can_view_offer($id)) {
            $data['notes'] = $this->misc_model->get_notes($id, 'offer');
            $this->load->view('admin/includes/sales_notes_template', $data);
        }
    }

    public function convert_to_estimate($id)
    {
        if (!has_permission('estimates', '', 'create')) {
            access_denied('estimates');
        }
        if ($this->input->post()) {
            $this->load->model('estimates_model');
            $estimate_id = $this->estimates_model->add($this->input->post());
            if ($estimate_id) {
                set_alert('success', _l('offer_converted_to_estimate_success'));
                $this->db->where('id', $id);
                $this->db->update(db_prefix() . 'offers', [
                    'estimate_id' => $estimate_id,
                    'status'      => 3,
                ]);
                log_activity('Offer Converted to Estimate [EstimateID: ' . $estimate_id . ', OfferID: ' . $id . ']');

                hooks()->do_action('offer_converted_to_estimate', ['offer_id' => $id, 'estimate_id' => $estimate_id]);

                redirect(admin_url('estimates/estimate/' . $estimate_id));
            } else {
                set_alert('danger', _l('offer_converted_to_estimate_fail'));
            }
            if ($this->set_offer_pipeline_autoload($id)) {
                redirect(admin_url('offers'));
            } else {
                redirect(admin_url('offers/list_offers/' . $id));
            }
        }
    }

    public function convert_to_invoice($id)
    {
        if (!has_permission('invoices', '', 'create')) {
            access_denied('invoices');
        }
        if ($this->input->post()) {
            $this->load->model('invoices_model');
            $invoice_id = $this->invoices_model->add($this->input->post());
            if ($invoice_id) {
                set_alert('success', _l('offer_converted_to_invoice_success'));
                $this->db->where('id', $id);
                $this->db->update(db_prefix() . 'offers', [
                    'invoice_id' => $invoice_id,
                    'status'     => 3,
                ]);
                log_activity('Offer Converted to Invoice [InvoiceID: ' . $invoice_id . ', OfferID: ' . $id . ']');
                hooks()->do_action('offer_converted_to_invoice', ['offer_id' => $id, 'invoice_id' => $invoice_id]);
                redirect(admin_url('invoices/invoice/' . $invoice_id));
            } else {
                set_alert('danger', _l('offer_converted_to_invoice_fail'));
            }
            if ($this->set_offer_pipeline_autoload($id)) {
                redirect(admin_url('offers'));
            } else {
                redirect(admin_url('offers/list_offers/' . $id));
            }
        }
    }

    public function get_invoice_convert_data($id)
    {
        $this->load->model('payment_modes_model');
        $data['payment_modes'] = $this->payment_modes_model->get('', [
            'expenses_only !=' => 1,
        ]);
        $this->load->model('taxes_model');
        $data['taxes']         = $this->taxes_model->get();
        $data['currencies']    = $this->currencies_model->get();
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $this->load->model('invoice_items_model');
        $data['ajaxItems'] = false;
        if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
            $data['items'] = $this->invoice_items_model->get_grouped();
        } else {
            $data['items']     = [];
            $data['ajaxItems'] = true;
        }
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $data['staff']          = $this->staff_model->get('', ['active' => 1]);
        $data['offer']       = $this->offers_model->get($id);
        $data['billable_tasks'] = [];
        $data['add_items']      = $this->_parse_items($data['offer']);

        if ($data['offer']->rel_type == 'lead') {
            $this->db->where('leadid', $data['offer']->rel_id);
            $data['customer_id'] = $this->db->get(db_prefix() . 'clients')->row()->userid;
        } else {
            $data['customer_id'] = $data['offer']->rel_id;
        }
        $data['custom_fields_rel_transfer'] = [
            'belongs_to' => 'offer',
            'rel_id'     => $id,
        ];
        $this->load->view('admin/offers/invoice_convert_template', $data);
    }

    public function get_estimate_convert_data($id)
    {
        $this->load->model('taxes_model');
        $data['taxes']         = $this->taxes_model->get();
        $data['currencies']    = $this->currencies_model->get();
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $this->load->model('invoice_items_model');
        $data['ajaxItems'] = false;
        if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
            $data['items'] = $this->invoice_items_model->get_grouped();
        } else {
            $data['items']     = [];
            $data['ajaxItems'] = true;
        }
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $data['staff']     = $this->staff_model->get('', ['active' => 1]);
        $data['offer']  = $this->offers_model->get($id);
        $data['add_items'] = $this->_parse_items($data['offer']);

        $this->load->model('estimates_model');
        $data['estimate_statuses'] = $this->estimates_model->get_statuses();
        if ($data['offer']->rel_type == 'lead') {
            $this->db->where('leadid', $data['offer']->rel_id);
            $data['customer_id'] = $this->db->get(db_prefix() . 'clients')->row()->userid;
        } else {
            $data['customer_id'] = $data['offer']->rel_id;
        }

        $data['custom_fields_rel_transfer'] = [
            'belongs_to' => 'offer',
            'rel_id'     => $id,
        ];

        $this->load->view('admin/offers/estimate_convert_template', $data);
    }

    private function _parse_items($offer)
    {
        $items = [];
        foreach ($offer->items as $item) {
            $taxnames = [];
            $taxes    = get_offer_item_taxes($item['id']);
            foreach ($taxes as $tax) {
                array_push($taxnames, $tax['taxname']);
            }
            $item['taxname']        = $taxnames;
            $item['parent_item_id'] = $item['id'];
            $item['id']             = 0;
            $items[]                = $item;
        }

        return $items;
    }

    /* Send offer to email */
    public function send_to_email($id)
    {
        $canView = user_can_view_offer($id);
        if (!$canView) {
            access_denied('offers');
        } else {
            if (!has_permission('offers', '', 'view') && !has_permission('offers', '', 'view_own') && $canView == false) {
                access_denied('offers');
            }
        }

        if ($this->input->post()) {
            try {
                $success = $this->offers_model->send_offer_to_email(
                    $id,
                    $this->input->post('attach_pdf'),
                    $this->input->post('cc')
                );
            } catch (Exception $e) {
                $message = $e->getMessage();
                echo $message;
                if (strpos($message, 'Unable to get the size of the image') !== false) {
                    show_pdf_unable_to_get_image_size_error();
                }
                die;
            }

            if ($success) {
                set_alert('success', _l('offer_sent_to_email_success'));
            } else {
                set_alert('danger', _l('offer_sent_to_email_fail'));
            }

            if ($this->set_offer_pipeline_autoload($id)) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                redirect(admin_url('offers/list_offers/' . $id));
            }
        }
    }

    public function copy($id)
    {
        if (!has_permission('offers', '', 'create')) {
            access_denied('offers');
        }
        $new_id = $this->offers_model->copy($id);
        if ($new_id) {
            set_alert('success', _l('offer_copy_success'));
            $this->set_offer_pipeline_autoload($new_id);
            redirect(admin_url('offers/offer/' . $new_id));
        } else {
            set_alert('success', _l('offer_copy_fail'));
        }
        if ($this->set_offer_pipeline_autoload($id)) {
            redirect(admin_url('offers'));
        } else {
            redirect(admin_url('offers/list_offers/' . $id));
        }
    }

    public function mark_action_status($status, $id)
    {
        if (!has_permission('offers', '', 'edit')) {
            access_denied('offers');
        }
        $success = $this->offers_model->mark_action_status($status, $id);
        if ($success) {
            set_alert('success', _l('offer_status_changed_success'));
        } else {
            set_alert('danger', _l('offer_status_changed_fail'));
        }
        if ($this->set_offer_pipeline_autoload($id)) {
            redirect(admin_url('offers'));
        } else {
            redirect(admin_url('offers/list_offers/' . $id));
        }
    }

    public function delete($id)
    {
        if (!has_permission('offers', '', 'delete')) {
            access_denied('offers');
        }
        $response = $this->offers_model->delete($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('offer')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('offer_lowercase')));
        }
        redirect(admin_url('offers'));
    }

    public function get_relation_data_values($rel_id, $rel_type)
    {
        echo json_encode($this->offers_model->get_relation_data_values($rel_id, $rel_type));
    }

    public function add_offer_comment()
    {
        if ($this->input->post()) {
            echo json_encode([
                'success' => $this->offers_model->add_comment($this->input->post()),
            ]);
        }
    }

    public function edit_comment($id)
    {
        if ($this->input->post()) {
            echo json_encode([
                'success' => $this->offers_model->edit_comment($this->input->post(), $id),
                'message' => _l('comment_updated_successfully'),
            ]);
        }
    }

    public function get_offer_comments($id)
    {
        $data['comments'] = $this->offers_model->get_comments($id);
        $this->load->view('admin/offers/comments_template', $data);
    }

    public function remove_comment($id)
    {
        $this->db->where('id', $id);
        $comment = $this->db->get(db_prefix() . 'offer_comments')->row();
        if ($comment) {
            if ($comment->staffid != get_staff_user_id() && !is_admin()) {
                echo json_encode([
                    'success' => false,
                ]);
                die;
            }
            echo json_encode([
                'success' => $this->offers_model->remove_comment($id),
            ]);
        } else {
            echo json_encode([
                'success' => false,
            ]);
        }
    }

    public function save_offer_data()
    {
        if (!has_permission('offers', '', 'edit') && !has_permission('offers', '', 'create')) {
            header('HTTP/1.0 400 Bad error');
            echo json_encode([
                'success' => false,
                'message' => _l('access_denied'),
            ]);
            die;
        }
        $success = false;
        $message = '';

        $this->db->where('id', $this->input->post('offer_id'));
        $this->db->update(db_prefix() . 'offers', [
            'content' => html_purify($this->input->post('content', false)),
        ]);

        $success = $this->db->affected_rows() > 0;
        $message = _l('updated_successfully', _l('offer'));

        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
    }

    // Pipeline
    public function pipeline($set = 0, $manual = false)
    {
        if ($set == 1) {
            $set = 'true';
        } else {
            $set = 'false';
        }
        $this->session->set_userdata([
            'offers_pipeline' => $set,
        ]);
        if ($manual == false) {
            redirect(admin_url('offers'));
        }
    }

    public function pipeline_open($id)
    {
        if (has_permission('offers', '', 'view') || has_permission('offers', '', 'view_own') || get_option('allow_staff_view_offers_assigned') == 1) {
            $data['offer']      = $this->get_offer_data_ajax($id, true);
            $data['offer_data'] = $this->offers_model->get($id);
            $this->load->view('admin/offers/pipeline/offer', $data);
        }
    }

    public function update_pipeline()
    {
        if (has_permission('offers', '', 'edit')) {
            $this->offers_model->update_pipeline($this->input->post());
        }
    }

    public function get_pipeline()
    {
        if (has_permission('offers', '', 'view') || has_permission('offers', '', 'view_own') || get_option('allow_staff_view_offers_assigned') == 1) {
            $data['statuses'] = $this->offers_model->get_statuses();
            $this->load->view('admin/offers/pipeline/pipeline', $data);
        }
    }

    public function pipeline_load_more()
    {
        $status = $this->input->get('status');
        $page   = $this->input->get('page');

        $offers = (new OffersPipeline($status))
        ->search($this->input->get('search'))
        ->sortBy(
            $this->input->get('sort_by'),
            $this->input->get('sort')
        )
        ->page($page)->get();

        foreach ($offers as $offer) {
            $this->load->view('admin/offers/pipeline/_kanban_card', [
                'offer' => $offer,
                'status'   => $status,
            ]);
        }
    }

    public function set_offer_pipeline_autoload($id)
    {
        if ($id == '') {
            return false;
        }

        if ($this->session->has_userdata('offers_pipeline') && $this->session->userdata('offers_pipeline') == 'true') {
            $this->session->set_flashdata('offerid', $id);

            return true;
        }

        return false;
    }

    public function get_due_date()
    {
        if ($this->input->post()) {
            $date    = $this->input->post('date');
            $duedate = '';
            if (get_option('offer_due_after') != 0) {
                $date    = to_sql_date($date);
                $d       = date('Y-m-d', strtotime('+' . get_option('offer_due_after') . ' DAY', strtotime($date)));
                $duedate = _d($d);
                echo $duedate;
            }
        }
    }
}
