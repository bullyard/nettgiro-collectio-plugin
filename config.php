<?php

if (!defined('CONF_collectio_enable')) define('CONF_collectio_enable', true);


// used to determine if user har accepted terms when registering
define('CONF_collectio_enabled', 1682434278); //  tirsdag 25. april 2023 kl. 16:51:14 GMT+02:00 DST

define('CONF_collectio_days_before_deadline', 14);
define('CONF_collectio_deny_manual_before_deadline', true); // does not allow sending manually before the deadline is met


// define predator vartiables DEV
if (CONF_is_dev){
    define('CONF_collectio_email', 'perez@bullyard.no');
    define('CONF_collectio_email_contact', 'perez@bullyard.no');
    define('PREDATOR_WDSL', 'https://131117.web-site.no/CustomerServiceTest/CustomerService.asmx?WSDL');
    define('PREDATOR_AUTH_UID', 'TestKreditor');
    define('PREDATOR_AUTH_PW', 'U2PAqdprT95Ds6hk');
    define('PREDATOR_AUTH_CLIENTNO', '001968');
    define('PREDATOR_TEMPLATE_CREDITOR', '002369');
    define('PREDATOR_TEMPLATE_CREDITOR_ALT', '002369');
}else{
    // production
    define('CONF_collectio_email', 'rune@collectio.no');
    define('CONF_collectio_email_contact', 'kontakt@collectio.no');
    define('PREDATOR_WDSL', 'https://131117.web-site.no/customerservice/CustomerService.asmx?WSDL');
    define('PREDATOR_AUTH_UID', 'BullyardServices');
    define('PREDATOR_AUTH_PW', 'jBrD2Qwx7f7TBGjVX');
    define('PREDATOR_AUTH_CLIENTNO', '002662');
    define('PREDATOR_TEMPLATE_CREDITOR', '002792');
    define('PREDATOR_TEMPLATE_CREDITOR_ALT', '002793');
    define('PREDATOR_BIND_IP', '172.31.34.160'); // Route via 13.49.101.30 (clean IP, avoids blacklisted primary)
}
