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
use Bullyard\Predator\ExportPaymentsByFilter;
use Bullyard\Predator\PredatorRecallCase;
use Bullyard\Predator\ExportStatusByFilter;




$connection = db_connect();

// decrypt token if passed
if ($_REQUEST['token']){
	$tokenData = EncryptUID::decrypt($_REQUEST['token']);
	if (is_array($tokenData)) extract($tokenData);
}

if ($_REQUEST['slug'] && !empty($currentSlug)){
	$currentSlug = $_REQUEST['slug'];
}

$req =  $_REQUEST['req'];

if (is_numeric($uid) && $_SESSION['aid']){

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
            $creditorID = \Metadata::get('collectio_creditor_id', $uid);  
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
        $creditor =  \Metadata::get('collectio_creditor_id', $uid); 
        $caseNo =  \Metadata::get('collectio_case_'. $invoiceNumber, $uid); 

    
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
        $caseNo = \Metadata::get('collectio_case_'.$invoiceID, $uid);

        if (!empty($caseNo)){

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
            $collectionStatus = \Metadata::get('collectio_status_'.$invoiceID, $uid);

            // do not allow if case is processing
            if ($collectionStatus != 'processing'){
                \Metadata::set('collectio_followup_'.$invoiceID, '0', $uid);
                \Metadata::set('collectio_status_'.$invoiceID,'canceled', $uid);

                \LogInvoiceActions::add($uid, $invoiceID, 'Automatic collection service', 'deactivated');
                echo json_encode(array('status'=>'ok'));
                die();
            }else{
                echo json_encode(array('status'=>'failed', 'message'=>'Kan ikke stoppe saken for oppfølging, da den er allerede oversendt til innkrever.'));
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
            $collectionStatus = \Metadata::get('collectio_status_'.$invoiceID, $uid);

            // allow only if terms has been accepted, id is provided when terms are accepted
            $creditorID = \Metadata::get('collectio_creditor_id', $uid);  
            if (is_numeric($creditorID)){

                if (is_numeric($invoiceID)){

                    // check if case is not processing, completed or recalled
                    if (!in_array($collectionStatus, array('completed', 'processing', 'recall'))){
                        \Metadata::set('collectio_followup_'.$invoiceID, 1, $uid);
                        \Metadata::set('collectio_status_'.$invoiceID,'queued', $uid);

                        \LogInvoiceActions::add($uid, $invoiceID, 'Automatic collection service', 'activated');
                        echo json_encode(array('status'=>'ok'));
                        die();
                    }else{
                        echo json_encode(array('status'=>'failed', 'message'=>'Kan ikke sende inn saken for oppfølging, da den allerede er sendt inn eller avsluttet.'));
                        die();
                    }
                }
            }
        }

        echo json_encode(array('status'=>'failed'));

    
    }else if ($req=="recall_case"){
        // User-initiated recall: must own the invoice AND status must be 'processing'
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID   = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID  = Invoice::get_uid_by_hash($invoiceHash);

        if ($invoiceUID !== $uid || !is_numeric($invoiceID)){
            echo json_encode(array('status'=>'failed', 'message'=>'Ingen tilgang til denne saken.'));
            die();
        }

        $collectionStatus = \Metadata::get('collectio_status_'.$invoiceID, $uid);
        if ($collectionStatus !== 'processing'){
            echo json_encode(array('status'=>'failed', 'message'=>'Kun saker under oppfølging kan tilbakekalles.'));
            die();
        }

        $caseNo     = \Metadata::get('collectio_case_'.$invoiceID, $uid);
        $creditorID = \Metadata::get('collectio_creditor_id', $uid);

        if (empty($caseNo) || empty($creditorID)){
            echo json_encode(array('status'=>'failed', 'message'=>'Manglende sak- eller kreditor-informasjon.'));
            die();
        }

        $recall   = new PredatorRecallCase();
        $response = $recall->getResult($creditorID, $caseNo);

        if (isset($response['status']) && $response['status'] == 'success'){
            \Metadata::set('collectio_followup_'.$invoiceID, 0, $uid);
            \Metadata::set('collectio_status_'.$invoiceID, 'recall', $uid);
            \LogInvoiceActions::add($uid, $invoiceID, 'Automatic collection service', 'case recalled by user');

            echo json_encode(array('status'=>'success', 'caseNo'=>$caseNo));
            die();
        }

        echo json_encode(array('status'=>'failed', 'message'=>'Tilbakekall feilet hos partner. Prøv igjen senere eller kontakt Collectio.'));

    }else if ($req=="delete_case"){
        // get invoice id from hash
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID = Invoice::get_uid_by_hash($invoiceHash);
        
        $key = $_REQUEST['key'];

        if ($invoiceUID === $uid){
            $collectionStatus = \Metadata::get('collectio_status_'.$invoiceID, $uid);

            // do not allow if case is processing
            if ($collectionStatus != 'processing'){
                if (md5($invoiceHash.CONF_hashKey) == $key){
                    if (is_numeric($invoiceID)){
                        \Metadata::delete('collectio_followup_'.$invoiceID, $uid);
                        \Metadata::delete('collectio_status_'.$invoiceID, $uid);
    
                        \LogInvoiceActions::add($uid, $invoiceID, 'Automatic collection service', 'deactivated');
    
                        echo json_encode(array('status'=>'ok'));
                        die();
                    }
                }
            }else{
                echo json_encode(array('status'=>'failed', 'message'=>'Kan ikke stoppe saken for oppfølging, da den er allerede oversendt til innkrever.'));
                die();
            }

        }
      
        echo json_encode(array('status'=>'failed'));
       
        
    }else{
	    echo json_encode(array("internal"=> "Feil i kommunikasjon med server. Vennligst logg av og på, og prøv igjen."));
    }

// admin functions
}elseif($_SESSION['admin'] == 1){ 

    if ($req=="get_status_by_payments"){

        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID = Invoice::get_uid_by_hash($invoiceHash);
        $caseNo = \Metadata::get('collectio_case_'.$invoiceID, $invoiceUID);
        $creditorID = \Metadata::get('collectio_creditor_id',$invoiceUID);

        $processor = new CollectioInvoiceProcess();
        $response = $processor->getCaseStatus2($caseNo, $creditorID);
        echo json_encode(array('result'=>$response));

    }else if ($req=="credit_line"){
        $invoiceHash = $_REQUEST['hash'];
        $invoiceID = Invoice::get_id_by_hash($invoiceHash);
        $invoiceUID = Invoice::get_uid_by_hash($invoiceHash);
        $caseNo = \Metadata::get('collectio_case_'.$invoiceID, $invoiceUID);
        $creditorID = \Metadata::get('collectio_creditor_id',$invoiceUID);

        $recall = new PredatorRecallCase();
        $response = $recall->getResult($creditorID, $caseNo);

        if ( $response['status'] == "success"){
            // update metadata for invoice
            \Metadata::set('collectio_followup_'.$invoiceID, 0, $invoiceUID);
            \Metadata::set('collectio_status_'.$invoiceID,'recall', $invoiceUID);

            // log to invoice actions
            \LogInvoiceActions::add($invoiceUID, $invoiceID, 'Automatic collection service', 'case recalled');
        }

        echo json_encode($response);
        
    }else if ($req=="get_case_status"){

        $creditor_id = $_REQUEST['creditor_id'];
        $min_case_number = $_REQUEST['min_case_number'];
        $max_case_number = $_REQUEST['max_case_number'];


        $predator = new ExportStatusByFilter();
        $statuses = $predator->getResult($min_case_number, $max_case_number, $creditor_id);

        // group by case number and add CaseDebtCollectionStatusCode to the array
        $grouped = [];
        $uniqueStatuses = [];
        $ActionCodes = [];
        $dateData = [];
        $i = 0;

        // convert to array if only one object
        if (!is_array($statuses->PaymentSet->Payment)){
            $statuses->PaymentSet->Payment = array($statuses->PaymentSet->Payment);
        }

        // generate for sorting by date
        foreach ($statuses->PaymentSet->Payment as $payment) {
            $i++;
            $sortdate = strtotime($payment->DateOfPayment);
            $dateData[$sortdate + $i] = $payment;
        }

        // sort by date
        krsort($dateData);
        
        $lastStatusDate = [];
        foreach ($dateData as $payment) {
            
            $statusCode = $payment->Receipt->CaseDebtCollectionStatusCode;
            
            $invoiceText = $payment->InvoiceText ?? "";
            $actionCode = $payment->Receipt->ActionCode;
            $date = date('d-m-Y', strtotime($payment->DateOfPayment));
            
            if ($actionCode == $statusCode && empty($lastStatusDate[$payment->CaseNumber])){
                $lastStatusDate[$payment->CaseNumber] = date('d-m-Y', strtotime($payment->DateOfPayment));
                if ($payment->InvoiceText){
                    $grouped[$payment->CaseNumber]['StatusText'] = "- ".$statusCode. ": " .$payment->InvoiceText." (".$lastStatusDate[$payment->CaseNumber].")";
                }else{
                    $grouped[$payment->CaseNumber]['StatusText'] = "- ".collectio_completed_case_number_to_text($statusCode). " (".$lastStatusDate[$payment->CaseNumber].")";
                }
            }

            // set codeMatch if actionCode is the same as statusCode
            if ($actionCode == $statusCode && !isset($grouped[$payment->CaseNumber]['codeMatch'])){
                $grouped[$payment->CaseNumber]['codeMatch'] = true;
            }
            
            $ActionCodes[] = $actionCode;
            $grouped[$payment->CaseNumber]['StatusCode'] = $statusCode;
            $grouped[$payment->CaseNumber]['Creditor'] = $payment->Creditor->CreditorNO;

            // check if it has InvoiceText or get the code description from function
            
            $grouped[$payment->CaseNumber]['ActionCode'][] = $actionCode.": ". $invoiceText." (".$date.")";

        }

        //loop though $grouped
        foreach ($grouped as $caseNumber => $data) {
            if (!isset($data['codeMatch'])){
                $getByActivityCode = $predator->getByActivityCode($caseNumber ,$data['Creditor'], $data['StatusCode']);
                // update status text
                if ($getByActivityCode){
                    $payment = $getByActivityCode->PaymentSet->Payment;
                    if ($payment->InvoiceText){
                        $grouped[$caseNumber]['StatusText'] = "-- ".$payment->Receipt->CaseDebtCollectionStatusCode. ": " .$payment->InvoiceText." (".date('d-m-Y', strtotime($payment->DateOfPayment)).")";
                    }else{
                        $grouped[$caseNumber]['StatusText'] = "-- ".collectio_completed_case_number_to_text($payment->Receipt->CaseDebtCollectionStatusCode). " (".date('d-m-Y', strtotime($payment->DateOfPayment)).")";
                    }
                   
                }
            }
        }


        echo json_encode(array('result'=>$grouped, 'actionCodes'=>$ActionCodes, 'fullResult'=>$statuses));
    }else if ($req=="get_case_payments"){
        $creditor_id = $_REQUEST['creditor_id'];
        $case_number = $_REQUEST['case_number'];
      
        $predator = new ExportPaymentsByFilter();
        $payments = $predator->getResult($case_number, $creditor_id);

        $paymentList = [];
        $paymentList['totalPayed'] = 0;
        $paymentList['payments'] = [];
        if ($payments->PaymentSet->Payment){
            if (!is_array($payments->PaymentSet->Payment)){
                $payments = array($payments->PaymentSet->Payment);
            }else{
                $payments = $payments->PaymentSet->Payment;
            }
            foreach ($payments as $payment) {
                $paymentList['payments'][] = array('value' => $payment->Capital, 'date' => date('d-m-Y', strtotime($payment->DateOfPayment)));
                $paymentList['totalPayed'] += $payment->Capital;
            }
        }

        echo json_encode(array('result'=>$paymentList, 'fullResult'=>$payments));

    }else if ($req == "cleanup_counts"){
        echo json_encode(array(
            'status'   => 'ok',
            'counts'   => collectio_cleanup_get_counts(),
            'pipeline' => collectio_cleanup_get_pipeline_stats(),
        ));

    }else if ($req == "cleanup_list"){
        $category = isset($_REQUEST['category']) ? (string)$_REQUEST['category'] : '';
        $limit    = isset($_REQUEST['limit'])  ? intval($_REQUEST['limit'])  : 100;
        $offset   = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;
        $limit    = max(1, min(500, $limit));

        if (!in_array($category, collectio_cleanup_categories(), true)) {
            echo json_encode(array('status' => 'error', 'message' => 'Ukjent kategori.'));
            die();
        }

        $rows = collectio_cleanup_get_targets($category, $limit, $offset, true);
        echo json_encode(array('status' => 'ok', 'rows' => $rows, 'limit' => $limit, 'offset' => $offset));

    }else if ($req == "cleanup_delete_single"){
        $invoiceID = isset($_REQUEST['invoice_id']) ? intval($_REQUEST['invoice_id']) : 0;
        $targetUID = isset($_REQUEST['target_uid']) ? intval($_REQUEST['target_uid']) : 0;
        $only      = isset($_REQUEST['only']) ? (string)$_REQUEST['only'] : '';

        if ($invoiceID <= 0 || $targetUID <= 0) {
            echo json_encode(array('status' => 'error', 'message' => 'Ugyldig parameter.'));
            die();
        }

        $deleted = collectio_cleanup_delete_batch(
            [['uid' => $targetUID, 'invoice_id' => $invoiceID]],
            $only === 'followup'
        );

        \LogInvoiceActions::add($targetUID, $invoiceID, 'Collectio admin cleanup', 'single ('.$only.'): rows=' . $deleted);

        echo json_encode(array('status' => 'ok', 'deleted' => $deleted));

    }else if ($req == "cleanup_delete_bulk"){
        // Progressive / chunked cleanup. Process ONE batch per request.
        // Client is expected to loop while done=false.
        $category = isset($_REQUEST['category']) ? (string)$_REQUEST['category'] : '';
        $batch    = isset($_REQUEST['batch']) ? intval($_REQUEST['batch']) : 500;
        $batch    = max(50, min(2000, $batch));

        if (!in_array($category, collectio_cleanup_categories(), true) || $category === 'serious_no_case') {
            // serious_no_case is review-only; never delete in bulk
            echo json_encode(array('status' => 'error', 'message' => 'Kategorien støtter ikke bulk-rydding.'));
            die();
        }

        // Always pull the NEXT batch from offset 0 — deleted rows drop out of
        // the result set so the "next batch" of unprocessed rows surfaces naturally.
        $targets = collectio_cleanup_get_targets($category, $batch, 0, false);

        if (empty($targets)) {
            $counts = collectio_cleanup_get_counts();
            echo json_encode(array(
                'status'    => 'ok',
                'done'      => true,
                'deleted'   => 0,
                'remaining' => $counts[$category] ?? 0,
            ));
            die();
        }

        $deleted = collectio_cleanup_delete_batch($targets, $category === 'orphan_followup');

        // Aggregate log entry per batch rather than per-row to avoid 500+ log writes.
        $sampleIds  = array_slice(array_column($targets, 'invoice_id'), 0, 5);
        // \debug(
        //     'Collectio cleanup batch',
        //     'category=' . $category
        //     . ' targets=' . count($targets)
        //     . ' deleted=' . $deleted
        //     . ' sample_invoice_ids=' . implode(',', $sampleIds)
        // );

        // Cheap remaining count using the same aggregate query as the page
        $counts    = collectio_cleanup_get_counts();
        $remaining = $counts[$category] ?? 0;

        echo json_encode(array(
            'status'    => 'ok',
            'done'      => ($remaining <= 0),
            'deleted'   => $deleted,
            'processed' => count($targets),
            'remaining' => $remaining,
        ));

    }else{
        echo json_encode(array("internal"=> "Du har vært innaktiv for lenge, vennligst logg inn igjen <a href='/login'>her</a>"));
    }
}else{
	echo json_encode(array("internal"=> "Du har vært innaktiv for lenge, vennligst logg inn igjen <a href='/login'>her</a>"));
}