<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(LIBSPATH . 'pdf/App_pdf.php');

class Offer_pdf extends App_pdf
{
    protected $offer;

    private $offer_number;

    public function __construct($offer, $tag = '')
    {
        $this->load_language($offer->clientid);

        $offer                = hooks()->apply_filters('offer_html_pdf_data', $offer);
        $GLOBALS['offer_pdf'] = $offer;

        parent::__construct();

        $this->tag             = $tag;
        $this->offer        = $offer;
        $this->offer_number = format_offer_number($this->offer->id);

        $this->SetTitle($this->offer_number);
    }

    public function prepare()
    {

        $this->set_view_vars([
            'status'          => $this->offer->status,
            'offer_number' => $this->offer_number,
            'offer'        => $this->offer,
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'offer';
    }

    protected function file_path()
    {
        $customPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/my_offerpdf.php';
        $actualPath = module_views_path('offers','themes/' . active_clients_theme() . '/views/offers/offerpdf.php');

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
