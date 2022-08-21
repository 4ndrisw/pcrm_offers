<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(LIBSPATH . 'pdf/App_pdf.php');

class Schedule_pdf extends App_pdf
{
    protected $schedule;

    private $schedule_number;

    public function __construct($schedule, $tag = '')
    {
        $this->load_language($schedule->clientid);

        $schedule                = hooks()->apply_filters('schedule_html_pdf_data', $schedule);
        $GLOBALS['schedule_pdf'] = $schedule;

        parent::__construct();

        $this->tag             = $tag;
        $this->schedule        = $schedule;
        $this->schedule_number = format_schedule_number($this->schedule->id);

        $this->SetTitle($this->schedule_number);
    }

    public function prepare()
    {

        $this->set_view_vars([
            'status'          => $this->schedule->status,
            'schedule_number' => $this->schedule_number,
            'schedule'        => $this->schedule,
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'schedule';
    }

    protected function file_path()
    {
        $customPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/my_schedulepdf.php';
        $actualPath = module_views_path('schedules','themes/' . active_clients_theme() . '/views/schedules/schedulepdf.php');

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
