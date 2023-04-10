<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if ($offer['status'] == $status) { ?>
<li data-offer-id="<?php echo $offer['id']; ?>" class="<?php if($offer['invoice_id'] != NULL || $offer['offer_id'] != NULL){echo 'not-sortable';} ?>">
   <div class="panel-body">
      <div class="row">
         <div class="col-md-12">
            <h4 class="bold pipeline-heading">
               <a href="<?php echo admin_url('offers/list_offers/'.$offer['id']); ?>" data-toggle="tooltip" data-title="<?php echo $offer['subject']; ?>" onclick="offer_pipeline_open(<?php echo $offer['id']; ?>); return false;"><?php echo format_offer_number($offer['id']); ?></a>
               <?php if(has_permission('offers','','edit')){ ?>
               <a href="<?php echo admin_url('offers/offer/'.$offer['id']); ?>" target="_blank" class="pull-right"><small><i class="fa fa-pencil-square-o" aria-hidden="true"></i></small></a>
               <?php } ?>
            </h4>
            <span class="mbot10 inline-block full-width">
            <?php
               if($offer['rel_type'] == 'lead'){
                 echo '<a href="'.admin_url('leads/index/'.$offer['rel_id']).'" onclick="init_lead('.$offer['rel_id'].'); return false;" data-toggle="tooltip" data-title="'._l('lead').'">' .$offer['offer_to'].'</a><br />';
               } else if($offer['rel_type'] == 'customer'){
                 echo '<a href="'.admin_url('clients/client/'.$offer['rel_id']).'" data-toggle="tooltip" data-title="'._l('client').'">' .$offer['offer_to'].'</a><br />';
               }
               ?>
            </span>
         </div>
         <div class="col-md-12">
            <div class="row">
               <div class="col-md-8">
                  <?php if($offer['total'] != 0){
                     ?>
                  <span class="bold"><?php echo _l('offer_total'); ?>:
                     <?php echo app_format_money($offer['total'], get_currency($offer['currency'])); ?>
                  </span>
                  <br />
                  <?php } ?>
                  <?php echo _l('offer_date'); ?>: <?php echo _d($offer['date']); ?>
                  <?php if(is_date($offer['open_till'])){ ?>
                  <br />
                  <?php echo _l('offer_open_till'); ?>: <?php echo _d($offer['open_till']); ?>
                  <?php } ?>
                  <br />
               </div>
               <div class="col-md-4 text-right">
                  <small><i class="fa fa-comments" aria-hidden="true"></i> <?php echo _l('offer_comments'); ?>: <?php echo total_rows(db_prefix().'offer_comments', array(
                     'offerid' => $offer['id']
                     )); ?></small>
               </div>
               <?php $tags = get_tags_in($offer['id'],'offer');
                  if(count($tags) > 0){ ?>
               <div class="col-md-12">
                  <div class="mtop5 kanban-tags">
                     <?php echo render_tags($tags); ?>
                  </div>
               </div>
               <?php } ?>
            </div>
         </div>
      </div>
   </div>
</li>
<?php } ?>
