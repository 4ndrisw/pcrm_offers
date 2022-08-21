<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Offer extends ClientsController
{
    public function index($id, $hash)
    {
        check_offer_restrictions($id, $hash);
        $offer = $this->offers_model->get($id);

        if ($offer->rel_type == 'customer' && !is_client_logged_in()) {
            load_client_language($offer->rel_id);
        } else if($offer->rel_type == 'lead') {
            load_lead_language($offer->rel_id);
        }

        $identity_confirmation_enabled = get_option('offer_accept_identity_confirmation');
        if ($this->input->post()) {
            $action = $this->input->post('action');
            switch ($action) {
                case 'offer_pdf':

                    $offer_number = format_offer_number($id);
                    $companyname     = get_option('invoice_company_name');
                    if ($companyname != '') {
                        $offer_number .= '-' . mb_strtoupper(slug_it($companyname), 'UTF-8');
                    }

                    try {
                        $pdf = offer_pdf($offer);
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        die;
                    }

                    $pdf->Output($offer_number . '.pdf', 'D');

                    break;
                case 'offer_comment':
                    // comment is blank
                    if (!$this->input->post('content')) {
                        redirect($this->uri->uri_string());
                    }
                    $data               = $this->input->post();
                    $data['offerid'] = $id;
                    $this->offers_model->add_comment($data, true);
                    redirect($this->uri->uri_string() . '?tab=discussion');

                    break;
                case 'accept_offer':
                    $success = $this->offers_model->mark_action_status(3, $id, true);
                    if ($success) {
                        process_digital_signature_image($this->input->post('signature', false), PROPOSAL_ATTACHMENTS_FOLDER . $id);

                        $this->db->where('id', $id);
                        $this->db->update(db_prefix().'offers', get_acceptance_info_array());
                        redirect($this->uri->uri_string(), 'refresh');
                    }

                    break;
                case 'decline_offer':
                    $success = $this->offers_model->mark_action_status(2, $id, true);
                    if ($success) {
                        redirect($this->uri->uri_string(), 'refresh');
                    }

                    break;
            }
        }

        $number_word_lang_rel_id = 'unknown';
        if ($offer->rel_type == 'customer') {
            $number_word_lang_rel_id = $offer->rel_id;
        }
        $this->load->library('app_number_to_word', [
            'clientid' => $number_word_lang_rel_id,
        ],'numberword');

        $this->disableNavigation();
        $this->disableSubMenu();

        $data['title']     = $offer->subject;
        $data['offer']  = hooks()->apply_filters('offer_html_pdf_data', $offer);
        $data['bodyclass'] = 'offer offer-view';

        $data['identity_confirmation_enabled'] = $identity_confirmation_enabled;
        if ($identity_confirmation_enabled == '1') {
            $data['bodyclass'] .= ' identity-confirmation';
        }

        $this->app_scripts->theme('sticky-js','assets/plugins/sticky/sticky.js');

        $data['comments'] = $this->offers_model->get_comments($id);
        add_views_tracking('offer', $id);
        hooks()->do_action('offer_html_viewed', $id);
        $this->app_css->remove('reset-css','customers-area-default');
        $data                      = hooks()->apply_filters('offer_customers_area_view_data', $data);
        no_index_customers_area();
        $this->data($data);
        $this->view('viewoffer');
        $this->layout();
    }
}
