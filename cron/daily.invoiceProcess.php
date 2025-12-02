<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', 0);
set_time_limit(0);

ini_set('zlib.output_compression', 0);
ini_set('output_buffering', 'Off');
ini_set('output_handler', '');
ini_set('implicit_flush', 1);

include_once($_SERVER['DOCUMENT_ROOT']."/config/config.php");
include_once($_SERVER['DOCUMENT_ROOT']."/func/fpdf_func.php");

header('X-Accel-Buffering: no');
//header('Content-Encoding: none;');
header('Content-Encoding: none;');

ob_implicit_flush(1);
echo "[".date('Y-m-d H:i:s')."]<br>\n";
ob_end_clean();


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


// Flush (send) the output buffer and turn off output buffering
ob_end_flush();

