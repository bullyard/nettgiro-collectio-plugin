<?php

require_once($_SERVER['DOCUMENT_ROOT']."/config/config.php");
require_once($_SERVER['DOCUMENT_ROOT']."/func/fpdf_func.php");

use Bullyard\Invoice\Invoice;
use Bullyard\Invoice\InvoiceValidation;
use Bullyard\Invoice\RecurringFrequency;
use Bullyard\Invoice\InvoiceRecurring;
use Bullyard\Predator\PredatorAddCreditor;
use Bullyard\Predator\PredatorCaseImport;
use Bullyard\Predator\GetInvoiceRemainingAmountByClientAndInvoiceNumber;
use Bullyard\Predator\GetCaseInvoicesByCaseNumber;



$connection = db_connect();

// decrypt token if passed
if ($_REQUEST['token']){
	extract(EncryptUID::decrypt($_REQUEST['token']));
}

if ($_REQUEST['slug'] && !empty($currentSlug)){
	$currentSlug = $_REQUEST['slug'];
}


if (is_numeric($uid) && $_SESSION['aid']){

    $req =  $_REQUEST['req'];
	$aid = $_SESSION['aid'];

	//////////////////////////
	//
	//  ACCEPT TERMS FOR COMPANY
	//
	////////////////////////


	if ($req=="collectio_accept_terms"){


        $creditorID = collectio_create_creditor($uid);
       
        if (is_numeric($creditorID)){
            $creditorInfo = get_company($uid);
            echo json_encode(array("status"=> "ok", "message"=>"Gratulerer, registreringen ble vellykket!<br>Din ID for foretaket ".$creditorInfo['name']." er <b>".$creditorID."</b>. <br><br><b>Oppdater siden for å oppfriske de nye endringene.</b>"));
        }else{
            echo json_encode(array("status"=> "error", "error" => "Kunne ikke generere konto hos partner. Vennligst prøv igjen eller kontakt support for en løsning."));
        }
       
    }elseif ($req=="sendReminder"){

        $sendigMethod = $_REQUEST['sendingMethod'];
        $invoiceHash = $_REQUEST['hash'];

        if ($sendigMethod == 'collection'){

            // get creditor
            $creditorID = Metadata::get('collectio_creditor_id', $uid);  
            if (is_numeric($creditorID)){
                
                $invoiceID = Invoice::get_id_by_hash($invoiceHash);
                if (is_numeric($invoiceID)){

                    $response = collectio_create_case_for_invoice($invoiceID, $uid);
                    echo json_encode($response);
                    
                }else{
                    echo json_encode(array("status"=> "error", "message" => "Kunne ikke finne riktig faktura for innsending. Oppdater siden og prøv igjen"));
                }
            }else{
                echo json_encode(array("status"=> "error", "error" => "Du må godta vilkårene for å opperette konto hos vår partner før du kan sende inn saker for oppfølging."));
            }


            


        }else{
            echo json_encode(array("internal"=> "Feil i kommunikasjon med server. Vennligst logg av og på, og prøv igjen."));
        }

    }else if ($req=="get_remaining"){
        // get invoice id
        $invoiceNumber = $_REQUEST['invoiceID'];

        // get creditor
        $creditor =  Metadata::get('collectio_creditor_id', $uid); 
        $caseNo =  Metadata::get('collectio_case_'. $invoiceNumber, $uid); 

    
        if (is_numeric($caseNo)){

            $processor = new CollectioInvoiceProcess();
            $response = $processor->getCaseStatus($caseNo);
            
            if (is_numeric($response)){
                echo json_encode(array("response" => $response));
                die();
            } 
        }

        echo json_encode(array("response" => false));
    
    }else if ($req=="get_status"){
       
        // get invoice id, and case id
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $caseNo = Metadata::get('collectio_case_'.$invoiceID, $uid);

        if (is_numeric($caseNo)){

            $processor = new CollectioInvoiceProcess();
            $response = $processor->getCaseStatus($caseNo);

            if (is_numeric($response['BalanceCapital'])){
                $adminInfo = "";
                if ($_SESSION['admin'] == 1){
                    $adminInfo = " <br><small><b>ADMIN INFO: InitialCapital:".$response['InitialCapital']." | BalanceCapital:".$response['BalanceCapital']."</b></small>";
                }

                if ($response['BalanceCapital'] === (float) 0){
                    echo json_encode(array("response" => "<b>Saken ser ut til å være løst!</b> <br>Vi vil snart oppdatere fakturaen din og merke den som betalt.".$adminInfo));
                }else{
                   
                    echo json_encode(array("response" => "Saken er pågående og under oppfølging. ".$adminInfo));
                }
                die();
            } 
        }

        echo json_encode(array("response" => "Kunne ikke hente status for sak, prøv igjen senere eller kontakt innkrever for mer informasjon."));
    
    
    }else if ($req=="stop_case"){
        // get invoice id from hash
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID = Invoice::get_uid_by_hash($invoiceHash);

        if ($invoiceUID === $uid){

            if (is_numeric($invoiceID)){
                Metadata::set('collectio_followup_'.$invoiceID, '0', $uid);
                Metadata::set('collectio_status_'.$invoiceID,'canceled', $uid);
                echo json_encode(array('status'=>'ok'));
                die();
            }
        }

        echo json_encode(array('status'=>'failed'));

    }else if ($req=="queue_case"){
        // get invoice id from hash
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID = Invoice::get_uid_by_hash($invoiceHash);

        if ($invoiceUID === $uid){

            // allow only if terms has been accepted, id is provided when terms are accepted
            $creditorID = Metadata::get('collectio_creditor_id', $uid);  
            if (is_numeric($creditorID)){

                if (is_numeric($invoiceID)){
                    Metadata::set('collectio_followup_'.$invoiceID, 1, $uid);
                    Metadata::set('collectio_status_'.$invoiceID,'queued', $uid);
                    echo json_encode(array('status'=>'ok'));
                    die();
                }
            }
        }

        echo json_encode(array('status'=>'failed'));

    
    }else if ($req=="delete_case"){
        // get invoice id from hash
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID = Invoice::get_uid_by_hash($invoiceHash);
        
        $key = $_REQUEST['key'];

        if ($invoiceUID === $uid){

            if (md5($invoiceHash.CONF_hashKey) == $key){

                if (is_numeric($invoiceID)){
                    Metadata::delete('collectio_followup_'.$invoiceID, $uid);
                    Metadata::delete('collectio_status_'.$invoiceID, $uid);
                    echo json_encode(array('status'=>'ok'));
                    die();
                }
            }
        }
      
        echo json_encode(array('status'=>'failed'));
       
        
    }else{
	    echo json_encode(array("internal"=> "Feil i kommunikasjon med server. Vennligst logg av og på, og prøv igjen."));
    }
}else{
	echo json_encode(array("internal"=> "Du har vært innaktiv for lenge, vennligst logg inn igjen <a href='/login'>her</a>"));
}