<?php

defined('BASEPATH') or exit('No direct script access allowed');

$dimensions = $pdf->getPageDimensions();

$info_right_column = '';
$info_left_column  = '';

$info_right_column .= '<span style="font-weight:bold;font-size:27px;">' . _l('offer_pdf_heading') . '</span><br />';
$info_right_column .= '<b style="color:#4e4e4e;"># ' . format_offer_number($offer->id) . '</b>';

if (get_option('show_status_on_pdf_ei') == 1) {
    $info_right_column .= '<br /><span style="color:rgb(' . offer_status_color_pdf($offer->status) . ');text-transform:uppercase;">' . format_offer_status($offer->status, '', false) . '</span>';
}

// Add logo
$info_left_column .= pdf_logo_url();
// Write top left logo and right column info/text
pdf_multi_row($info_left_column, $info_right_column, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);

$pdf->ln(10);

$organization_info = '<div style="color:#424242;">';
    $organization_info .= format_organization_info();
$organization_info .= '</div>';

// Estimate to
$offer_info = '<b>' . _l('offer_to') . '</b>';
$offer_info .= '<div style="color:#424242;">';
$offer_info .= format_offer_info($offer, 'offer');
$offer_info .= '</div>';

$offer_info .= '<br />' . _l('offer_data_date') . ': ' . _d($offer->date) . '<br />';

if (!empty($offer->open_till)) {
    $offer_info .= _l('offer_data_expiry_date') . ': ' . _d($offer->open_till) . '<br />';
}

if (!empty($offer->reference_no)) {
    $offer_info .= _l('reference_no') . ': ' . $offer->reference_no . '<br />';
}



foreach ($pdf_custom_fields as $field) {
    $value = get_custom_field_value($offer->id, $field['id'], 'offer');
    if ($value == '') {
        continue;
    }
    $offer_info .= $field['name'] . ': ' . $value . '<br />';
}

$left_info  = $swap == '1' ? $offer_info : $organization_info;
$right_info = $swap == '1' ? $organization_info : $offer_info;

pdf_multi_row($left_info, $right_info, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);

// The Table
$pdf->Ln(hooks()->apply_filters('pdf_info_and_table_separator', 6));

// The items table
$items = get_items_table_data($offer, 'offer', 'pdf');

$tblhtml = $items->table();

$pdf->writeHTML($tblhtml, true, false, false, false, '');

$pdf->Ln(8);
$tbltotal = '';
$tbltotal .= '<table cellpadding="6" style="font-size:' . ($font_size + 4) . 'px">';
$tbltotal .= '
<tr>
    <td align="right" width="85%"><strong>' . _l('offer_subtotal') . '</strong></td>
    <td align="right" width="15%">' . app_format_money($offer->subtotal, $offer->currency_name) . '</td>
</tr>';

if (is_sale_discount_applied($offer)) {
    $tbltotal .= '
    <tr>
        <td align="right" width="85%"><strong>' . _l('offer_discount');
    if (is_sale_discount($offer, 'percent')) {
        $tbltotal .= ' (' . app_format_number($offer->discount_percent, true) . '%)';
    }
    $tbltotal .= '</strong>';
    $tbltotal .= '</td>';
    $tbltotal .= '<td align="right" width="15%">-' . app_format_money($offer->discount_total, $offer->currency_name) . '</td>
    </tr>';
}

foreach ($items->taxes() as $tax) {
    $tbltotal .= '<tr>
    <td align="right" width="85%"><strong>' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)' . '</strong></td>
    <td align="right" width="15%">' . app_format_money($tax['total_tax'], $offer->currency_name) . '</td>
</tr>';
}

if ((int)$offer->adjustment != 0) {
    $tbltotal .= '<tr>
    <td align="right" width="85%"><strong>' . _l('offer_adjustment') . '</strong></td>
    <td align="right" width="15%">' . app_format_money($offer->adjustment, $offer->currency_name) . '</td>
</tr>';
}

$tbltotal .= '
<tr style="background-color:#f0f0f0;">
    <td align="right" width="85%"><strong>' . _l('offer_total') . '</strong></td>
    <td align="right" width="15%">' . app_format_money($offer->total, $offer->currency_name) . '</td>
</tr>';

$tbltotal .= '</table>';

$pdf->writeHTML($tbltotal, true, false, false, false, '');

if (get_option('total_to_words_enabled') == 1) {
    // Set the font bold
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->writeHTMLCell('', '', '', '', _l('num_word') . ': ' . $CI->numberword->convert($offer->total, $offer->currency_name), 0, 1, false, true, 'C', true);
    // Set the font again to normal like the rest of the pdf
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(4);
}

if (!empty($offer->clientnote)) {
    $pdf->Ln(4);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('offer_note'), 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $pdf->writeHTMLCell('', '', '', '', $offer->clientnote, 0, 1, false, true, 'L', true);
}

if (!empty($offer->terms)) {
    $pdf->Ln(4);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('terms_and_conditions') . ":", 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $pdf->writeHTMLCell('', '', '', '', $offer->terms, 0, 1, false, true, 'L', true);
}
