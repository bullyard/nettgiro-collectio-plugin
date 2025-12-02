<?php

if(__FILE__ == $_SERVER['SCRIPT_FILENAME'])
    die("This file cannot be executed directly");

use \Bullyard\Predator\ExportStatusByFilter;

if (ob_get_level()) {
    ob_end_flush();
}
ob_implicit_flush(true);

$adminUID = 1;
$predator_id = PREDATOR_AUTH_CLIENTNO;


if ($_SESSION['admin']==1){

    $db = DB::getInstance();

    // fist get max and min values from invoices table where creditor_id = $predator_id and status = 'processing'
    $q = "SELECT 
            m.meta_data as predator_id, l.*, i.value, SUBSTRING(l.misc, 14) AS caseno, u.email, u.name
        FROM
            nettgiro.log_services l
            LEFT join invoice i on l.ref_id = i.id
            left join metadata m on m.uid = l.uid and m.meta_key = 'collectio_creditor_id'
            left join users u on u.uid = l.uid
        WHERE
            l.type = 'collectio' AND l.uid != 1 having predator_id is not null
        ORDER BY l.datestamp ASC, predator_id ASC";

    $stmt = $db->prepare($q);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (empty($data)) {
        echo "No invoices found for creditor: " . $predator_id;
        die();
    }

    $creditors = [];

    // group by creditor id
    while ($row = $result->fetch_assoc()) {
        // set cases by creditor id (predator_id)
        $creditors[$row['predator_id']][] = $row;
    }

    // print for debug  
    // echo "<pre>";
    // die(var_export($statuses, true));

    // (object) array(
    //     'PaymentSet' => 
    //    (object) array(
    //       'Payment' => 
    //      array (
    //        0 => 
    //        (object) array(
    //           'Creditor' => 
    //          (object) array(
    //             'CreditorNO' => '004386',
    //          ),
    //           'CaseNumber' => '243516',
    //           'ReferenceNumberPrimary' => 'A-001660',
    //           'DebtorPersonalIdentificationNumber' => '998270065',
    //           'DateOfPayment' => '2024-09-13',
    //           'Capital' => '0',
    //           'Receipt' => 
    //          (object) array(
    //             'ReferenceNumber' => 'A-001660',
    //             'OriginalImportDataLineNo' => 1,
    //             'ActionCode' => '1039',
    //             'CreditorNo' => '004386',
    //             'CaseDebtCollectionBalance' => 
    //            (object) array(
    //               'EFee' => '0',
    //               'FFee' => '0',
    //               'Capital' => '5952',
    //               'ConvertFee' => '0',
    //               'CostInterest' => '0',
    //               'Overpaid' => '0',
    //               'SSalary' => '0',
    //               'Salary1' => '35',
    //               'Salary2' => '0',
    //               'Interest' => '103.67',
    //            ),
    //             'CaseDebtCollectionStatusCode' => '1039',
    //             'CaseDebtCollectionPaidInterest' => '0',
    //          ),
    //           'ReferenceNumberSecondary' => '',
    //           'InvoiceText' => 'Inkassovarsel sendt',
    //           'CustomerId' => '5900000243516592',
    //        ),
    //        1 => 
    //        (object) array(
    //           'Creditor' => 
    //          (object) array(
    //             'CreditorNO' => '004386',
    //          ),
    //           'CaseNumber' => '243517',
    //           'ReferenceNumberPrimary' => 'A-001663',
    //           'DebtorPersonalIdentificationNumber' => '915964699',
    //           'DateOfPayment' => '2024-09-13',
    //           'Capital' => '0',
    //           'Receipt' => 
    //          (object) array(
    //             'ReferenceNumber' => 'A-001663',
    //             'OriginalImportDataLineNo' => 1,
    //             'ActionCode' => '1039',
    //             'CreditorNo' => '004386',
    //             'CaseDebtCollectionBalance' => 
    //            (object) array(
    //               'EFee' => '0',
    //               'FFee' => '0',
    //               'Capital' => '7440',
    //               'ConvertFee' => '0',
    //               'CostInterest' => '0',
    //               'Overpaid' => '0',
    //               'SSalary' => '0',
    //               'Salary1' => '35',
    //               'Salary2' => '0',
    //               'Interest' => '129.59',
    //            ),
    //             'CaseDebtCollectionStatusCode' => '1039',
    //             'CaseDebtCollectionPaidInterest' => '0',
    //          ),
    //           'ReferenceNumberSecondary' => '',
    //           'InvoiceText' => 'Inkassovarsel sendt',
    //           'CustomerId' => '5900000243517599',
    //        ),
    //        2 => 



   ?>

<main class="container">
    <div class="row">

        <!--  Main content  -->
        <div class="col-xl-12">

            <div class="row align-items-center mainTitleRow">
                <div class="col-sm-6 mt-4">
                    <h4 class='mt-0'>Collectio Saker</h4>

                </div>
                <div class="col-sm-6">
                    <div class="container mt-4 mt-sm-3 mt-md-4">
                        <div class="row justify-content-end">


                        </div>
                    </div>
                </div>
            </div>

            <hr class="d-none d-md-block mt-0">



            <div class="row mt-2 ">
                <div class="col-md-12 order-md-0">
                    <div class="card card-subtle bg-white p-md-5 pt-md-2">
                        <div class="card-body p-0">

                            

                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class='card-title mt-md-0 pb-2'>
                                    creditors and cases
                                </h4>

                            </div>
                           

                            <div class="row">
                                <?php

                                    foreach ($creditors as $creditor_id => $creditor) {

                                        $max_case_number = null;
                                        $min_case_number = null;

                                        if (count($creditor) == 1) {
                                            $max_case_number = $creditor[0]['caseno'];
                                            $min_case_number = $creditor[0]['caseno'];
                                        }else{
                                            foreach ($creditor as $case) {
                                                if (empty($max_case_number) || $case['caseno'] > $max_case_number) {
                                                    $max_case_number = $case['caseno'];
                                                }
                                                if (empty($min_case_number) || $case['caseno'] < $min_case_number) {
                                                    $min_case_number = $case['caseno'];
                                                }
                                            }
                                        }

                                        // show creditor id and case numbers under each creditor as dt and dd
                                        echo "<div class='col-md-12'>";
                                        echo "<dl>";
                                        echo "<dt data-max='".$max_case_number."'  data-min='".$min_case_number."'  data-id='".$creditor_id."' >Creditor: <a class='creditor' href='#'>".$creditor_id."</a> - ".$creditor[0]['name']." - ".$creditor[0]['email']."</dt>";
                                            foreach ($creditor as $case) {
                                                $commission = $case['value'] * 0.08;
                                                // 80% of commission goes to creditor
                                                $commissionMe = $commission * 0.8;
                                                echo "<dd>
                                                    <div class='row'>
                                                        <div class='col-md-6'>
                                                            caseNo: ".$case['caseno']." date: ".format_time_form($case['datestamp'])." value: NOK: ".currency_nor2($case['value'])."<br>
                                                            <b>Commission 8% </b> ".currency_nor2($commission, ' ', 0)." NOK -&gt; To me: ".currency_nor2($commissionMe, ' ', 0)." NOK<br>
                                                            <a class='payments' data-id='".$creditor_id."' data-case='".$case['caseno']."' href='#'>Payments</a>
                                                            <div id='CASENO_".$case['caseno']."'></div>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            status: <div id='".$case['caseno']."'></div>
                                                        </div>
                                                        
                                                </dd>";
                                            }
                                        echo "</dl>";
                                        echo "</div>";
                                    }

                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>

    // onload jquery
    $(function() {

        // credit line using ajax call on click
        $('body').on('click', '.creditor', function(event) {
            event.preventDefault();
            var url = "/plugins/collectio/ajax/ajax.php";
            var row = $(this).closest('dt');
            var creditor_id = row.data('id');
            var max_case_number = row.data('max');
            var min_case_number = row.data('min');

            $.ajax({
            url: url,
            type: "GET",
            dataType: "JSON",
            data: {
                    req: 'get_case_status',
                    min_case_number: min_case_number,
                    max_case_number: max_case_number,
                    creditor_id: creditor_id
                }
            })
            .done(function(response) {
               
                 // response:
                // {"result":{"865941":["903","903","903","903","903","903","903","903","903"],"868746":["912","912","912","912","912","912"]},"uniqueStatuses":{"903":"903","912":"912"}}
                   
                // if response.result is not empty
                if (response.result) {

                    // loop through response.result and set status in div where key is case number
                    $.each(response.result, function(caseNo, statuses) {
                        html = '<ul>';
                        $.each(statuses.ActionCode, function(i, actionCodes) {
                            html += '<li>'+actionCodes+'</li>';
                        });
                        html += '</ul>';
                        
                        $('#'+caseNo).html('CurrentStatus: '+statuses.StatusText+'<br>ActionCodes: '+html);    
                    });
                   

                }else{
                    $.fn.alert('En feil oppstod, sjekk logg for mer informasjon');
                }
            
            });
        });


        // credit line using ajax call on click
        $('body').on('click', '.payments', function(event) {
            event.preventDefault();
            var url = "/plugins/collectio/ajax/ajax.php";
            row = $(this);
            var creditor_id = row.data('id');
            var case_number = row.data('case');

            $.ajax({
            url: url,
            type: "GET",
            dataType: "JSON",
            data: {
                    req: 'get_case_payments',
                    case_number: case_number,
                    creditor_id: creditor_id
                }
            })
            .done(function(response) {
               
                // if response.result is not empty
                if (response.result) {
                    // response: {"result":{"totalPayed":5564.67,"payments":[{"value":"4381.45","date":"15-01-2024"},{"value":"1183.22","date":"21-02-2024"}]}}

                   console.log(response.result);
                   if (response.result.totalPayed) {
                        html = '<ul>';
                        $.each(response.result.payments, function(i, payment) {
                            // format currency with js
                            html += '<li>'+payment.date+' - '+payment.value+' NOK</li>';
                        });
                        html += '</ul>';
                        html += '<b>Total payed: '+response.result.totalPayed+' NOK</b>';
                        $('#CASENO_'+case_number).html(html);
                     }else{
                        $('#CASENO_'+case_number).html('No payments found');
                     }
                }else{
                    $.fn.alert('En feil oppstod, sjekk logg for mer informasjon');
                }
            });
        });
    });

</script>

<?php
} else {
    echo "Du har ikke tilgang til denne siden";
}