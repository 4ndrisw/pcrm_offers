<?php

defined('BASEPATH') or exit('No direct script access allowed');

$baseCurrency = get_base_currency();

$aColumns = [
    db_prefix() . 'offers.id',
    'subject',
    'offer_to',
    'total',
    'date',
    'open_till',
    '(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'offers.id and rel_type="offer" ORDER by tag_order ASC) as tags',
    'datecreated',
    'status',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'offers';

$where  = [];
$filter = [];

if ($this->ci->input->post('leads_related')) {
    array_push($filter, 'OR rel_type="lead"');
}
if ($this->ci->input->post('customers_related')) {
    array_push($filter, 'OR rel_type="customer"');
}
if ($this->ci->input->post('expired')) {
    array_push($filter, 'OR open_till IS NOT NULL AND open_till <"' . date('Y-m-d') . '" AND status NOT IN(2,3)');
}

$statuses  = $this->ci->offers_model->get_statuses();
$statusIds = [];

foreach ($statuses as $status) {
    if ($this->ci->input->post('offers_' . $status)) {
        array_push($statusIds, $status);
    }
}
if (count($statusIds) > 0) {
    array_push($filter, 'AND status IN (' . implode(', ', $statusIds) . ')');
}

$agents    = $this->ci->offers_model->get_sale_agents();
$agentsIds = [];
foreach ($agents as $agent) {
    if ($this->ci->input->post('sale_agent_' . $agent['sale_agent'])) {
        array_push($agentsIds, $agent['sale_agent']);
    }
}
if (count($agentsIds) > 0) {
    array_push($filter, 'AND assigned IN (' . implode(', ', $agentsIds) . ')');
}

$years      = $this->ci->offers_model->get_offers_years();
$yearsArray = [];
foreach ($years as $year) {
    if ($this->ci->input->post('year_' . $year['year'])) {
        array_push($yearsArray, $year['year']);
    }
}
if (count($yearsArray) > 0) {
    array_push($filter, 'AND YEAR(date) IN (' . implode(', ', $yearsArray) . ')');
}

if (count($filter) > 0) {
    array_push($where, 'AND (' . prepare_dt_filter($filter) . ')');
}

if (!has_permission('offers', '', 'view')) {
    array_push($where, 'AND ' . get_offers_sql_where_staff(get_staff_user_id()));
}

$join          = [];
$custom_fields = get_table_custom_fields('offer');

foreach ($custom_fields as $key => $field) {
    $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);

    array_push($customFieldsColumns, $selectAs);
    array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
    array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' ON ' . db_prefix() . 'offers.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
}

$aColumns = hooks()->apply_filters('offers_table_sql_columns', $aColumns);

// Fix for big queries. Some hosting have max_join_limit
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'currency',
    'rel_id',
    'rel_type',
    'invoice_id',
    'hash',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    //$numberOutput = '<a href="' . admin_url('offers/list_offers/' . $aRow[db_prefix() . 'offers.id']. '#' . $aRow[db_prefix() . 'offers.id']) . '" onclick="init_offer(' . $aRow[db_prefix() . 'offers.id'] . '); return false;">' . format_offer_number($aRow[db_prefix() . 'offers.id']) . '</a>';
    //$numberOutput = '<a href="' . admin_url('offers#' . $aRow[db_prefix() . 'offers.id']) . '" target="_blank">' . format_offer_number($aRow[db_prefix() . 'offers.id']) . ' AA</a>';
    //$numberOutput = '<a href="' . admin_url('offers/list_offers/' . $aRow[db_prefix() . 'offers.id']. '#' . $aRow[db_prefix() . 'offers.id']) . '" target="_blank">' . format_offer_number($aRow[db_prefix() . 'offers.id']) . '</a>';
    //$numberOutput = '<a href="' . admin_url('offers/list_offers/' . $aRow[db_prefix() . 'offers.id']. '#' . $aRow[db_prefix() . 'offers.id']) . '">' . format_offer_number($aRow[db_prefix() . 'offers.id']) . '</a>';



    // If is from client area table
    $numberOutput = '<a href="' . admin_url('offers/list_offers/' . $aRow[db_prefix() . 'offers.id']. '#' . $aRow[db_prefix() . 'offers.id']) . '" onclick="init_offer(' . $aRow[db_prefix() . 'offers.id'] . '); return false;">' . format_offer_number($aRow[db_prefix() . 'offers.id']) . '</a>';

    $numberOutput .= '<div class="row-options">';

    $numberOutput .= '<a href="' . site_url('offers/show/' . $aRow[db_prefix() . 'offers.id'] . '/' . $aRow['hash']) . '" target="_blank">' . _l('view') . '</a>';
    if (has_permission('offers', '', 'edit')) {
        $numberOutput .= ' | <a href="' . admin_url('offers/offer/' . $aRow[db_prefix() . 'offers.id']) . '">' . _l('edit') . '</a>';
    }
    $numberOutput .= '</div>';

    $row[] = $numberOutput;

    $row[] = '<a href="' . admin_url('offers/list_offers/' . $aRow[db_prefix() . 'offers.id']) . '" onclick="init_offer(' . $aRow[db_prefix() . 'offers.id'] . '); return false;">' . $aRow['subject'] . ' bb</a>';

    if ($aRow['rel_type'] == 'lead') {
        $toOutput = '<a href="#" onclick="init_lead(' . $aRow['rel_id'] . ');return false;" target="_blank" data-toggle="tooltip" data-title="' . _l('lead') . '">' . $aRow['offer_to'] . '</a>';
    } elseif ($aRow['rel_type'] == 'customer') {
        $toOutput = '<a href="' . admin_url('clients/client/' . $aRow['rel_id']) . '" target="_blank" data-toggle="tooltip" data-title="' . _l('client') . '">' . $aRow['offer_to'] . '</a>';
    }

    $row[] = $toOutput;

    $amount = app_format_money($aRow['total'], ($aRow['currency'] != 0 ? get_currency($aRow['currency']) : $baseCurrency));

    if ($aRow['invoice_id']) {
        $amount .= '<br /> <span class="hide"> - </span><span class="text-success">' . _l('offer_invoiced') . '</span>';
    }

    $row[] = $amount;


    $row[] = _d($aRow['date']);

    $row[] = _d($aRow['open_till']);

    $row[] = render_tags($aRow['tags']);

    $row[] = _d($aRow['datecreated']);

    $row[] = format_offer_status($aRow['status']);

    // Custom fields add values
    foreach ($customFieldsColumns as $customFieldColumn) {
        $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
    }

    $row['DT_RowClass'] = 'has-row-options';

    $row = hooks()->apply_filters('offers_table_row_data', $row, $aRow);

    $output['aaData'][] = $row;
}
