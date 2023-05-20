<?php


// return will stop processing preceding code and will allow other functions.php to run if found by the Plugin class. exit() will stop the whole script.
if (CONF_collectio_enable !== true) return;

require_once dirname(dirname(__FILE__)) ."/collectio/class/CollectioInvoiceProcess.class.php";

use Bullyard\Predator\GetInvoiceRemainingAmountByClientAndInvoiceNumber;
use Bullyard\Predator\PredatorAddCreditor;
use Bullyard\Predator\PredatorCaseImport;


/* HOOKS */

$hooks->add_action('invoice_option_checkbox', 'collectio_invoice_option_checkbox_fn');
function collectio_invoice_option_checkbox_fn($uid, $invoiceType){
    
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
        <div class="col-form-label col-12 col-sm-8  pl-4 offset-sm-4 custom-control custom-checkbox">
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
                        if (CONF_priceCollectorFee > 0){ ?>
                        <div class="col-12 mt-2">
                            <?php
                                (new AlertHelper('Automatisk purring og inkasso', 'Pris: NOK <strong>'.CONF_priceCollectorFee.'.-</strong> <small>eks. MVA</small> for tjenesten når saken blir overført til partner.', false))->icon('!')->id('collectio_show_price')->type('red')->alert(true);
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
    echo "<h5 class='pt-2 font-weight-normal'>Brukerbetingelser for Automatisk purring og inkasso via Collectio AS</h5>";
    require_once('terms.php');
};

$hooks->add_action('reminder_option_top', function(){
    global $uid;
    $acepptedTerms = \Metadata::get('collectio_terms', $uid);

    echo "<div class='custom-control custom-radio'>
            <input id='radioCollect' autocomplete='off' type='radio' name='sendingMethod' value='collection' class='custom-control-input' data-show='.show_infoCollection' />
            <label for='radioCollect' class='custom-control-label'><strong>Automatisk oppfølging, purring og inkasso.</strong> <span class='badge badge-danger'>NYHET</span></label>
        </div>";

        if (!empty($acepptedTerms)){
            echo "<div class='d-none infobox show_infoCollection' style=''>
                <div class='ml-3 p-1 pl-3 mt-2'>
                    ".AlertHelper::get_al('','Ved overføring av sak for oppfølging tilkommer NOK <strong>'.CONF_priceCollectorFee.'.-</strong> <small>eks. MVA</small>', 'red', '!', false)."
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
        <ol class='mt-1 pl-3 font-weight-bold'>
            <li class='font-weight-light'>Saken blir sendt inn til vår partner Collectio AS</li>
            <li class='font-weight-light'>Saken blir umiddelbart lagt til for oppfølging</li>
            <li class='font-weight-light'>Collectio følger opp med 1 purring og deretter til inkasso for raskere løsning</li>
            <li class='font-weight-light'>Når saken blir løst vil du få pengene direkte inn på konto og faktura vil bli merket som betalt her hos oss</li>

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
function collectio_after_created_invoice_fn($invoiceid, $uid, $request){
    // check if requeset contians that invoice should be automatically follwed up by partner
    if (isset($request['collectionService']) && $request['collectionService'] == "1" && $request['invoiceType'] == 1){
        Metadata::set('collectio_followup_'.$invoiceid, 1, $uid);
        Metadata::set('collectio_status_'.$invoiceid, 'queued', $uid);
    }

    //error_log(var_export(array($invoiceid, $uid, $request), true));
};

/* after register company */
$hooks->add_action('register_company', 'collectio_register_company_fn');
function collectio_register_company_fn($slug, $uid){
    
    // add to task manager
    $taskManager = new TaskManager();

    // Add a task to the queue
    $taskDataObj = new stdClass();
    $taskDataObj->uid = $uid;
    $callback = 'collectio_task_create_creditor';
    $taskManager->addTask($taskDataObj, $callback);

}


$hooks->add_action('outbox_invoice_sent', 'outbox_invoice_sent_fn');
function outbox_invoice_sent_fn($invoiceObj, $uid){
    // check if invoice is in queue for collection service
    $invoiceType = $invoiceObj->get_property('invoiceType');
    // this is credit note
    if ($invoiceType == 2){
        // check if the ref id is in queue for collection
        $invoiceRefID = $invoiceObj->get_property('creditNoteRefReal');

        $isInQueue = Metadata::get('collectio_status_'.$invoiceRefID, $uid);
        // deactivate
        if (!empty($isInQueue) && $isInQueue == "queued"){
            Metadata::set('collectio_followup_'.$invoiceRefID, 0, $uid);
            Metadata::set('collectio_status_'.$invoiceRefID, 'canceled', $uid);
        }
    }

    //error_log(var_export(array($invoiceType, $uid, $invoiceObj), true));

}

/* after register company */
$hooks->add_action('after_register_payment_success', 'collectio_after_register_payment_success_fn');
function collectio_after_register_payment_success_fn($invoiceID, $uid){
    
    $remaining = invoice_remaining($invoiceID);
    if ($remaining == 0){
        Metadata::set('collectio_followup_'.$invoiceID, 0, $uid);
        Metadata::set('collectio_status_'.$invoiceID, 'canceled', $uid);
    }
    error_log("Remaining: ".$remaining);
}

/* after delete company */
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


/* FILTERS */


\Filter::add_filter('filter_invoice_title', 'collectio_filter_invoice_title_fn');
function collectio_filter_invoice_title_fn($html){
    global $row, $uid;
    // check if case is being collected
    $caseStatus = Metadata::get('collectio_status_'.$row['id'], $uid);
    if (in_array($caseStatus, array('processing', 'queued'))){

        $bubbleTitle = ($caseStatus == "processing") ? "Saken er under oppfølging" : "Kravet vil bli automatisk fulgt opp dersom den ikke betales innen forfall";

        $info = '
        <style>
            .collector_status_bubble{
                display: inline-block;
                padding-left: 3px;
                height: 12px;
                width: 16px;
                vertical-align: inherit;
                line-height: 12px;
            }
            .collector_status_bubble svg{
                display: block;
            }
            .collector_status_bubble .bubble_front.processing {
                fill: rgba(255, 136, 0, 0.9);
              }
              .collector_status_bubble .bubble_back.processing {
                fill: transparent;
                stroke: rgba(255, 136, 0, 0.7);
              }
              .collector_status_bubble .bubble_front.queued {
                fill: rgba(0, 255, 171, 0.9);
              }
              .collector_status_bubble .bubble_back.queued {
                fill: transparent;
                stroke: rgba(0, 255, 171, 0.7);
              }
        </style>
        
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


\Filter::add_filter('filter_overdue_options', 'override_options_fn');
function override_options_fn($html){
    global $row, $uid;
    // check if invoice has been tagged for processing
    $isInQueue = Metadata::get('collectio_status_'.$row['id'], $uid);
   
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
                    <p><b>Saken blir automatisk overført ".CONF_collectio_days_before_deadline." dager etter forfall.</b><br>
                    Hvis du vil hindre at saken blir fulgt opp, kan du gjøre en av følgende tre ting: </p>
                    <ol>
                    <li>Trykk på 'Avbryt oppfølging'-knappen</li>
                    <li>Registrer innbetaling av hele beløpet som skyldes</li>
                    <li>Opprett en kreditnota for fakturaen</li>
                    </ol>
                </div>";

            $output .= " 
                <div class='col-md-12 pl-md-2'>
                    <div class='mt-3 pl-2'><small><b>Handlinger</b></small></div>
                        <button class='btn btn-danger' id='cancel_collection'>Avbryt oppfølging</button><br>
                        <!--button class='btn btn-primary mt-2' id='force_collection'>Følg opp nå</button-->
                </div>
                ";

            $output .=  '<script>
                $(function(){
                    $("#cancel_collection").on("click", function(event){
                        event.preventDefault();
                        $.fn.confirm("Vil du avbryte automatisk oppfølging av saken?", (ans) => {
                            if (ans) {
                                $.ajax({
                                    url: "/plugins/collectio/ajax/ajax.php",
                                    type: "GET",
                                    dataType: "JSON",
                                    data: {
                                        req: "stop_case",
                                        hash: "'.$row['hash'].'"
                                    }
                                })
                                .done(function(response) {
                                    top.location.href = location.href;
                                });
                            }
                        });   
                    });
                });
            
            </script>';
            
        $output .= "</div>";
        return $output;
    }else{
       return $html;
    }
}


\Filter::add_filter('filter_processing_overdue', 'override_output_inkasso_sent_fn');
function override_output_inkasso_sent_fn($html){
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
            $output .=  "<p>Din sak har ekstern saksnummer: <b>".$caseNO."</b> og er under behandling hos vår partner. <br>Ta kontakt med <a href='https://www.collectio.no/kontakt' target='_blank'>Collectio AS kunderservice</a> dersom du har noen spørsmål.</p>";
            
            // get status
            // $creditorID = Metadata::get('collectio_creditor_id', $uid); 
            // $obj = new GetInvoiceRemainingAmountByClientAndInvoiceNumber();
            // $resp = $obj->getRemaining($row['invoiceid'], $creditorID);

            $output .=  '<script>
                $(function(){
                    $.ajax({
                        url: "/plugins/collectio/ajax/ajax.php",
                        type: "GET",
                        dataType: "JSON",
                        data: {
                            req: "get_status",
                            hash: "'.$row['hash'].'"
                        }
                    })
                    .done(function(response) {
                       $("#collectio_remaining_response").html(response.response);
                    });
        
                });
            
            </script>';
            
            $output .=  " 
               
                <div class='mt-3 pl-2'><small><b>Status</b></small></div>
                <div class='py-2 px-5 mt-1 rounded d-inline-block bg-warning' id='collectio_remaining_response'>
                    henter status <i class='by-icon-sun by-spin by-va'></i>
                </div>
                ";
            
        $output .=  "</div>";
        return $output;
    }
    return $html;   
}

\Filter::add_filter('filter_html_price_list', 'filter_html_price_list_fn');
function filter_html_price_list_fn($html){
    // add price for inkasso in the list
    $Added = "<li>".CONF_priceCollectorFee.".- kr for oevrføring til purring/inkasso via partner</li>";
    $html = str_replace( '</ul>', $Added."</ul>", $html);
    return $html;
}





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
        send_mail("collectio", CONF_collectio_email, "Ny kreditor #".$creditorID, $body);

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
                            if (is_numeric($receiptCase)) {
                                // update the metadata with the receipt case number
                                $hasStatus = Metadata::set('collectio_case_'.$invoiceID, $receiptCase,  $uid);
                                $hasStatus = Metadata::set('collectio_status_'.$invoiceID, 'processing',  $uid);

                                // update the invoice with the sent_inkasso status
                                q("update invoice set sent_inkasso = '2' where uid = '".$uid."' and id ='".$invoiceID."'");

                                // change price for admin invoices
                                if ($uid == CONF_ServiceProviderUID){
                                    $price = 0;
                                }else{
                                    $price = CONF_priceCollectorFee;
                                }	
                                // register the service
                                \log_service($price, $uid, "Tjeneste: Sending av krav for ekstern oppfølging", $invoiceID, "collectio", "External ID: ".$receiptCase);
                                // set the output message
                                $result["status"] = "ok";
                                $result["message"] = "Gratulerer! Saken har blitt sendt til oppfølging.";
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
    return '
    <div class="dropdown-divider"></div>
    <li>
        <a class="dropdown-item" href="/?go=collectio_stats">
            <i class="by-icon-push-pin" aria-hidden="true"></i>&nbsp;COLLECTIO STATS
        </a>
    </li>';
}
