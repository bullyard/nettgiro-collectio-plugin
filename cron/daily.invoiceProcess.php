<?php

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
ini_set('zlib.output_compression', false);
putenv('no-gzip=1'); // apache_setenv('no-gzip', '1'); <- apache_setenv() is only available when running PHP as an Apache module. If you're running PHP using a different SAPI (Server Application Programming Interface), such as CGI or FastCGI, this function may not be available.

echo "[".date('Y-m-d H:i:s')."]<br>\n";

include_once($_SERVER['DOCUMENT_ROOT']."/config/config.php");
include_once($_SERVER['DOCUMENT_ROOT']."/func/fpdf_func.php");

//  check if plugin is active
if (CONF_collectio_enable === false) exit('Plugin is not enabled');

// init class
$obj = new CollectioInvoiceProcess();

// find invoices to send them for collection
// IMPORTANT use this first so we don't send email to customer before sending to collection
// this setup can only be used as a daily cron
// if for some reason any of these needs to run at a more regularly schedule, methods need to be separated in differen files. 
// findInvoicesForCollection() needs to run 24h after findInvoicesForAlert() 
// because after findInvoicesForAlert() is executed, invoices gets flagged as ready for findInvoicesForCollection() 
$obj->findInvoicesForCollection();

// find invoices to send email to customer before sending them for collection
$obj->findInvoicesForAlert();

// update status on invoices if theyre are completed
$obj->processCaseStatusAll();



