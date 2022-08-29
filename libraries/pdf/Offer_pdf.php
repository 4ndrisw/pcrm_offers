<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(LIBSPATH . 'pdf/App_pdf.php');

class Offer_pdf extends App_pdf
{
    protected $offer;

    private $offer_number;

    public function __construct($offer, $tag = '')
    {
        if ($offer->rel_id != null && $offer->rel_type == 'customer') {
            $this->load_language($offer->rel_id);
        } else if ($offer->rel_id != null && $offer->rel_type == 'lead') {
            $CI = &get_instance();

            $this->load_language($offer->rel_id);
            $CI->db->select('default_language')->where('id', $offer->rel_id);
            $language = $CI->db->get('leads')->row()->default_language;

            load_pdf_language($language);
        }

        $offer                = hooks()->apply_filters('offer_html_pdf_data', $offer);
        $GLOBALS['offer_pdf'] = $offer;

        parent::__construct();

        $this->tag      = $tag;
        $this->offer = $offer;

        $this->offer_number = format_offer_number($this->offer->id);

        $this->SetTitle($this->offer_number);
        $this->SetDisplayMode('default', 'OneColumn');

        # Don't remove these lines - important for the PDF layout
        $this->offer->content = $this->fix_editor_html($this->offer->content);
    }

    public function prepare()
    {
        $number_word_lang_rel_id = 'unknown';

        if ($this->offer->rel_type == 'customer') {
            $number_word_lang_rel_id = $this->offer->rel_id;
        }

        $this->with_number_to_word($number_word_lang_rel_id);

        $total = '';
        if ($this->offer->total != 0) {
            $total = app_format_money($this->offer->total, get_currency($this->offer->currency));
            $total = _l('offer_total') . ': ' . $total;
        }

        $this->set_view_vars([
            'number'       => $this->offer_number,
            'offer'     => $this->offer,
            'total'        => $total,
            'offer_url' => site_url('offer/' . $this->offer->id . '/' . $this->offer->hash),
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'offer';
    }

    protected function file_path()
    {
        $filePath = 'my_offerpdf.php';
        $customPath = module_views_path('offers','themes/' . active_clients_theme() . '/views/offers/' . $filePath);
        $actualPath = module_views_path('offers','themes/' . active_clients_theme() . '/views/offers/offerpdf.php');

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
