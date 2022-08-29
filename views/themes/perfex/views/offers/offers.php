<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading section-offers">
  <div class="panel-body">
    <h4 class="no-margin section-text"><?php echo _l('offers'); ?></h4>
  </div>
</div>
<div class="panel_s">
  <div class="panel-body">
    <table class="table dt-table table-offers" data-order-col="3" data-order-type="desc">
      <thead>
        <tr>
          <th class="th-offer-number"><?php echo _l('offer') . ' #'; ?></th>
          <th class="th-offer-subject"><?php echo _l('offer_subject'); ?></th>
          <th class="th-offer-total"><?php echo _l('offer_total'); ?></th>
          <th class="th-offer-open-till"><?php echo _l('offer_open_till'); ?></th>
          <th class="th-offer-date"><?php echo _l('offer_date'); ?></th>
          <th class="th-offer-status"><?php echo _l('offer_status'); ?></th>
          <?php
          $custom_fields = get_custom_fields('offer',array('show_on_client_portal'=>1));
          foreach($custom_fields as $field){ ?>
            <th><?php echo $field['name']; ?></th>
          <?php } ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($offers as $offer){ ?>
          <tr>
            <td>
              <a href="<?php echo site_url('offer/'.$offer['id'].'/'.$offer['hash']); ?>" class="td-offer-url">
                <?php echo format_offer_number($offer['id']); ?>
                <?php
                if ($offer['invoice_id']) {
                  echo '<br /><span class="text-success offer-invoiced">' . _l('estimate_invoiced') . '</span>';
                }
                ?>
              </a>
              <td>
                <a href="<?php echo site_url('offer/'.$offer['id'].'/'.$offer['hash']); ?>" class="td-offer-url-subject">
                  <?php echo $offer['subject']; ?>
                </a>
                <?php
                if ($offer['invoice_id'] != NULL) {
                  $invoice = $this->invoices_model->get($offer['invoice_id']);
                  echo '<br /><a href="' . site_url('invoice/' . $invoice->id . '/' . $invoice->hash) . '" target="_blank" class="td-offer-invoice-url">' . format_invoice_number($invoice->id) . '</a>';
                } else if ($offer['estimate_id'] != NULL) {
                  $estimate = $this->estimates_model->get($offer['estimate_id']);
                  echo '<br /><a href="' . site_url('estimate/' . $estimate->id . '/' . $estimate->hash) . '" target="_blank" class="td-offer-estimate-url">' . format_estimate_number($estimate->id) . '</a>';
                }
                ?>
              </td>
              <td data-order="<?php echo $offer['total']; ?>">
                <?php
                if ($offer['currency'] != 0) {
                 echo app_format_money($offer['total'], get_currency($offer['currency']));
               } else {
                 echo app_format_money($offer['total'], get_base_currency());
               }
               ?>
             </td>
             <td data-order="<?php echo $offer['open_till']; ?>"><?php echo _d($offer['open_till']); ?></td>
             <td data-order="<?php echo $offer['date']; ?>"><?php echo _d($offer['date']); ?></td>
             <td><?php echo format_offer_status($offer['status']); ?></td>
             <?php foreach($custom_fields as $field){ ?>
               <td><?php echo get_custom_field_value($offer['id'],$field['id'],'offer'); ?></td>
             <?php } ?>
           </tr>
         <?php } ?>
       </tbody>
     </table>
   </div>
 </div>
