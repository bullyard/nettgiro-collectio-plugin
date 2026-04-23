<?php

// version 1.0.0

// return will stop processing preceding code and will allow other functions.php to run if found by the Plugin class. exit() will stop the whole script.
if (CONF_collectio_enable !== true) return;

require_once dirname(__DIR__) ."/collectio/class/CollectioInvoiceProcess.class.php";

use Bullyard\Predator\GetInvoiceRemainingAmountByClientAndInvoiceNumber;
use Bullyard\Predator\PredatorAddCreditor;
use Bullyard\Predator\PredatorCaseImport;



/* HOOKS */
// this hook is deactivated since its now executed by the reminder
//$hooks->add_action('invoice_option_checkbox', 'collectio_invoice_option_checkbox_fn');
function collectio_invoice_option_checkbox_fn($uid, $invoiceType){

    $CONF_collection_fee = \SubscriptionChecker::get_service_pricing($uid, 'collector_fee');
    
    if ($invoiceType == 1){
        // check if user has accepted terms
        $acceptedTerms = Metadata::get('collectio_terms', $uid);

        // checked if user has accepted terms
        $isChecked = ($acceptedTerms) ? "checked='checked'" : "";

        // uncheck if admin
        if (isset($_SESSION['admin']) && $_SESSION['admin'] == true){
            $isChecked = "";
        }

        ?>
        <div class="col-form-label custom-control custom-checkbox">
            <input type="checkbox" name="collectionService" id="collectionService" class="custom-control-input"
                value='1' <?=($acceptedTerms?"":"disabled='disabled'")?> <?=$isChecked?> />
            <label class="custom-control-label" for="collectionService">
                Automatisk purring/inkasso ved manglende betaling.
                <?php 
                    if ($acceptedTerms == false){
                        echo '<a href="#" class="badge badge-warning rounded-circle badge-warning-alt ml-2 " data-toggle="collapse" data-target="#infoPluginAcceptTerms" aria-expanded="false" aria-controls="infoPluginAcceptTerms">?</a>';
                    }
                ?>
            </label>

            <div class="form-group form-row mb-0" id="collectionServiceInfo">
                <?php 
                    if ($acceptedTerms !== false){
                        if ($CONF_collection_fee['price'] > 0){ ?>
                        <div class="col mr-3 mt-2">
                            <?php
                                (new AlertHelper('Automatisk purring og inkasso', 'Pris: NOK <strong>'.$CONF_collection_fee['price'].'.-</strong> <small>eks. MVA</small> for tjenesten når saken blir overført til partner.', false))->icon('!')->id('collectio_show_price')->type('red')->alert(true);
                            ?>
                        </div>
                    <?php 
                        } 
                    }else{ 
                        echo "<div id='infoPluginAcceptTerms' class='collapse mt-2'>";
                            AlertHelper::al('Aktiver purring og Inkasso', 'For å kunne aktivere oppfølging av dine saker må du først <a href="#" class="collectio_accept_terms"> godta vilkår og betingelser</a>.', 'warning', '?', false);
                        echo "</div>";
                    }
                ?>
            </div>
        </div>

        <?php 
    }

    
};

$hooks->add_action('terms_page', 'collectio_terms_fn');
function collectio_terms_fn(){
    echo "<h5 class='pt-2 font-weight-normal' id='collectio-terms'>Brukerbetingelser for Automatisk purring og inkasso via Collectio AS</h5>";
    require_once('terms.php');
};

$hooks->add_action('reminder_option_top', function(){
    global $uid;
    $acepptedTerms = \Metadata::get('collectio_terms', $uid);
    $CONF_collection_fee = \SubscriptionChecker::get_service_pricing($uid, 'collector_fee');

    echo "<div class='custom-control custom-radio'>
            <input id='radioCollect' autocomplete='off' type='radio' name='sendingMethod' value='collection' class='custom-control-input' data-show='.show_infoCollection' />
            <label for='radioCollect' class='custom-control-label'><strong>Ekstern oppfølging via partner (purring og inkasso).</strong></label>
        </div>";

        if (!empty($acepptedTerms)){
            echo "<div class='d-none infobox show_infoCollection' style=''>
                <div class='ml-3 p-1 pl-3 mt-2'>
                    ".AlertHelper::get_al('','Ved overføring av sak for oppfølging tilkommer NOK <strong>'.$CONF_collection_fee['price'].'.-</strong> <small>eks. MVA</small>', 'red', '!', false)."
                </div>
            </div>";
        }

        echo "<hr class='border-light'>";

}); 

$hooks->add_action('reminder_option_info', function(){
    global $uid;
    $acepptedTerms = \Metadata::get('collectio_terms', $uid);

    echo "<div class='d-none infobox show_infoCollection'>
        <h5><b>Atomatisk purring og inkasso</b></h5>
        <hr class='spacer'>
        Vår partner tar seg av innkrevingen og vil følge opp kunden hele veien frem til saken blir løst. 

        <h6 class='mt-2'>Hvordan fungerer det?</h6>
        <ol class='mt-1 pl-3 font-weight-bold small'>
            <li class='font-weight-light'>Saken blir sendt inn til vår partner Collectio AS</li>
            <li class='font-weight-light'>Saken blir umiddelbart lagt til for oppfølging</li>
            <li class='font-weight-light'>Collectio følger opp med 1 purring og deretter til inkasso for raskere løsning</li>
            <li class='font-weight-light'>Når saken blir løst vil du få gjelden direkte inn på konto (fratukket evnt gebyrer og andre trekk fra collectio AS) og faktura vil bli merket som betalt her hos oss</li>

        </ol>";

        if (empty($acepptedTerms)){
            echo "<div id='infoPluginAcceptTerms' class='mt-2'>";
                AlertHelper::al('Aktiver purring og Inkasso', 'For å kunne aktivere oppfølging av dine saker må du først <a href="#" class="collectio_accept_terms"> godta vilkår og betingelser</a>.', 'warning', '?', false);
            echo "</div>";
        }else{
            echo "<div class='form-group mt-2'>
                <input type='button' class='btn btn-block btn-danger reminderSendButtonAlt' data-ajax='/plugins/collectio/ajax/ajax.php' value='Send til automatisk oppfølging'>
            </div>";
        }

    echo "</div>";
 
}); 


$hooks->add_action('after_created_invoice', 'collectio_after_created_invoice_fn');
function collectio_after_created_invoice_fn($invoiceid, $uid, $invoiceData){
    // check if requeset contians that invoice should be automatically follwed up by partner
    if (isset($invoiceData['customData']['collectionService']) && $invoiceData['customData']['collectionService'] == "1" && $invoiceData['billtype'] == 'Faktura'){
        Metadata::set('collectio_followup_'.$invoiceid, 1, $uid);
        Metadata::set('collectio_status_'.$invoiceid, 'queued', $uid);

        \LogInvoiceActions::add($uid, $invoiceid, 'Automatic collection service', 'activated');
    }

    //error_log(var_export(array($invoiceData['billtype'], $invoiceData['customData']['collectionService'], $invoiceid, $uid, $invoiceData), true));
};

/** add filter and register that this invoice is a invoice with collelction service active by the current $_request */
\Filter::add_filter('invoice_custom_data', 'collectio_invoice_custom_data_fn');
function collectio_invoice_custom_data_fn($data){
    if (isset($_REQUEST['collectionService']) && $_REQUEST['collectionService'] == "1"){
        $data['collectionService'] = 1;
    }
    return $data;
}

/* after register company */
$hooks->add_action('register_company', 'collectio_register_company_fn');
function collectio_register_company_fn($slug, $uid){
    global $_REQUEST;

     // get setting for forced or voluntary registration to collectio
     $voluntary = Settings::get('collectio_voluntary_register_company');
     
    // Execute if the form was submitted with 'collection_service' checked as 1
    // and if $voluntary is 1, or if $voluntary is not 1 regardless of the checkbox value.
   if ($_REQUEST['collection_service'] == "1" && $voluntary == 1 || $voluntary != 1){
    
        // add to task manager
        $taskManager = new TaskManager();

        // Add a task to the queue
        $taskDataObj = new stdClass();
        $taskDataObj->uid = $uid;
        $callback = 'collectio_task_create_creditor';
        $taskManager->addTask($taskDataObj, $callback);
    }else{
        //debug("Collectio membership not added", 'user did not activate collection service checkbox, or voluntary setting is not 1');
    }

}


$hooks->add_action('outbox_invoice_sent', 'outbox_invoice_sent_fn');
function outbox_invoice_sent_fn($invoiceObj, $uid){
    // check if invoice is in queue for collection service
    $invoiceType = $invoiceObj->get_property('invoiceType');
    // this is credit note
    if ($invoiceType == 2){
        // check if the ref id is in queue for collection
        $invoiceRefID = $invoiceObj->get_property('creditNoteRefReal');

        $collectionStatus = Metadata::get('collectio_status_'.$invoiceRefID, $uid);
        // deactivate
        if (!empty($collectionStatus) && $collectionStatus == "queued"){
            Metadata::set('collectio_followup_'.$invoiceRefID, 0, $uid);
            Metadata::set('collectio_status_'.$invoiceRefID, 'canceled', $uid);
        }
    }

    //error_log(var_export(array($invoiceType, $uid, $invoiceObj), true));

}

/* after register company */
$hooks->add_action('after_register_payment_success', 'collectio_after_register_payment_success_fn');
function collectio_after_register_payment_success_fn($invoiceID, $uid, $moreData = null){
    // get status
    $collectionStatus = Metadata::get('collectio_status_'.$invoiceID, $uid);
    
    // if no status return
    if (empty($collectionStatus)) return;
    
    // Check if status is processing and call payment registration
    if ($collectionStatus == "processing") {
       
        // try to register payment
        try {
            // Get case number from metadata
            $caseNumber = Metadata::get('collectio_case_'.$invoiceID, $uid);
            // Get creditor ID
            $creditorId = Metadata::get('collectio_creditor_id', $uid);
            
            // Get invoice data for the payment registration
            $invoiceData = q("SELECT invoiceid FROM invoice WHERE id = '".$invoiceID."' AND uid = '".$uid."' LIMIT 1");
            
            
            if ($invoiceData && $invoiceData->num_rows > 0) {
               
                $invoice = mfa($invoiceData);
                $invoiceNumber = $invoice['invoiceid'];

                // convert payment date to YYYY-MM-DD
                if (isset($moreData['date'])){
                    if (strtotime($moreData['date']) !== false){
                        $dateOfPayment = date('Y-m-d', strtotime($moreData['date']));
                    }else{
                        $dateOfPayment = date('Y-m-d');
                        error_log("Collectio: Invalid payment date: ".$moreData['date']);
                    }
                }else{
                    $dateOfPayment = date('Y-m-d');
                }
                
                // Extract payment details from moreData if available
                $capital = isset($moreData['value']) ? $moreData['value'] : 0;

                // normalize capital to 2 decimals
                $capital = round($capital, 2);
                
                // Only proceed if we have all required data
                if ($caseNumber && $creditorId && $invoiceNumber && $capital > 0) {

                    // Try to instantiate the payment import class
                    $className = 'Bullyard\\Predator\importPaymentsByProxy';
                    if (class_exists($className)) {
                        $predator = new $className();
                        if (method_exists($predator, 'registerPayment')) {
                            $response = $predator->registerPayment($caseNumber, $creditorId, $invoiceNumber, $dateOfPayment, $capital);

                            //$debugBodyExtensive = "UID: ".$uid.", Invoice ID: ".$invoiceID.", Case: ".$caseNumber.", Amount: ".$capital.", Date: ".$dateOfPayment.", Creditor ID: ".$creditorId.", Invoice Number: ".$invoiceNumber.", Response: ".var_export($response, true);
                            if (is_object($response) && $response->Imported == "true"){
                                // set status to completed
                                //debug("automatic payment registered to collectio", $debugBodyExtensive, true, 3);
                                 // Log the payment registration attempt
                                \LogInvoiceActions::add($uid, $invoiceID, 'Collectio payment registered', 'Case: '.$caseNumber.', Amount: '.$capital);
                            }else{
                                //debug("Collectio plugin: after_register_payment_success", "Collectio plugin: payment registration error for invoice ".$invoiceID.", Response: ".var_export($response, true));
                            }
                        } else {
                            //debug("Collectio plugin: after_register_payment_success", "Collectio plugin: registerPayment method not found in class ".$className);
                        }
                    } else {
                       //debug("Collectio plugin: after_register_payment_success", "Collectio plugin: Payment import class ".$className." not found");
                    }
                }
            }else{
                //debug("Collectio plugin: after_register_payment_success", "Collectio plugin: Invoice data not found for invoice ID: ".$invoiceID);
            }
        } catch (\Throwable $e) {
            // Log any errors that occur during payment registration
            debug("Collectio plugin: after_register_payment_success", "Collectio plugin: payment registration error for invoice ".$invoiceID.": ".$e->getMessage());
        }
    }else if ($collectionStatus == "completed"){
        // do nothing
        return;
    }else if ($collectionStatus == "canceled" || $collectionStatus == "recall"){
        // do nothing
        return;
    }else{

        // if status is queued, check if invoice is payed before status is completed or processing
        // check if invoice is payed before status is completed or processing
        // stop collection if invoice is payed before status is queued
        $remaining = invoice_remaining($invoiceID);
        if ($remaining == 0){
            Metadata::set('collectio_followup_'.$invoiceID, 0, $uid);
            Metadata::set('collectio_status_'.$invoiceID, 'canceled', $uid);
        }
    }

    
    //error_log("Remaining: ".$remaining);
}

/* after delete company */
/* deactivate automatic collection if reminder plan is assigned or resumed */
$hooks->add_action('reminder_plan_assigned', 'collectio_reminder_plan_assigned_resumed_fn');
$hooks->add_action('reminder_plan_resumed', 'collectio_reminder_plan_assigned_resumed_fn');
function collectio_reminder_plan_assigned_resumed_fn($invoice_id, $plan_id = null, $assigned_by = null){
	// Get invoice uid for metadata lookup
	$invoice_res = q("SELECT uid FROM invoice WHERE id = " . intval($invoice_id) . " LIMIT 1");
	if ($invoice_res->num_rows > 0) {
		$uid = intval(mfa($invoice_res)['uid']);
		if ($uid > 0) {
			// When a reminder plan is assigned or changed, check if Collectio status is "queued" and delete it
			$collectio_status = Metadata::get('collectio_status_' . $invoice_id, $uid);
            //debug("Collectio plugin: reminder_plan_assigned_resumed", "Collectio plugin: reminder plan assigned or resumed for invoice ".$invoice_id.", uid: ".$uid.", plan_id: ".$plan_id.", assigned_by: ".$assigned_by.", collectio_status: ".$collectio_status, true, 3);
			if ($collectio_status === 'queued') {
				// Delete the queued collectio status metadata
				Metadata::delete('collectio_status_' . $invoice_id, $uid);
                Metadata::delete('collectio_followup_' . $invoice_id, $uid);
			}

            //debug("Collectio plugin: reminder_plan_assigned_resumed", "Collectio plugin: reminder plan assigned or resumed for invoice ".$invoice_id.", uid: ".$uid.", plan_id: ".$plan_id.", assigned_by: ".$assigned_by, true, 3);
		}
	}
}


$hooks->add_action('delete_company', 'collectio_delete_company_fn');
function collectio_delete_company_fn($uid){
    
   // remove from task manager
   q("UPDATE task_queue set status = 'failed', response_data = 'Client deleted' WHERE uid = '".$uid."' and status = 'queued'");

}

/* resend invoice validation */
$hooks->add_action('reminder_resend_invoice_validation', 'collectio_reminder_resend_invoice_validation_fn');
function collectio_reminder_resend_invoice_validation_fn($uid, $row){
    
    // check if invoice is being collected an do not allow resending
    $isBeingCollected = Metadata::get('collectio_case_'.$row['id'], $uid);
    if (isset($isBeingCollected) && is_numeric($isBeingCollected)){
        echo json_encode(array("internal"=> "Saken er under ekstern oppfølging og kan ikke sendes igjen."));
        die();
    }
}

/* reminder plan validation */
$hooks->add_action('reminder_sending_plan_validation', 'collectio_reminder_sending_plan_validation_fn');
function collectio_reminder_sending_plan_validation_fn($uid, $row, $reminderPlanId){
    
    // Only validate if a plan is selected (planId > 0)
    if ($reminderPlanId > 0) {
        $invoiceID = $row['id'];
        
        // Check if invoice is already in collection process
        $inkassoStatus = Metadata::get('collectio_status_'.$invoiceID, $uid);
        if (in_array($inkassoStatus, array('queued', 'processing', 'recall', 'completed'))) {
            $statusMessages = array(
                'queued' => 'i kø for oppfølging',
                'processing' => 'under oppfølging',
                'recall' => 'tilbakekalt fra oppfølging',
                'completed' => 'fullført'
            );
            $statusText = isset($statusMessages[$inkassoStatus]) ? $statusMessages[$inkassoStatus] : $inkassoStatus;
            echo json_encode(array("internal"=> "Du kan ikke bruke oppfølgingsplaner på denne fakturaen da den allerede er ".$statusText." hos inkassobyrået."));
            die();
        }
        
        // Check if user has accepted terms
        $acceptedTerms = Metadata::get('collectio_terms', $uid);
        
        // If terms are not accepted, prevent using reminder plans
        if (empty($acceptedTerms)) {
            echo json_encode(array("internal"=> "Du kan ikke bruke oppfølgingsplaner før du har godtatt vilkår og betingelser for Inkasso. <a href=\"#\" class=\"collectio_accept_terms\">Klikk her for å godta vilkårene</a>."));
            die();
        }
    }
}


/* FILTERS */

\Filter::add_filter('filter_payment_dialog_alert', 'collectio_filter_payment_dialog_alert_fn');
function collectio_filter_payment_dialog_alert_fn($html){
    global $uid, $id;
    
    $invoiceID = $id;
    $collectionStatus = Metadata::get('collectio_status_'.$invoiceID, $uid);
    
    // Show alert if invoice is under collection processing
    if ($collectionStatus == "processing") {
        $alert = AlertHelper::get_al(
            'Faktura er under oppfølging', 
            'Denne fakturaen er for øyeblikket under oppfølging hos Collectio AS. Registrering av betaling vil automatisk sende betalingsinformasjonen til Collectio og <strong>kan ikke reverseres</strong>.<br><br>Vær svært nøyaktig med betalingsdato da denne også sendes til Collectio og det er ingen mulighet for å endre eller overstyre denne informasjonen senere.', 
            'danger', 
            '!',
            false,
            'mb-3'
        );
        
        $html .= $alert;
    }
    
    return $html;
}

\Filter::add_filter('reminder_plan_output', 'collectio_filter_reminder_plan_output_fn');
function collectio_filter_reminder_plan_output_fn($html){
    global $uid, $invoiceType;
    
    // Only check for invoice type 1 (regular invoice)
    if (intval($invoiceType) !== 1) {
        return $html;
    }
    
    // Check if user has accepted terms
    $acceptedTerms = Metadata::get('collectio_terms', $uid);
    
    // If terms are not accepted, disable select box and show alert
    if (empty($acceptedTerms)) {
        // Remove selected attribute from all options
        $html = preg_replace('/\s+selected(?=\s|>)/', '', $html);
        
        // Select the "Ikke bruk oppfølgingsplan" option (value="0") instead
        $html = str_replace(
            '<option value="0"',
            '<option value="0" selected',
            $html
        );
        
        // Disable the select box
        $html = str_replace(
            '<select class="form-control selectpicker full-description" name="reminder_plan_id" id="reminder_plan_id"',
            '<select class="form-control selectpicker full-description" name="reminder_plan_id" id="reminder_plan_id" disabled="disabled"',
            $html
        );

        // send hidden input with the value 0
        $html .= '<input type="hidden" name="reminder_plan_id" value="0">';

        // Add alert after the small text (before closing the pl-4 div)
        $alert_html = AlertHelper::get_al(
            'Aktiver bruk av oppfølginsplaner', 
            'For å kunne benytte oppfølginsplaner må du først <a href="#" class="collectio_accept_terms">godta vilkår og betingelser</a>  for Inkasso.', 
            'warning', 
            '?', 
            false
        );
        
        // Insert alert after the small text element (before closing pl-4 div)
        // Find the last </small> tag and insert alert before the closing </div> tags
        $html = preg_replace(
            '/(<\/small>\s*<\/div>\s*<\/div>)/',
            '</small>
        
        <div id="infoPluginAcceptTermsReminder" class="mt-3">
            '.$alert_html.'
        </div>
    </div>
</div>',
            $html,
            1
        );
    }
    
    // Return modified or original HTML
    return $html;
}

\Filter::add_filter('filter_invoice_show_sidebar', 'collectio_filter_invoice_show_sidebar_fn');
function collectio_filter_invoice_show_sidebar_fn($html){
    global $uid, $row;
    
    $invoiceID = $row['id'];
    $collectionStatus = Metadata::get('collectio_status_'.$invoiceID, $uid);
    $caseNumber = Metadata::get('collectio_case_'.$invoiceID, $uid);
    
    // Only show if there's a case number
    if (!empty($caseNumber)) {
        
        // Determine status display
        $statusText = '';
        $statusIcon = 'hio hio-star';
        $statusColor = 'text-muted';
        
        switch ($collectionStatus) {
            case 'queued':
                $statusText = 'I kø for oppfølging';
                $statusIcon = 'hio hio-clock';
                $statusColor = 'text-warning';
                break;
            case 'processing':
                $statusText = 'Under oppfølging';
                $statusIcon = 'hio hio-clock';
                $statusColor = 'text-danger';
                break;
            case 'completed':
                $statusText = 'Oppfølging fullført';
                $statusIcon = 'hio hio-check-circle';
                $statusColor = 'text-success';
                break;
            case 'canceled':
                $statusText = 'Oppfølging avbrutt';
                $statusIcon = 'hio hio-x-circle';
                $statusColor = 'text-muted';
                break;
            case 'recall':
                $statusText = 'Tilbakekalt fra oppfølging';
                $statusIcon = 'hio hio-arrow-uturn-left';
                $statusColor = 'text-info';
                break;
            default:
                $statusText = 'Ukjent status';
                $statusIcon = 'hio hio-exclamation-triangle';
                $statusColor = 'text-muted';
                break;
        }
        
        $card = '<div class="card bg-white mb-3 mt-4" style="border-radius: 30px;">
                    <div class="card-header bg-warning" style="border-radius: 30px 30px 0 0;">
                        <h5 class="card-title mt-1 mb-0 ">
                            <span class="text-white"><i class="hio hio-shield-check"></i> Automatisk oppfølging</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>Status:</strong> 
                            <span class="'.$statusColor.'">
                                <b><i class="'.$statusIcon.'"></i> '.$statusText.'</b>
                            </span>
                        </p>';
        
        if (!empty($caseNumber)) {
            $card .= '<p class="mb-2">
                        <strong>Saksnummer:</strong> 
                        <span class="text-dark">'.$caseNumber.'</span>
                    </p>';
        }
        
        if ($collectionStatus == 'processing') {
            $card .= '<p class="mb-0 text-muted">
                        <small>
                            <i class="hio hio-info-circle"></i> 
                            Saken følges opp av Collectio AS. 
                            Kontakt Collectio kundeservice via <a href="mailto:post@collectio.no?subject=Gjelder%20saksnummer%3A%20'.$caseNumber.'" target="_blank">E-post</a> eller via telefon <a href="tel:+4722806290">+47 22 80 62 90</a> for spørsmål.
                        </small>
                    </p>';
        } elseif ($collectionStatus == 'queued') {
            $card .= '<p class="mb-0 text-muted">
                        <small>
                            <i class="hio hio-info-circle"></i> 
                            Saken vil automatisk overføres for oppfølging '.CONF_collectio_days_before_deadline.' dager etter forfall.
                        </small>
                    </p>';
        }
        
        $card .= '    </div>
                 </div>';
        
        $html .= $card;
    }
    
    return $html;
}

\Filter::add_filter('filter_action_buttons_array', 'collectio_filter_action_buttons_array_fn');
function collectio_filter_action_buttons_array_fn($array){
	global $uid, $row;
	
	$invoiceID = $row['id'];
    $invoiceType = $row['type'];
	$inkassoStatus = \metadata::get('collectio_status_'.$invoiceID, $uid); 

    // check if customer has accepted collectio terms
    if (intval($invoiceType) === 1){
        
        // check if user has accepted terms
        $acceptedTerms = Metadata::get('collectio_terms', $uid);

        if ($acceptedTerms !== false){

            // check if invoice status is not payed and not draft
            if ($row['status'] != 'payed' && $row['status'] != 'draft'){

                if ($row['sent_inkasso']!=1){

                    // Check if there's an active reminder plan - if so, hide collection button
                    $has_active_reminder_plan = false;
                    if (class_exists('ReminderPlanManager') && \Settings::get('reminder_system_enable') !== "false") {
                        $has_active_reminder_plan = \ReminderPlanManager::has_active_reminders($invoiceID);
                    }

                    // Only show collection button if there's no active reminder plan
                    if (!$has_active_reminder_plan) {
                        // check if CONF_collectio_days_before_deadline has passed after invoice due date
                        $dueDate = $row['duedate'];
                        $daysBeforeDeadline = CONF_collectio_days_before_deadline;
                        $deadline = date('Y-m-d', strtotime($dueDate. ' + '.$daysBeforeDeadline.' days'));
                        $today = date('Y-m-d');
                        $todayTimestamp = strtotime($today);
                        $deadlineTimestamp = strtotime($deadline);

                        //if ($todayTimestamp < $deadlineTimestamp){

                            // check if status is not processing, cancelled, recall, completed
                            if (!in_array($inkassoStatus, array('processing', 'recall', 'completed'))){
                            
                                if ($inkassoStatus == 'queued'){

                                    $btnParams = array(
                                        'hash'=>$row['hash'],
                                        'key'=>md5($row['hash'].CONF_hashKey),
                                        'req'=>'delete_case',
                                        'service_is_active' => true
                                    );
                        
                                    $array['invoiceBased']['stop'] = icon_button("#" ,"<span class='action-text'>Oppfølging: PÅ</span>", "time", "btn btn-success btn-icon btn-block text-left","", "btnToggleCollection", $btnParams);
                                    
                                }else{
                                    $btnParams = array(
                                        'hash'=>$row['hash'],
                                        'key'=>md5($row['hash'].CONF_hashKey),
                                        'req'=>'queue_case',
                                        'service_is_active' => false
                                    );
                        
                                    $array['invoiceBased']['stop'] = icon_button("#" ,"<span class='action-text'>Oppfølging: AV</span>", "time", "btn btn-danger btn-icon btn-block text-left","", "btnToggleCollection", $btnParams);
                                }
                            }
                        //}
                    }
                }
            }

        }
    }
		
	return $array;
    
}


\Filter::add_filter('filter_invoice_list_item', 'collectio_filter_invoice_list_item_fn');
function collectio_filter_invoice_list_item_fn($invoiceData){
    global $uid;
    
    // Access invoice data
    $invoiceId = $invoiceData['id'];
    $invoiceHash = $invoiceData['hash'];
    
    // Add custom data to the invoice object
    // This data will be available in JavaScript
    
    // Example: Check collection status
    $caseStatus = Metadata::get('collectio_status_' . $invoiceId, $uid);
    
    if (in_array($caseStatus, ['processing', 'queued'])) {
        $invoiceData['collector_status'] = $caseStatus;
        $invoiceData['collector_title'] = ($caseStatus == "processing") 
            ? "Saken er under oppfølging" 
            : "Kravet vil bli fulgt opp automatisk dersom den ikke betales innen forfall. <br><b>Husk å registrere innbetaling i systemet når faktura er betalt.</b>";
    }
    
    // Return modified invoice data
    return $invoiceData;
}


\Filter::add_filter('filter_invoice_title', 'collectio_filter_invoice_title_fn');
function collectio_filter_invoice_title_fn($html){
    global $row, $uid;
    // check if case is being collected
    $caseStatus = Metadata::get('collectio_status_'.$row['id'], $uid);
    if (in_array($caseStatus, array('processing', 'queued'))){

        $bubbleTitle = ($caseStatus == "processing") ? "Saken er under oppfølging" : "Kravet vil bli automatisk fulgt opp dersom den ikke betales innen forfall";

        $info = '
        <div class="collector_status_bubble">
            <svg width="12px" height="12px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" data-toggle="tooltip" title="'.$bubbleTitle.'">
                <animate 
                    xlink:href="#bubbleback'.$row['id'].'"
                    attributeName="r"
                    from="4.3"
                    to="7.5"
                    dur="1s"
                    begin="0s"
                    repeatCount="indefinite"
                    fill="freeze" 
                    id="circ-anim"
                />
                <animate 
                    xlink:href="#bubbleback'.$row['id'].'"
                    attributeType="CSS" attributeName="opacity"
                    from="1"
                    to="0"
                    dur="1s"
                    begin="0s"
                    repeatCount="indefinite"
                    fill="freeze" 
                    id="circ-anim"
                />
                <circle id="bubbleback'.$row['id'].'" class="bubble_back '.$caseStatus.'" cx="7.2" cy="7" r="6.5" stroke-width="1.333"/>
                <circle class="bubble_front '.$caseStatus.'" cx="7.2" cy="7" r="5.5"/>
            </svg>
        </div>';
        $html .= $info;
    }

    return $html;
}


\Filter::add_filter('filter_overdue_options', 'collectio_override_options_fn');
function collectio_override_options_fn($html){
    global $row, $uid;
    // check if invoice has been tagged for processing
    $isInQueue = Metadata::get('collectio_status_'.$row['id'], $uid);

    $CONF_collection_fee = \SubscriptionChecker::get_service_pricing($uid, 'collector_fee');

    if (!empty($isInQueue) && $isInQueue == "queued"){

        $output = "<div class='pl-3 pl-md-4 pt-0 card-body'>";
            $output .= "<div class='col-12'>
                <h4 class='card-title mt-2'>Faktura er under automatisk oppfølging.</h4>";
            //AlertHelper::al('', "" , 'warning', '!');
            $output .= "<hr class='spacer mt-3' />
            </div>
            ";
            $output .= "
                <div class='col-md-12'>
                    <p><b>Saken blir automatisk overført tidligst ".CONF_collectio_days_before_deadline." dager etter forfall.</b><br>
                    Hvis du vil hindre at saken blir fulgt opp, kan du gjøre en av følgende tre ting: </p>
                    <ol>
                    <li>Trykk på 'Avbryt oppfølging'-knappen</li>
                    <li>Registrer innbetaling av hele beløpet som skyldes</li>
                    <li>Opprett en kreditnota for fakturaen</li>
                    </ol>

                    ".(new AlertHelper('Merk at dette er en betaltjeneste', 'Dersom saken blir overført til Partner for oppfølging vil det tilkomme kostnader på NOK <strong>'.$CONF_collection_fee['price'].'.-</strong> <small>eks. MVA</small> for overføringen.', false))->icon('!')->id('collectio_show_price')->type('red')->alert(false)."

                </div>";

            $output .= " 
                <div class='col-md-12 pl-md-2'>
                    <div class='mt-3 pl-2'><small><b>Handlinger</b></small></div>
                        <button class='btn btn-danger' id='cancel_collection_in_invoice_page' data-hash='".$row['hash']."'>Avbryt oppfølging</button><br>
                        <!--button class='btn btn-primary mt-2' id='force_collection'>Følg opp nå</button-->
                </div>
                ";
            
        $output .= "</div>";
        return $output;
    }else{
       return $html;
    }
}


\Filter::add_filter('filter_processing_overdue', 'collectio_override_output_inkasso_sent_fn');
function collectio_override_output_inkasso_sent_fn($html){
    global $uid, $row;

    $invoiceID = $row['id'];

    // get metadata status 
    $caseStatus = Metadata::get('collectio_status_'.$invoiceID, $uid);

    if ($caseStatus == "processing"){ 
        // check if case has a case number
        $caseNO = Metadata::get('collectio_case_'.$row['id'], $uid);
        $output = "<div class='pl-3 pl-md-4 pt-0 card-body'>";
        $output .= "<h4 class='card-title mt-2'>Kravet er under oppfølging hos Collectio AS</h4>";
            //AlertHelper::al('', "" , 'warning', '!');
            $output .=  "<hr class='spacer mt-3' />";
            $output .=  "<p>Din sak har ekstern saksnummer: <b>".$caseNO."</b> og er under behandling hos vår partner. <br>Ta kontakt med Collectio kundeservice via 
                <a href='mailto:post@collectio.no?subject=Gjelder%20saksnummer%3A%20".$caseNO."' target='_blank'>E-post</a> eller via telefon <a href='tel:+4722806290'>+47 22 80 62 90</a> for spørsmål.</p>";
            
            // get status
            // $creditorID = Metadata::get('collectio_creditor_id', $uid); 
            // $obj = new GetInvoiceRemainingAmountByClientAndInvoiceNumber();
            // $resp = $obj->getRemaining($row['invoiceid'], $creditorID);
            
            $output .=  " 
               
                <div class='mt-3 pl-2'>
                    <small class='text-muted d-block mb-1'><b>Status</b></small>
                    <div class='d-flex flex-wrap align-items-center gap-2'>
                        <div class='py-2 px-4 rounded badge badge-warning text-dark mb-2 mb-sm-0 d-none' id='collectio_remaining_response' data-collectio-hash='".htmlspecialchars($row['hash'], ENT_QUOTES)."'>
                            <span class='status-text'></span>
                        </div>
                        <button type='button' class='btn btn-sm btn-outline-primary d-inline-flex align-items-center' id='collectio_load_status_btn' data-hash='".htmlspecialchars($row['hash'], ENT_QUOTES)."'>
                            <i class='hio hio-refresh mr-1'></i><span>Hent status</span>
                        </button>
                    </div>
                </div>
                <style>
                    .reminder-options-help-button {
                       display: none !important;
                    }
                </style>
                ";
            
        $output .=  "</div>";
        return $output;
    }
    return $html;   
}

\Filter::add_filter('filter_html_price_list', 'collectio_filter_html_price_list_fn');
function collectio_filter_html_price_list_fn($html){
    global $uid;

    $CONF_collection_fee = \SubscriptionChecker::get_service_pricing($uid, 'collector_fee');

    // add price for inkasso in the list
    $Added = "<li>".$CONF_collection_fee['price'].".- kr for overføring til purring/inkasso via partner</li>";
    $html = str_replace( '</ul>', $Added."</ul>", $html);
    return $html;
}


\Filter::add_filter('filter_more_options_in_create_company', 'collectio_filter_more_options_in_create_company_fn');
function collectio_filter_more_options_in_create_company_fn($html){

    // get setting for forced or voluntary registration to collectio
    $voluntary = Settings::get('collectio_voluntary_register_company');
    if ($voluntary == "1"){
       
        return "<label class='mt-4 w-100'><strong>Automatisk purring/inkasso</strong></label>
        <hr class=' mt-0'>
        <!-- Invoice -->
        <div class='form-group form-row '>
            <div class='col-auto'>
                <div class='custom-control custom-checkbox'>
                    <input type='checkbox' class='custom-control-input' id='collection_service' name='collection_service' value='1'>
                    <label class='custom-control-label' for='collection_service'>Knytt foretaket med <a href='/terms/#added-terms' target='_blank'>Collectio AS</a> for oppfølging av forfalte faktura. Du kan alltids deaktivere integrasjonen på ettertid.</label>
                </div>
            </div>
        </div>";
    }

    return "";
}


// Register reminder event for external collection agency
// This event sends cases to external collection service
Filter::add_filter('reminder_register_event_types', function($types) {
    $types[] = [
        'event_key' => 'external_collection',
        'event_name' => 'Automatisk oppfølging hos inkassobyrå',
        'event_description' => 'Sender saken til eksternt inkassobyrå. VIKTIG: Kan ikke brukes hvis purring med gebyr er sendt (inkassobyrået aksepterer ikke saker med tidligere purregebyr).',
        'event_category' => 'collection',
        'handler_class' => 'PluginEventHandler',
        'handler_method' => 'execute',
        'rules' => [
            'min_days_from_due' => 14,              // Minimum 14 days after due date
            'max_occurrences' => 1,                 // Only once per invoice
            'min_days_between_events' => 3,        // 14 days spacing
            'blocks_after_events' => ['purring', 'inkasso'],   // CANNOT add if purring exists before (collection agency rule)
            'blocks_future_events' => true,         // STOPS all future automated reminders
            'requires_subscription' => false,
            'service_name' => 'collector_fee',                 // External collection handled by third-party agency (pricing may vary)
            'default_config' => ['event_key' => 'external_collection']
        ],
        'icon_class' => 'hio hio-building-office',
        'color_class' => 'text-danger',
        'is_system' => 0,
        'plugin_source' => 'test_plugin',
        'sort_order' => 100
    ];
    return $types;
});

// Add callback for when this event executes
Filter::add_filter("reminder_execute_external_collection", function($data) {
    $invoice_id = intval($data['invoice_id']);
    $config = $data['config'] ?? [];
    
    if (empty($invoice_id)) {
        return [
            'success' => false,
            'error' => 'Invalid invoice ID provided'
        ];
    }
    
    // Get invoice data
    $invoice_res = q("SELECT * FROM invoice WHERE id = " . intval($invoice_id) . " LIMIT 1");
    if ($invoice_res->num_rows == 0) {
        return [
            'success' => false,
            'error' => 'Invoice not found'
        ];
    }
    
    $invoice = mfa($invoice_res);
    $uid = $invoice['uid'];
    
    // Safety check: Verify user has accepted terms
    $acceptedTerms = Metadata::get('collectio_terms', $uid);
    if (empty($acceptedTerms)) {
        return [
            'success' => false,
            'error' => 'Terms not accepted. Cannot send to external collection.'
        ];
    }
    
    // Safety check: Verify invoice is not already in collection
    $inkassoStatus = Metadata::get('collectio_status_'.$invoice_id, $uid);
    if (in_array($inkassoStatus, array('queued', 'processing', 'recall', 'completed'))) {
        return [
            'success' => false,
            'error' => 'Invoice is already in collection process (status: '.$inkassoStatus.')'
        ];
    }
    
    // Check if case already exists
    $existingCase = Metadata::get('collectio_case_'.$invoice_id, $uid);
    if (!empty($existingCase)) {
        return [
            'success' => false,
            'error' => 'Case already exists for this invoice (case: '.$existingCase.')'
        ];
    }

    // check if theres a reminder invoice sent for this invoice that has been sent with a fee
    // Collection agencies don't accept cases if a reminder with fee has already been sent
    // Check if reminder_fee_count > 0 on the original invoice
    if ($invoice['reminder_fee_count'] > 0) {
        return [
            'success' => false,
            'error' => 'Kan ikke sende saken til ekstern oppfølging da det allerede er sendt en purring med gebyr for denne fakturaen. Inkassobyrået aksepterer ikke saker hvor purring med gebyr allerede er sendt.'
        ];
    }
    
    // Also check if there are any reminder invoices (type=3) with fees for this invoice
    $reminder_check = q("SELECT id, reminder_fee_count FROM invoice 
                         WHERE refid = " . intval($invoice_id) . " 
                         AND type = 3 
                         AND uid = " . intval($uid) . " 
                         AND reminder_fee_count > 0 
                         LIMIT 1");
    if ($reminder_check->num_rows > 0) {
        return [
            'success' => false,
            'error' => 'Kan ikke sende saken til ekstern oppfølging da det allerede er sendt en purring med gebyr for denne fakturaen. Inkassobyrået aksepterer ikke saker hvor purring med gebyr allerede er sendt.'
        ];
    }

    
    // Create case for invoice using the existing function
    $result = collectio_create_case_for_invoice($invoice_id, $uid);
    
    // Handle the result
    if ($result['status'] == 'ok') {
        // Case was successfully created
        $caseNumber = $result['caseID'] ?? null;
        
        // Update followup stage to 3 (completed, followup was submitted to partner)
        // This matches the behavior in CollectioInvoiceProcess::findInvoicesForCollection()
        Metadata::set('collectio_followup_'.$invoice_id, 3, $uid);
        
        // Log the action (already done in collectio_create_case_for_invoice, but adding reminder context)
        \LogInvoiceActions::add($uid, $invoice_id, 'Reminder plan: Sent to external collection', 'Case: '.$caseNumber);
        
        return [
            'success' => true,
            'external_case_id' => $caseNumber,
            'message' => $result['message'] ?? 'Saken har blitt sendt til ekstern oppfølging.',
            'case_number' => $caseNumber
        ];
    } else {
        // Error occurred during case creation
        $errorMessage = $result['message'] ?? 'Unknown error occurred while creating case';
        
        // Log the error
        debug("Collectio plugin: reminder_execute_external_collection", "Failed to create case for invoice {$invoice_id}: {$errorMessage}");
        
        \LogInvoiceActions::add($uid, $invoice_id, 'Reminder plan: Sent to external collection', 'Failed');

        return [
            'success' => false,
            'error' => $errorMessage,
            'message' => $errorMessage
        ];
    }
});

/* FUNCTIONS */


function collectio_task_create_creditor($uid, $data1 = null, $data2 = null) {
    // check if user has registered after the activation date
    $collectio_enabled_date = date('Y-m-d H:i:s', CONF_collectio_enabled);
    $user_query = "SELECT uid FROM users WHERE uid = '".$uid."' and joindate >= '$collectio_enabled_date'";
    $user_result = q($user_query);

    if ($user_result) {
        // create a creditor
        $creditorID = collectio_create_creditor($uid);
        if (is_numeric($creditorID)){
            return "Successfully created with id ".$creditorID;
        }else{
            throw new Exception("Could not create creditor for uid #".$uid.", look at log_soap for more information", 1);
        }
        
    } else {
        // do nothing
        return "User has not registered after the activation date";
    }
    
 
}


function collectio_create_creditor(int $uid){

    $creditorInfo = get_company($uid);
    $creditorID = false;

    try {
        // create creditor.
        $obj = new Bullyard\Predator\PredatorAddCreditor();
    } catch (\Throwable $th) {
        //throw $th;
        error_log(__FUNCTION__."() error: ".$th->getMessage());
        return false;
    }

    $creditor = new \stdClass();
    $data = new \stdClass();

    $creditor->city             = $creditorInfo['postplace'];
    $creditor->countrycode      = "NO";
    $creditor->zipcode          = $creditorInfo['postnr'] ?: "?";
    $creditor->firstname        = "";
    $creditor->companyname      = $creditorInfo['name'];
    $creditor->orgnumber        = $creditorInfo['orgnr'] ?: "PRIVAT_".$creditorInfo['uid'];
    $creditor->address          = $creditorInfo['address'] ?: "";
    $creditor->accountNumber    = $creditorInfo['banknr'];
    $creditor->gsm              = $creditorInfo['gsm'] ?: "";
    $creditor->email            = $creditorInfo['email'] ?: "";
    $creditor->uid              = $creditorInfo['uid'];
    $creditor->vat_liable       = boolval($creditorInfo['mva']);
    
    $data->creditor = $creditor;

    $obj->build($data);
    $returnStatus = $obj->push();

    if (!$returnStatus){
        
        if ($obj->error_recoverable){
            $creditorID = $obj->api_duplicate_id;
        }
        // else{
        //     echo "<h3>General error</h3><br>";
        //     echo "Error: ".$obj->error."<br>";
        // }
    }else{
        $creditorID = $obj->api_receipt->CreditorNo;
    }

    if (!empty($creditorID) && is_numeric($creditorID)){
            // set metadata
        Metadata::set('collectio_terms', date('Y-m-d H:i:s'), $uid);
        Metadata::set('collectio_creditor_id', $creditorID, $uid);

        $body = "Foretak har godtatt brukervilkår / eller har generert et nytt foretak etter at vilkårene ble lagt til under registrering av konto<br><br>
            Kreditor generert med id ".$creditorID."<br>
            Foretaksinformasjon:<br>
            ".$creditor->companyname."<br>
            ".$creditor->address."<br>
            ".$creditor->zipcode.", ".$creditor->postplace."<br>
            Epost: ".$creditor->email."<br>
            Org: ".$creditor->orgnumber."<br><br>

            Datostempel: ".date('Y-m-d H:i:s')."<br>
            IP: ".$_SERVER['REMOTE_ADDR']."<br><br><br>

            Mvh, ".CONF_sitename."

        ";

        // send email 
        send_mail("collectio", CONF_collectio_email, "Ny kreditor #".$creditorID. " hos nettgiro.no", $body);
        send_mail("collectio", CONF_collectio_email_contact, "Ny kreditor #".$creditorID." hos nettgiro.no (kopi)", $body);

        return $creditorID;  
    }else{
       return false;
    }
}


/**
 * Creates a case for the given invoice if it hasn't been created already
 * 
 * @param int $invoiceID The id of the invoice to create a case for
 * @param int $uid The user ID of the invoice owner
 * @return array The output array status:ok|error
 */
function collectio_create_case_for_invoice($invoiceID, $uid) {
    // initialize an empty result array
    $result = array();

     //  check if metadata is set.
     $hasStatus = Metadata::get('collectio_case_'.$invoiceID, $uid);
     if (empty($hasStatus)){

        // check if the invoice is eligible for creating a case
        $res = q("select *, DATEDIFF(NOW(), duedate) AS days_since_duedate from invoice where id = '".safe_mysql($invoiceID)."' and type in (1) and uid = '".$uid."' limit 1");

        if ($res->num_rows > 0){
            while ($r = mfa($res)) {

                if (CONF_collectio_deny_manual_before_deadline && $r['days_since_duedate'] < CONF_collectio_days_before_deadline) {
                    $result['status'] = 'error';
                    $result['message'] = 'Kan ikke overføre saken før det har gått minst '.CONF_collectio_days_before_deadline.' dager';
                    return $result;
                }else{

                    // if the invoice is eligible
                    if ($r['sent_inkasso'] !== 0) {
                        try {
                            // get the creditor ID
                            $creditorID = Metadata::get('collectio_creditor_id', $uid);
                            // get the invoice ID
                            $invoiceID = $r['id'];
                            // create a new PredatorCaseImport object
                            $obj = new PredatorCaseImport();
                            // create a case for the invoice
                            $obj->create($invoiceID, $creditorID);
                            // build the case
                            $obj->build();
                            // push the case to the API
                            $returnStatus = $obj->push();
                            // get the receipt case number
                            $receiptCase = $obj->api_receipt->PredatorCaseNumber;

                            // if the receipt case number is numeric
                            if (!empty($receiptCase)) {
                                // update the metadata with the receipt case number
                                $hasStatus = Metadata::set('collectio_case_'.$invoiceID, $receiptCase,  $uid);
                                $hasStatus = Metadata::set('collectio_status_'.$invoiceID, 'processing',  $uid);

                                // update the invoice with the sent_inkasso status
                                q("update invoice set sent_inkasso = '2' where uid = '".$uid."' and id ='".$invoiceID."'");

                                // change price for admin invoices
                                if ($uid == CONF_serviceProviderUID){
                                    $price = 0;
                                }else{
                                    try {
                                        $pricingInfo = \SubscriptionChecker::get_service_pricing($uid, 'collector_fee');
                                        $price = $pricingInfo['price'];
                                    } catch (\Throwable $th) {
                                        //throw $th;  
                                        debug("Plugin Collectio: add case ERROR", "Error getting pricing info: ".$th->getMessage()."<br><br>ID:'".$invoiceID."'<br>uid:".$uid);
                                        $price = 0;
                                    }
                                }	
                                // register the service
                                \log_service($price, $uid, "Tjeneste: Sending av krav for ekstern oppfølging", $invoiceID, "collectio", "External ID: ".$receiptCase);
                                \LogInvoiceActions::add($uid, $invoiceID, 'Collectio case transfer', $receiptCase);
                                // set the output message
                                $result["status"] = "ok";
                                $result["message"] = "Gratulerer! Saken har blitt sendt til oppfølging.";
                                $result["caseID"] = $receiptCase;
                            } else {
                                // if the receipt case number is not numeric, log the error and set the output message
                                debug("Plugin Collectio add case ERROR", "API RESPONSE PROBLEM:<br><pre>Receipt:".var_export($obj->api_receipt, true)."</pre><br><pre>Error:".var_export($obj->error, true)."</pre><br><br>ID:'".$invoiceID."'<br>uid:".$uid);
                                $result["status"] = "error";
                                $result["message"] = "Intern error, kunne ikke generere sak hos Partner. <br><br>Hvis du nylig har aktivert Purring og inkasso, kan det hende at systemet ennå ikke har blitt oppdatert hos Collectio AS. Kom tilbake om litt og prøv igjen da.";
                            }
                        } catch (\Throwable $e) {
                            // if an exception is thrown, log the error and set the output message
                            debug("Plugin Collectio add case ERROR", $e->getMessage()."<br><br>ID:'".$invoiceID."'<br>uid:".$uid);
                            $result["status"] = "error";
                            $result["message"] = "Intern error, kunne ikke kommunisere med Partner. Vennligst prøv igjen.";
                        }
                    } else {
                        // if the invoice is not eligible, set the output message
                        debug("Plugin Collectio add case ERROR", "Case has no reference number, but has inkasso status =".$r['sent_inkasso']."<br><br>ID:'".$invoiceID."'<br>uid:".$uid);
                        $result["status"] = "error";
                        $result["message"] = "Saken er merket som inkassosak. Kan ikke automatisk importere saken. Ta kontakt med Collectio AS for mer informasjon";
                    }
                }
            
            }
        } else {
            // if the case is already submitted, ignore
            $result["status"] = "error";
            $result["message"] = "Kan ikke sende saken til oppfølging. Enten er ikke dette en kvalifisert faktura eller så er data registrert ikke korrekt.";
        }
    } else {
        // if the case is already submitted, ignore
        $result["status"] = "error";
        $result["message"] = "Saken er allerede sendt inn. Ta kontakt med Collectio AS for mer informasjon, saksnummer er '".$hasStatus."'";
    }
    return $result;
}

// $objjjj = new CollectioInvoiceProcess();
// $objjjj->findInvoicesForAlert();


/** PAGES */
Pages::register_page('collectio_stats', $_SERVER['DOCUMENT_ROOT'].'/plugins/collectio/pages/stats.php');

\Filter::add_filter('filter_add_admin_menu_element', 'collectio_filter_add_admin_menu_element_fn');
function collectio_filter_add_admin_menu_element_fn($empty){
    return $empty.'
    <div class="dropdown-divider"></div>
    <li>
        <a class="dropdown-item" href="/?go=collectio_stats">
            <i class="hio hio-shield-check" aria-hidden="true"></i>&nbsp;COLLECTIO STATS
        </a>
    </li>';
}

Pages::register_page('collectio_case_statuses', $_SERVER['DOCUMENT_ROOT'].'/plugins/collectio/pages/case_statuses.php');

\Filter::add_filter('filter_add_admin_menu_element', 'collectio_filter_add_admin_menu_element_2_fn');
function collectio_filter_add_admin_menu_element_2_fn($empty){
    return $empty.'
    <div class="dropdown-divider"></div>
    <li>
        <a class="dropdown-item" href="/?go=collectio_case_statuses">
            <i class="hio hio-shield-check" aria-hidden="true"></i>&nbsp;COLLECTIO CASE STATUSES
        </a>
    </li>';
}


function collectio_completed_case_number_to_text($number) {
    $mapping = [
        '9000' => 'OO - Filopplast ved sakreg',
        '9001' => 'OO - Filopplast i sak',
        '901'  => 'Avsluttet Betalt',
        '902'  => 'Avsluttet direkte betalt',
        '903'  => 'Avslutt dir bet før ink',
        '904'  => 'Avsluttet akkord',
        '906'  => 'Avsluttet betalt rettslig',
        '909'  => 'Avsluttet betalt overvåking',
        '911'  => 'Avsluttet mindreårig',
        '912'  => 'Tilbakekalt av oppdragsgiver',
        '913'  => 'Feilsendt inkasso',
        '914'  => 'Avsluttet kreditert krav',
        '915'  => 'Avsluttet utvandret',
        '916'  => 'Avsl. insolvent jfr, namsmann',
        '917'  => 'Avsluttet skyldner død',
        '918'  => 'Avsluttet overført Intrum',
        '921'  => 'Avsluttet konkurs',
        '922'  => 'Avsluttet jfr gjeldsordning',
        '923'  => 'Avsluttet Foreldet',
        '924'  => 'Avsl. konk. ikke anmeldt',
        '926'  => 'Avsluttet henvist til retten',
        '927'  => 'Avsl. tvist',
        '928'  => 'Avsluttet kreditor konkurs',
        '932'  => 'Avsluttet, manglende tilb.m.',
        '933'  => 'Avsluttet, pågang uønsket',
        '934'  => 'Avsluttet samarbeid',
        '941'  => 'Avsl. for lavt beløp u/pågang',
        '945'  => 'Avsluttet, adr. ukjent',
        '999'  => 'Flyttet til',

        // Newly added mappings
        '1001' => 'Inkassovarsel sendt',
        '1002' => 'Betalingsoppfordring',
        '1003' => 'Varsel rettslig sendes',
        '1004' => 'Varsel rettslig/Betalingsoppf',
        '1005' => 'Inkassovarsel Kapitol sendt',
        '1006' => 'BO test omni',
        '1007' => 'Purring rest',
        '1008' => 'Bedt kunden om å ringe',
        '1009' => 'Varsel om pant i konto',
        '1010' => 'Ubetalt krav',
        '1011' => 'Konkursvarsel sendt',
        '1012' => 'Betalingsoppfordring fornyels',
        '1013' => 'Avsluttet sak',
        '1014' => 'Notifikasjon',
        '1015' => 'Tilbud om reduksjon',
        '1016' => 'Avtalt oppgjør',
        '1017' => 'Varsel særlig tvang fravikels',
        '1018' => 'Varsel rettslig/Betalingsoppf',
        '1019' => 'Varsel tvangssalg løsøre',
        '1020' => 'Avdragsgiro sendes',
        '1021' => 'Avdragsgiro arb.giv.',
        '1022' => '1. Purring avdrag',
        '1023' => 'Varsel rettslig iht tvistelov',
        '1024' => 'Ingen giro',
        '1025' => 'Betalingsdokument',
        '1026' => 'Restkrav egeninkasso',
        '1027' => 'Purring arb.giv sendt',
        '1028' => 'Avdragsgiro mail',
        '1029' => 'Betalingspåm Tidsk. tj. hunde',
        '1030' => 'Purring uten gebyr sendt',
        '1031' => 'Purring med gebyr sendt',
        '1032' => 'Inkassovarsel sendt',
        '1033' => 'Purring før tva. løs sendes',
        '1034' => 'Purring før tva. fast',
        '1035' => 'Betalingspåminnelse sendt',
        '1036' => 'Inkassovarsel sendt',
        '1037' => 'Inkassovarsel sendt',
        '1038' => 'Egen inkassovarsel sendt',
        '1039' => 'Inkassovarsel sendt',
        '1040' => 'Inkassovarsel sendt',
        '1041' => 'Betalingsoppfordring sendt',
        '1042' => 'Betalingspåminnelse TS',
        '1043' => 'Oppfordring til kontakt sendt',
        '1044' => 'Fornyet varsel sendt',
        '1045' => 'Betalingsoppfordring sendt',
        '1046' => 'Ringt ikke kontakt sendt',
        '1047' => 'Varsel om overvåking sendt',
        '1048' => 'Inkassovarsel international',
        '1049' => 'Betalingsoppfordring Internat',
        '1050' => 'Gjeldsbrev',
        '1051' => 'Betalingsdokument',
        '1052' => 'Kravsoversikt sendt',
        '1053' => 'Betalingspåminnelse',
        '1054' => 'Inkassovarsel Accountor',
        '1055' => 'Inkassovarsel Sporty24 sendt',
        '1056' => 'Varsel rettslig',
        '1057' => 'Infobrev uten gebyr',
        '1058' => 'Anmeldelse krav GO',
        '1059' => 'Inkassovarsel kr 70 sendt',

        // Newly added mappings from current request
        '1060' => 'Inkassoovervåking',
        '1061' => 'Kampanje 50% epost',
        '1062' => 'Kampanje omkostninger',
        '1063' => 'Kampanje desember',
        '1064' => 'Kampanje redusert oppgjør',
        '1065' => 'Julekampanje',
        '1066' => 'Kampanje 20% hs og rente',
        '1067' => 'City Gym postoppkrav',
        '1068' => 'Beklagelse',
        '1069' => 'Tilbud om avdragsordning',
        '1070' => 'Inkassovarsel telecom',
        '1071' => 'Betalingsoppfordring Telecom',
        '1072' => 'Begjæring utlegg/forliksklage',
        '1073' => 'Inkassovarsel Telecom bedr',
        '1074' => 'Betalingsoppfordring flere fak',
        '1075' => 'Egeninkasso',
        '1076' => 'oEvgeervnåinkkasso overvåk',  // Appears to be a typo, but preserving as given
        '1077' => 'Egeninkasso a2vdrag',
        '1078' => 'Påminnelse ef',
        '1079' => 'Betalingspåminnelse Norbond',
        '1080' => 'Forhanstående panthaver',
        '1081' => 'ELSA Begjæring 7-2 f',
        '1082' => 'Betalingsoppfordring Egenin',
        '1083' => 'ELSA Forliksklage',
        '1084' => 'ELSA Utleggsforretning vanlig',
        '1085' => 'Varsel arbeidsgiver',
        '1086' => 'Delbetaling til namsmannen',
        '1087' => 'Møtefullmakt',
        '1088' => 'Flytting av lønnstrekk',
        '1089' => 'Sletting av pant løsøre',
        '1090' => 'Forliksklage',
        '1091' => 'Begjæring om utlegg',
        '1092' => 'Begjæring utlegg/forliksklage',
        '1093' => 'Tvangssalg løsøre',
        '1094' => 'Tv.salg eiendom begj.',
        '1095' => 'Begjæring om tvangsdekning',
        '1096' => 'Inf begjæring',
        '1097' => 'Tilbakekalt begjæring',
        '1098' => 'Sletting av utlegg',
        '1099' => 'Sletting av fast eiendom',
        '1100' => 'Oppdater salær',
        '1101' => 'lett salær',
        '1102' => 'Eksterne prosess omkostninger',
        '1103' => 'Nullstill salær',
        '1104' => 'Godkjent tvangssalgsforeretnin',
        '1105' => 'Retinglysning fast eiendom',
        '1106' => 'Betalingspåminnelse e-post',
        '1107' => 'Varsel om at sak kan reises',
        '1108' => 'Forliksrådet Rettshjelp',
        '1109' => 'Inkassovarsel DEMENTI',
        '1110' => 'Betalingsoppfordring DEMENTI',
        '1112' => 'BOF test',
        '1113' => 'Klage husleietvistutvalget',
        '1114' => 'Husleie begjæring 7-2F',
        '1137' => 'Inkassovarsel Engelsk',
        '1138' => 'Betalingsoppfordring engelsk',
        '1139' => 'Varsel rettslig/BOF engelsk',
        '1140' => 'Fritekstbrev skyldner',
        '1141' => 'Anmeldelse i konkursbo ekstern',
        '1142' => 'Fritekstbrev tredjeperson',
        '1143' => 'Anmeldelse i konkursbo',
        '1144' => 'Anmeldelse i dødsbo',
        '1145' => 'Skifteforespørsel',
        '1146' => 'Anmeldelse i løyvegaranti',
        '1147' => 'Bekreftelse avsluttet sak',
        '1148' => 'Tilbakekalt forliksklage',
        '1149' => 'Fritekstbrev forliksrådet',
        '1150' => 'Betalingsoppfordring',
        '1151' => 'Varsel rettslig/Betalingsoppf.',

        '1170' => 'Inkassovarsel foresatte',
        '1171' => 'Betalingsoppfor foresatte',
        '1172' => 'Varsel rettslig foresatte',
        '1178' => 'Purring med gebyr v foresatte',
        '1179' => 'Inkassovarsel',
        '1180' => 'Fullmakt fristillelse',

        '1201' => 'Utlegg lønn',
        '1202' => 'Utlegg trygd',
        '1203' => 'Utlegg løsøre',
        '1204' => 'Utlegg eiendom',
        '1205' => 'Intet til utlegg',
        '1206' => 'Adr. sjekk ny notert',
        '1207' => 'Post i retur opphørt',
        '1208' => 'Post i retur',
        '1209' => 'Post i retur, ettersendt',
        '1210' => 'Post i retur, ny adr. notert',
        '1211' => 'Utvandret',
        '1212' => 'Ukjent på adressen',
        '1213' => 'Gjeldsordning',
        '1214' => 'Reg. på samme adresse',
        '1215' => 'Uten fast bostedsadresse',
        '1216' => 'Ringt ikke svar',
        '1217' => 'Ringt avtalt oppgjør',
        '1218' => 'Skyldner er konkurs',
        '1219' => 'Avtalt oppgjør',
        '1220' => 'Ringt skyldner',
        '1221' => 'Avventer tilbakemelding',
        '1222' => 'Ringe debitor',
        '1223' => 'Skyldner er død',
        '1224' => 'Gjeldsrådgivning',
        '1225' => 'Inngående telefon',
        '1226' => 'Misligholdt avdragsordning',
        '1227' => 'Vurder rettslig tiltak',
        '1228' => 'Ikke funnet telefonnummer',
        '1230' => 'Funnet ny adresse',
        '1231' => 'Manuell tekst',
        '1232' => 'Berostillelse',
        '1233' => 'Epost Arkivering',
        '1234' => 'Utsatt sak',
        '1235' => 'UB/FK SENDT',
        '1236' => 'Tungt salær i avdragsordning',
        '1237' => 'Avventer oppd sms',
        '1238' => 'FRISTAVBRUDD',
        '1239' => 'Kredittsjekk Infolink',
        '1240' => 'Kredittvurdering',
        '1241' => 'Overført overvåking',
        '1242' => 'Ringt avtalt oppgjør KSF',
        '1243' => 'Ringt avtalt oppgjør SHF',
        '1244' => 'Ringt avtalt oppgjør',
        '1245' => 'Webmelding mottatt',
        '1246' => 'Webmelding sendt',
        '1250' => 'Oversendes utenlandsinkasso?',
        '1251' => 'Sendes til ligningsvask',
    ];

   if (isset($mapping[$number])) {
       return $number.": ".$mapping[$number];
    } else {
        return 'Ukjent status ('.$number.')';
    }
}



