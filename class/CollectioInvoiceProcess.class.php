<?php

/* HELP
* metada collectio_status_[invoiceID] should have these statuses:
* 1. queued = queued is when the invoice has been marked for collection when sending, system should submit it to collectio and change status to processing
* 2. processing = processing is when the invoice has aready been sent to collectio
* 3. completed =  finished is when collectio has completed the collection, either by collecting the debt or not. So when the case is finished no matter the outcome
* 4. cancelled = canceled, can be set when the used cancels the invoice while it is in queue
* 5. recall = recall, can be set when the used recalls the invoice while it is in queue
*/

/**
 * FollowUpStage (collectio_followup_*):
 * 0: cancelled, by system or by user
 * 1: Mail not sent
 * 2: Mail notifying about oversending case has been sent
 * 3: completed, followup was submitted to partner
 * Explanation: This makes it possible to alaways send an email before the case will be submitted
 * This is specially important when user has added an invoice that already has a duedate that has expired.
 */

use Bullyard\Predator\GetInvoiceRemainingAmountByClientAndInvoiceNumber;
use Bullyard\Predator\GetCaseInvoicesByCaseNumber;
use Bullyard\Predator\PredatorAddCreditor;
use Bullyard\Predator\PredatorCaseImport;
use Bullyard\Predator\ExportPaymentsByFilter;
use Bullyard\Predator\PredatorRecallCase;

class CollectioInvoiceProcess {

    const DEADLINE = CONF_collectio_days_before_deadline;
    private static $db; // Database connection
    
    public function __construct() {
      self::$db = \DB::getInstance(); // Instantiate database connection
    }
    
    public function findInvoicesForAlert() {

        echo "<h1>".__FUNCTION__."</h1>\n";

        $deadlineBefore = self::DEADLINE - 1;  
        $query = $this->produceQuery($deadlineBefore);
        $invoices = self::$db->q($query);


        //echo "<code>".$query."</code><br>";
        
        $emailList = array();

       
        while ($invoice = self::$db->mfa($invoices)) {

            // loop through list and group by uid
            $emailList[$invoice['uid']][] = $invoice;
            
        }
        
      
       
        foreach ($emailList as $uid => $row) {

            $userinfo = Bullyard\Invoice\Invoice::get_user($uid); // Get company information
            echo "<hr>";
            echo "<b>Recipient: ".$uid." (".htmlentities($userinfo['email'])." [".$userinfo['email']."])</b><br>lists: ".json_encode($row)."<br><br>";
            
            // echo "<pre>";
            // var_dump($row);
            // die();

            $subHTML = "";
           
            setlocale(LC_TIME, 'no_NO'); // Set the locale to Norwegian
            // generate list
            
            $subHTML .="<div style='padding-top:20px;padding-bottom:20px'><table width='100%' cellpadding='0' cellspacing='0'>";
            $subHTML .="<tr style='border-bottom:1px solid #eee'>
                <td style='padding:4px 10px;'>
                    <div class='roboto thin' style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; color: #565c61; font-family: Roboto, sans-serif; font-size: 9pt; mso-height-rule: exactly; text-size-adjust: none;font-weight:300'>
                        Faktura
                    </div>
                </td>
                <td style='padding:4px 10px;'>
                    <div class='roboto thin' style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; color: #565c61; font-family: Roboto, sans-serif; font-size: 9pt; mso-height-rule: exactly; text-size-adjust: none;font-weight:300'>
                        Forfall
                    </div>
                </td>
            </tr>";

            foreach ($row as $l){
                
                $data = json_decode($l['data']);
                $subHTML .="<tr style='border-bottom:1px solid #eee'>";
                    $subHTML .="<td valign='middle' style='padding:10px;'>";
                        $subHTML .="<div class='roboto' style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; color: #333333; font-family: Roboto, sans-serif; font-size: 18px; mso-height-rule: exactly; text-size-adjust: none;'>";
                            $subHTML .="Faktura #".$l['invoiceid']." for ".$data->client->name;
                        
                        $subHTML .="</div>";
                    $subHTML .="</td>";
                    $subHTML .="<td valign='middle' style='padding:10px;'>";
                        $subHTML .="<div class='roboto' style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; color: #ca2f00; font-family: Roboto, sans-serif; font-size: 14px; mso-height-rule: exactly; text-size-adjust: none;'>";
                            $subHTML .=strftime('%-e %b %Y', strtotime($data->billduedate));
                        $subHTML .="</div>";
                    $subHTML .="</td>";
                $subHTML .="</tr>";

                \LogInvoiceActions::add($uid, $l['id'], 'Collectio email notice sent', $userinfo['email']);

            }
            $subHTML .="</table></div>";
            

            $html = " <div style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; font-size: 15px; font-weight: 600; line-height: 24px; padding: 10px; text-size-adjust: none;'>
            Vi ønsker å informere om at følgende faktura(er) er forfalt og vil bli videreført til vår samarbeidspartner for oppfølging (Purring/Inkasso) imorgen. 
            </div>";
            $html .= $subHTML;
            $html .= "<div style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; font-size: 15px; font-weight: 300; line-height: 24px; padding: 10px; text-size-adjust: none;'>
            ⚠️ For å unngå unødvendige kostnader, registrer innbetaling på fakturaer som er betalt. Er ikke den/de betalt, trenger du ikke å gjøre noe da de vil bli automatisk sendt for oppfølging. 
            <br><br>Hvis du vil hindre at en faktura går til oppfølging (Purring/Inkasso), kan du avbryte oppfølgingen ved å gå til fakturaen, klikke på 'Purring' og deretter på 'Avbryt oppfølging'. 
            Hvis hele beløpet blir kreditert på en faktura, vil det også hindre fakturaen fra å gå til oppfølging. Hvis du er usikker på hva du skal gjøre, kan du kontakte vår support for hjelp.
            <br><br><br>
            Med vennlig hilsen,<br>
            ".CONF_sitename."
            </div>";


            $title = (count($row) > 1) ? "Forfalte faktura som vil bli videreført til oppfølging" : "Forfalt faktura som vil bli videreført til oppfølging";

            // Send email to user
             
            try {
                $mailer = new \TemplateMailer($uid, 'company', "mail_template_empty", true);
                $mailer->
                set('PRETITLE', "Varsel om")->
                set('TITLE', $title)->
                set('HTML', $html)->
                set_recipient($userinfo['email'] ,$userinfo['name'])->
                send("");

                // $mailer = new \TemplateMailer($uid, 'company', "mail_template_empty", true);
                // $mailer->
                // set('PRETITLE', "Varsel om")->
                // set('TITLE', $title)->
                // set('HTML', $html)->
                // set_recipient('perez@bullyard.no' ,'Rodrigo Perez')->
                // send($subject);

                echo "SENDING OK <br>";

            } catch (\Exception $e) {
                echo "FAILED: Intern error: ".$e->getMessage()."<br>";
                \debug("error in ".__CLASS__."->".__FUNCTION__."()\n", $e->getMessage());
                
            }

            // success sending, set all invoices as FollowUpStage 2. This means that they have been alerted, 
            // and now when next iteration they can submitted to partner
            foreach ($row as $l){
                Metadata::set('collectio_followup_'.$l['id'], 2, $l['uid']);
            }

        }
    }
    
    public function findInvoicesForCollection() {

         // Start the output buffering to avoid timeout issues
         ob_start();

        echo "<h1>".__FUNCTION__."</h1>\n";

        $query = $this->produceQuery(self::DEADLINE, 2);
        $invoices = self::$db->q($query);

        // Start the timer
        $start = microtime(true);
        while ($invoice = self::$db->mfa($invoices)) {

            echo "Iteration created:<br>\n";

            $invoiceID = $invoice['id']; 
            $uid = $invoice['uid'];
            
            error_log('Logging '.__FUNCTION__.'("'.$invoiceID.'", "'.$uid.'") start ');
            
            // Get the start time
            $start_time = microtime(true);

            /**
             * Creates a case for the given invoice if it hasn't been created already
             * 
             * @param int $invoiceID The id of the invoice to create a case for
             * @param int $uid The user ID of the invoice owner
             * @return string The output message as a JSON-encoded string
             */
            $output = collectio_create_case_for_invoice($invoiceID, $uid);

            // Get the end time
            $end_time = microtime(true);

            // Calculate the execution time in seconds with two decimals
            $execution_time = number_format(($end_time - $start_time), 2, '.', '');

            error_log('Logging '.__FUNCTION__.'("'.$invoiceID.'", "'.$uid.'") execution time: '.$execution_time);

            if ($output['status'] == 'ok'){
                echo "Invoice id ".$invoiceID." status OK, Message:".$output['message']." (execution time: ".$execution_time.") <br><br>\n\n";
                Metadata::set('collectio_followup_'.$invoiceID, 3, $uid);
                
            }else{
                echo "Invoice id ".$invoiceID." status ERROR, Message:".$output['message']." (execution time: ".$execution_time.") <br><br>\n\n";
                debug('Function: '.__FUNCTION__.'("'.$invoiceID.'", "'.$uid.'")', "Execution time: ".$execution_time." \nReturned: ".$output['message']);
            }

            echo "Iteration ended<br>\n";

            $str =  ob_get_clean();
            echo $str;
            ob_flush();
            flush();

        }

        // End the timer
        $end = microtime(true);
        $time = round($end - $start, 2);

        echo "Total time: $time seconds<br>\n";

        // End the output buffering
        ob_end_flush();
    }


    /**
     * Checks the status of an invoice by its number 
     *
     * @param int $caseNO The number of the invoice to check.
     *
     * @return array|false The remaining amount of the invoice as a float if it exists and is numeric, or false otherwise.
     */
    public function getCaseStatus(string $caseNO) {

        if (!empty($caseNO) && strlen($caseNO) < 10){

            $obj = new GetCaseInvoicesByCaseNumber();
            $val = $obj->getInitialAndRemainingBalanace($caseNO);
            
            if (is_array($val)){
                return $val;
            } 
        }
        return false;
    }


    /**
     * Checks the status of an invoice by its number and creditor ID.
     *
     * @param int $caseNO The number of the invoice to check.
     *
     * @return array|false The remaining amount of the invoice as a float if it exists and is numeric, or false otherwise.
     */
    public function getCaseStatus2(string $caseNO, $creditorID) {

        if (!empty($caseNO) && strlen($caseNO) < 10){

            $obj = new ExportPaymentsByFilter();
            $val = $obj->getStatuses($caseNO, $creditorID);
            
            if (is_array($val)){
                return $val;
            } 
        }
        return false;
    }

    public function processCaseStatusAll(int $limit = 10){
        // Start the output buffering to avoid timeout issues
        ob_start();
       
        echo "<h1>".__FUNCTION__."</h1>\n";

        // Get all invoices that are sent to collectors and have a processing status
        $invoices = self::$db->q("SELECT 
                    i.*, ms.*, mm.meta_data as caseNO
            FROM
                invoice i
                    LEFT JOIN
                metadata mm ON mm.meta_key = CONCAT('collectio_case_', i.id)
                    LEFT JOIN
                metadata ms ON ms.meta_key = CONCAT('collectio_status_', i.id)
            WHERE
                mm.meta_data IS NOT NULL
                AND 
                ms.meta_data = 'processing'
            GROUP BY i.id");

        
        // convert to array
        $invoiceArray = array();
        while ($invoice = self::$db->mfa($invoices)) {
            $invoiceArray[] = $invoice;
        }

        // Start the timer
        $start = microtime(true);

         // split into chunks
         $chunks = array_chunk($invoiceArray, $limit);
         foreach ($chunks as $chunk) {

            echo "Iteration created:<br>\n";

            $this->processCaseStatus($chunk);
            sleep(2);
            echo "Iteration ended<br>\n";

            $str =  ob_get_clean();
            echo $str;
            ob_flush();
            flush();
         }

        // End the timer
        $end = microtime(true);
        $time = round($end - $start, 2);

        echo "Total time: $time seconds<br>\n";

        // End the output buffering
        ob_end_flush();
    }

    
    public function processCaseStatus($rows) {

        echo "<b>".__FUNCTION__."</b><br>\n";

        foreach ($rows as $invoice) {
            $invoiceID = intval($invoice['id']);
            $invoiceUID = intval($invoice['uid']);
            
            // get result
            $creditorID =  Metadata::get('collectio_creditor_id', $invoiceUID); 
            
            $apiResult = $this->getCaseStatus($invoice['caseNO']);

            // check if the remaining value is zero
            if ($apiResult['BalanceCapital'] === (float) 0){
                // invoice har been collected
                $paymentComment = "Sak løst via automatisk oppfølging.";
                $hasRegisteredPayments = $this->invoiceHasRegisteredPayments((int) $invoice['id']);

                if ($hasRegisteredPayments) {
                    // Already paid in Nettgiro: add a 0-value row only for history/log context.
                    $mysqlTime = date('Y-m-d H:i:s');
                    self::$db->q("INSERT INTO payments (refid, uid, value, datestamp, comment) VALUES ('".$invoiceID."', '".$invoiceUID."', '0', '".$mysqlTime."', '".safe_mysql($paymentComment)."')");
                    \LogInvoiceActions::add($invoiceUID, $invoiceID, 'Payment registered', currency_nor2(0));
                    \update_invoice_status($invoiceID);
                } else {
                    // No prior payment in Nettgiro: register the collected amount from Collectio.
                    $invPay = new Bullyard\Invoice\InvoicePayment($invoiceUID, $invoiceID, true);
		            $response = $invPay->register_pay("", "", (float) $apiResult['InitialCapital'], date('d.m.Y', time()), $paymentComment);
                }

                //send an email to user.
                $clientInfo = get_client_for_sending($invoice['recipient'], $invoiceUID);
                $html = "<div style='-ms-text-size-adjust: none; -webkit-text-size-adjust: none; font-size: 15px; font-weight: 300; line-height: 24px; padding: 10px; text-size-adjust: none;'>
                    Vi ønsker å informere deg om at oppfølgingssaken med id <b>".$invoice['caseNO']."</b> for faktura <b>#".$invoice['invoiceid']."</b> til <b>".htmlentities($clientInfo['clientname'])."</b> skal nå være løst. Fakturaen er nå registrert som betalt i arkivet ditt hos oss.
                    <br/><br/>
                    Vi vil fortsette å gi deg en pålitelig og effektiv tjeneste, og vi håper du er godt fornøyd med systemet vårt.
                    <br/><br/>
                    Hvis du har noen spørsmål eller bekymringer, er du velkommen til å kontakte oss når som helst.
                    <br/><br/>
                    Med vennlig hilsen,
                    <br/>
                    ".CONF_sitename."
                </div>";

                $userinfo = Bullyard\Invoice\Invoice::get_user($invoiceUID); // Get company information
                try {
                    $mailer = new \TemplateMailer($invoiceUID, 'company', "mail_template_empty", true);
                    $mailer->
                    set('PRETITLE', "")->
                    set('TITLE', "Krav på faktura #".$invoice['invoiceid']." er avsluttet")->
                    set('HTML', $html)->
                    set_recipient($userinfo['email'] ,$userinfo['name'])->
                    send("");

                } catch (\Exception $e) {
                    echo "Intern error: ".$e->getMessage()."<br>";
                    \debug("error in ".__CLASS__."->".__FUNCTION__."()\n", $e->getMessage());
                    die();
                }

                // update invoice row to payed, and change collectio_status_* metadata so it wont run again.
                Metadata::set('collectio_status_'.$invoiceID, 'completed', $invoiceUID);
                echo "Successfully updated case ".$invoice['caseNO']." invoice to completed, invoice id = ".$invoice['id']." hash = ".$invoice['hash']." <br>";

                \LogInvoiceActions::add($invoiceUID, $invoiceID, 'Collectio case completed', '');

            }else{
                // nothing has happened yet
                echo "No invoice status (".$invoice['caseNO'].") -> ".var_export($apiResult, true)." - ".var_export((float) 0, true)."<br>";
            }

            // Flush the output buffer to avoid timeout issues
            // $str = ob_get_contents();
            // echo str_pad( $str, 4096)."\n";
            // ob_flush();
            // flush();
        
        }
    }  

    private function produceQuery(int $deadlineDays = self::DEADLINE, int $FollowUpStage = 1) : string { 

        // mm.metakey in query only when followupstage is 3
        if ($FollowUpStage == 3){
            $addSql = "AND mm.meta_key IS NOT NULL";
        }else{
            $addSql = "";
        }

        return "SELECT 
                i.*
            FROM
                invoice i
                    LEFT JOIN
                invoice icheck ON icheck.refid = i.id
                    AND icheck.uid = i.uid
                    AND icheck.type = 2
                    LEFT JOIN
                metadata m ON m.meta_key = CONCAT('collectio_followup_', i.id)
                    AND meta_data = $FollowUpStage 
                    LEFT JOIN 
                metadata mm ON mm.meta_key = CONCAT('collectio_case_', i.id)
            WHERE
                        i.type = 1 
                    AND i.status = 'overdue'
                    AND i.duedate < DATE_SUB(NOW(), INTERVAL $deadlineDays DAY)
                    AND icheck.id IS NULL
                    AND m.meta_key IS NOT NULL
                    ".$addSql."
            GROUP BY i.id";
    }

    private function invoiceHasRegisteredPayments(int $invoiceID) : bool
    {
        $result = self::$db->q("SELECT id FROM payments WHERE refid = '".intval($invoiceID)."' AND status = 1 AND value < 0 LIMIT 1");
        return $result && $result->num_rows > 0;
    }
    
  }
  

