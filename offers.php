<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: offers
Description: Default module for defining offers
Version: 1.0.1
Requires at least: 2.3.*
*/

define('OFFERS_MODULE_NAME', 'offers');
define('OFFER_ATTACHMENTS_FOLDER', FCPATH . 'uploads/offers/');

//hooks()->add_filter('before_offer_updated', '_format_data_offer_feature');
//hooks()->add_filter('before_offer_added', '_format_data_offer_feature');

hooks()->add_action('after_cron_run', 'offers_notification');
hooks()->add_action('admin_init', 'offers_module_init_menu_items');
hooks()->add_action('admin_init', 'offers_permissions');
hooks()->add_action('admin_init', 'offers_settings_tab');
hooks()->add_action('clients_init', 'offers_clients_area_menu_items');

//hooks()->add_action('app_admin_head', 'offers_head_component');
//hooks()->add_action('app_admin_footer', 'offers_footer_js_component');

hooks()->add_action('staff_member_deleted', 'offers_staff_member_deleted');

hooks()->add_filter('migration_tables_to_replace_old_links', 'offers_migration_tables_to_replace_old_links');
hooks()->add_filter('global_search_result_query', 'offers_global_search_result_query', 10, 3);
hooks()->add_filter('global_search_result_output', 'offers_global_search_result_output', 10, 2);
hooks()->add_filter('get_dashboard_widgets', 'offers_add_dashboard_widget');
hooks()->add_filter('module_offers_action_links', 'module_offers_action_links');


function offers_add_dashboard_widget($widgets)
{
    /*
    $widgets[] = [
        'path'      => 'offers/widgets/offer_this_week',
        'container' => 'left-8',
    ];
    $widgets[] = [
        'path'      => 'offers/widgets/project_not_offerd',
        'container' => 'left-8',
    ];
    */

    return $widgets;
}


function offers_staff_member_deleted($data)
{
    $CI = &get_instance();
    $CI->db->where('staff_id', $data['id']);
    $CI->db->update(db_prefix() . 'offers', [
            'staff_id' => $data['transfer_data_to'],
        ]);
}

function offers_global_search_result_output($output, $data)
{
    if ($data['type'] == 'offers') {
        $output = '<a href="' . admin_url('offers/offer/' . $data['result']['id']) . '">' . format_offer_number($data['result']['id']) . '</a>';
    }

    return $output;
}

function offers_global_search_result_query($result, $q, $limit)
{
    $CI = &get_instance();
    if (has_permission('offers', '', 'view')) {

        // offers
        $CI->db->select()
           ->from(db_prefix() . 'offers')
           ->like(db_prefix() . 'offers.formatted_number', $q)->limit($limit);
        
        $result[] = [
                'result'         => $CI->db->get()->result_array(),
                'type'           => 'offers',
                'search_heading' => _l('offers'),
            ];
        
        if(isset($result[0]['result'][0]['id'])){
            return $result;
        }

        // offers
        $CI->db->select()->from(db_prefix() . 'offers')->like(db_prefix() . 'clients.company', $q)->or_like(db_prefix() . 'offers.formatted_number', $q)->limit($limit);
        $CI->db->join(db_prefix() . 'clients',db_prefix() . 'offers.client_id='.db_prefix() .'clients.userid', 'left');
        $CI->db->order_by(db_prefix() . 'clients.company', 'ASC');

        $result[] = [
                'result'         => $CI->db->get()->result_array(),
                'type'           => 'offers',
                'search_heading' => _l('offers'),
            ];
    }

    return $result;
}

function offers_migration_tables_to_replace_old_links($tables)
{
    $tables[] = [
                'table' => db_prefix() . 'offers',
                'field' => 'description',
            ];

    return $tables;
}

function offers_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('offers', $capabilities, _l('offers'));
}


/**
* Register activation module hook
*/
register_activation_hook(OFFERS_MODULE_NAME, 'offers_module_activation_hook');

function offers_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
* Register deactivation module hook
*/
register_deactivation_hook(OFFERS_MODULE_NAME, 'offers_module_deactivation_hook');

function offers_module_deactivation_hook()
{

     log_activity( 'Hello, world! . offers_module_deactivation_hook ' );
}

//hooks()->add_action('deactivate_' . $module . '_module', $function);

/**
* Register language files, must be registered if the module is using languages
*/
register_language_files(OFFERS_MODULE_NAME, [OFFERS_MODULE_NAME]);

/**
 * Init offers module menu items in setup in admin_init hook
 * @return null
 */
function offers_module_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
            'name'       => _l('offer'),
            'url'        => 'offers',
            'permission' => 'offers',
            'position'   => 57,
            ]);

    if (has_permission('offers', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('offers', [
                'slug'     => 'offers-tracking',
                'name'     => _l('offers'),
                'icon'     => 'fa fa-calendar',
                'href'     => admin_url('offers'),
                'position' => 12,
        ]);
    }
}

function module_offers_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('settings?group=offers') . '">' . _l('settings') . '</a>';

    return $actions;
}

function offers_clients_area_menu_items()
{   
    // Show menu item only if client is logged in
    if (is_client_logged_in()) {
        add_theme_menu_item('offers', [
                    'name'     => _l('offers'),
                    'href'     => site_url('offers/list'),
                    'position' => 15,
        ]);
    }
}

/**
 * [perfex_dark_theme_settings_tab net menu item in setup->settings]
 * @return void
 */
function offers_settings_tab()
{
    $CI = &get_instance();
    $CI->app_tabs->add_settings_tab('offers', [
        'name'     => _l('settings_group_offers'),
        //'view'     => module_views_path(OFFERS_MODULE_NAME, 'admin/settings/includes/offers'),
        'view'     => 'offers/offers_settings',
        'position' => 51,
    ]);
}

$CI = &get_instance();
$CI->load->helper(OFFERS_MODULE_NAME . '/offers');

if(($CI->uri->segment(1)=='admin' && $CI->uri->segment(2)=='offers') || $CI->uri->segment(1)=='offers'){
    $CI->app_css->add(OFFERS_MODULE_NAME.'-css', base_url('modules/'.OFFERS_MODULE_NAME.'/assets/css/'.OFFERS_MODULE_NAME.'.css'));
    $CI->app_scripts->add(OFFERS_MODULE_NAME.'-js', base_url('modules/'.OFFERS_MODULE_NAME.'/assets/js/'.OFFERS_MODULE_NAME.'.js'));
}

