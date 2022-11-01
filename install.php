<?php

defined('BASEPATH') or exit('No direct script access allowed');


require_once('install/offers.php');
require_once('install/offer_activity.php');
require_once('install/offer_comments.php');

$CI->db->query("
INSERT INTO `tblemailtemplates` (`type`, `slug`, `language`, `name`, `subject`, `message`, `fromname`, `fromemail`, `plaintext`, `active`, `order`) VALUES
('offer', 'offer-send-to-client', 'english', 'Send offer to Customer', 'offer # {offer_number} created', '<span style=\"font-size: 12pt;\">Dear {contact_firstname} {contact_lastname}</span><br /><br /><span style=\"font-size: 12pt;\">Please find the attached offer <strong># {offer_number}</strong></span><br /><br /><span style=\"font-size: 12pt;\"><strong>offer status:</strong> {offer_status}</span><br /><br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">We look forward to your communication.</span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}<br /></span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-already-send', 'english', 'offer Already Sent to Customer', 'offer # {offer_number} ', '<span style=\"font-size: 12pt;\">Dear {contact_firstname} {contact_lastname}</span><br /> <br /><span style=\"font-size: 12pt;\">Thank you for your offer request.</span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">Please contact us for more information.</span><br /> <br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-declined-to-staff', 'english', 'offer Declined (Sent to Staff)', 'Customer Declined offer', '<span style=\"font-size: 12pt;\">Hi</span><br /> <br /><span style=\"font-size: 12pt;\">Customer ({client_company}) declined offer with number <strong># {offer_number}</strong></span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-accepted-to-staff', 'english', 'offer Accepted (Sent to Staff)', 'Customer Accepted offer', '<span style=\"font-size: 12pt;\">Hi</span><br /> <br /><span style=\"font-size: 12pt;\">Customer ({client_company}) accepted offer with number <strong># {offer_number}</strong></span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-thank-you-to-customer', 'english', 'Thank You Email (Sent to Customer After Accept)', 'Thank for you accepting offer', '<span style=\"font-size: 12pt;\">Dear {contact_firstname} {contact_lastname}</span><br /> <br /><span style=\"font-size: 12pt;\">Thank for for accepting the offer.</span><br /> <br /><span style=\"font-size: 12pt;\">We look forward to doing business with you.</span><br /> <br /><span style=\"font-size: 12pt;\">We will contact you as soon as possible.</span><br /> <br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-expiry-reminder', 'english', 'offer Expiration Reminder', 'offer Expiration Reminder', '<p><span style=\"font-size: 12pt;\">Hello {contact_firstname} {contact_lastname}</span><br /><br /><span style=\"font-size: 12pt;\">The offer with <strong># {offer_number}</strong> will expire on <strong>{offer_expirydate}</strong></span><br /><br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span></p>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-send-to-client', 'english', 'Send offer to Customer', 'offer # {offer_number} created', '<span style=\"font-size: 12pt;\">Dear {contact_firstname} {contact_lastname}</span><br /><br /><span style=\"font-size: 12pt;\">Please find the attached offer <strong># {offer_number}</strong></span><br /><br /><span style=\"font-size: 12pt;\"><strong>offer status:</strong> {offer_status}</span><br /><br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">We look forward to your communication.</span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}<br /></span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-already-send', 'english', 'offer Already Sent to Customer', 'offer # {offer_number} ', '<span style=\"font-size: 12pt;\">Dear {contact_firstname} {contact_lastname}</span><br /> <br /><span style=\"font-size: 12pt;\">Thank you for your offer request.</span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">Please contact us for more information.</span><br /> <br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-declined-to-staff', 'english', 'offer Declined (Sent to Staff)', 'Customer Declined offer', '<span style=\"font-size: 12pt;\">Hi</span><br /> <br /><span style=\"font-size: 12pt;\">Customer ({client_company}) declined offer with number <strong># {offer_number}</strong></span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-accepted-to-staff', 'english', 'offer Accepted (Sent to Staff)', 'Customer Accepted offer', '<span style=\"font-size: 12pt;\">Hi</span><br /> <br /><span style=\"font-size: 12pt;\">Customer ({client_company}) accepted offer with number <strong># {offer_number}</strong></span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'staff-added-as-project-member', 'english', 'Staff Added as Project Member', 'New project assigned to you', '<p>Hi <br /><br />New offer has been assigned to you.<br /><br />You can view the offer on the following link <a href=\"{offer_link}\">offer__number</a><br /><br />{email_signature}</p>', '{companyname} | CRM', '', 0, 1, 0),
('offer', 'offer-accepted-to-staff', 'english', 'offer Accepted (Sent to Staff)', 'Customer Accepted offer', '<span style=\"font-size: 12pt;\">Hi</span><br /> <br /><span style=\"font-size: 12pt;\">Customer ({client_company}) accepted offer with number <strong># {offer_number}</strong></span><br /> <br /><span style=\"font-size: 12pt;\">You can view the offer on the following link: <a href=\"{offer_link}\">{offer_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0);
");
/*
 *
 */

// Add options for offers
add_option('delete_only_on_last_offer', 1);
add_option('offer_prefix', 'SCH-');
add_option('next_offer_number', 1);
add_option('default_offer_assigned', 9);
add_option('offer_number_decrement_on_delete', 0);
add_option('offer_number_format', 4);
add_option('offer_year', date('Y'));
add_option('exclude_offer_from_client_area_with_draft_status', 1);
add_option('predefined_client_note_offer', '- Staf diatas untuk melakukan riksa uji pada peralatan tersebut.
- Staf diatas untuk membuat dokumentasi riksa uji sesuai kebutuhan.');
add_option('predefined_terms_offer', '- Pelaksanaan riksa uji harus mengikuti prosedur yang ditetapkan perusahaan pemilik alat.
- Dilarang membuat dokumentasi tanpa seizin perusahaan pemilik alat.
- Dokumen ini diterbitkan dari sistem CRM, tidak memerlukan tanda tangan dari PT. Cipta Mas Jaya');
add_option('offer_due_after', 1);
add_option('allow_staff_view_offers_assigned', 1);
add_option('show_assigned_on_offers', 1);
add_option('require_client_logged_in_to_view_offer', 0);

add_option('show_project_on_offer', 1);
add_option('offers_pipeline_limit', 1);
add_option('default_offers_pipeline_sort', 1);
add_option('offer_accept_identity_confirmation', 1);
add_option('offer_qrcode_size', '160');
add_option('offer_send_telegram_message', 0);


/*

DROP TABLE `tbloffers`;
DROP TABLE `tbloffer_activity`, `tbloffer_items`, `tbloffer_members`;
delete FROM `tbloptions` WHERE `name` LIKE '%offer%';
DELETE FROM `tblemailtemplates` WHERE `type` LIKE 'offer';



*/