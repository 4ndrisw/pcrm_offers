<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['offers/offer/(:num)/(:any)'] = 'offer/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['offers/list'] = 'myoffer/list';
$route['offers/show/(:num)/(:any)'] = 'myoffer/show/$1/$2';
$route['offers/office/(:num)/(:any)'] = 'myoffer/office/$1/$2';
$route['offers/pdf/(:num)'] = 'myoffer/pdf/$1';
$route['offers/office_pdf/(:num)'] = 'myoffer/office_pdf/$1';
