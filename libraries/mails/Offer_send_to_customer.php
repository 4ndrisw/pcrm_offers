<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Offer_send_to_customer extends App_mail_template
{
    protected $for = 'customer';

    protected $offer;

    protected $contact;

    public $slug = 'offer-send-to-client';

    public $rel_type = 'offer';

    public function __construct($offer, $contact, $cc = '')
    {
        parent::__construct();

        $this->offer = $offer;
        $this->contact = $contact;
        $this->cc      = $cc;
    }

    public function build()
    {
        if ($this->ci->input->post('email_attachments')) {
            $_other_attachments = $this->ci->input->post('email_attachments');
            foreach ($_other_attachments as $attachment) {
                $_attachment = $this->ci->offers_model->get_attachments($this->offer->id, $attachment);
                $this->add_attachment([
                                'attachment' => get_upload_path_by_type('offer') . $this->offer->id . '/' . $_attachment->file_name,
                                'filename'   => $_attachment->file_name,
                                'type'       => $_attachment->filetype,
                                'read'       => true,
                            ]);
            }
        }

        $this->to($this->contact->email)
        ->set_rel_id($this->offer->id)
        ->set_merge_fields('client_merge_fields', $this->offer->client_id, $this->contact->id)
        ->set_merge_fields('offer_merge_fields', $this->offer->id);
    }
}
