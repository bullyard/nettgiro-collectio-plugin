<?php

if(__FILE__ == $_SERVER['SCRIPT_FILENAME'])
    die("This file cannot be executed directly");

$adminUID = 1;

if ($_SESSION['admin']==1){

    // query based on log_services
    $q1 = "SELECT 
            'success' AS soap_type,
            DATE(datestamp) AS date,
            COUNT(*) AS count
        FROM
            log_services
        WHERE
            type = 'collectio'
            AND datestamp > NOW() - INTERVAL 3 MONTH
            /* AND uid != ".$adminUID." */
        GROUP BY DATE(datestamp)
        order by DATE(datestamp) asc";

    // query based on log_soap
    $q2 ="SELECT 
            soap_type, 
            DATE(datestamp) as date, 
            COUNT(*) as count
        FROM 
            log_soap 
        WHERE 
            info2 LIKE '%ImportCasesByProxy%'
             AND datestamp > NOW() - INTERVAL 3 MONTH 
            /* AND uid != ".$adminUID." */
        GROUP BY 
            soap_type, 
            DATE(datestamp);";


    $chartQuery = q($q2);

    $data = [];
    
    // Initialize the date range
    $endDate = new DateTime(); // Today
    $startDate = (new DateTime())->modify('-3 months'); // 3 months ago
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);

    // Fill the array with zeroes for all dates in the period
    foreach ($period as $day) {
        $data[$day->format("Y-m-d")] = ['success' => 0, 'error' => 0];
    }

    // Populate the array with actual data
    while($row = mfa($chartQuery)) {
        $data[$row['date']][$row['soap_type']] = $row['count'];
    }

    // Prepare data for Chart.js
    $chartData = [
        'labels' => [],
        'success_counts' => [],
        'error_counts' => []
    ];

    foreach ($data as $date => $types) {
        $chartData['labels'][] = $date;
        $chartData['success_counts'][] = $types['success'];
        $chartData['error_counts'][] = $types['error'];
    }

    
	
	
	$resCumulative = q("SELECT meta_data, count(meta_data) as cnt FROM metadata where meta_key like 'collectio_status_%' and meta_data != 'canceled' group by meta_data;");

    $cumulative = array("processing" => 0, "queued"=>0 , "completed"=>0);
	while ($rowc = mfa($resCumulative)) {
		$cumulative[$rowc['meta_data']] = $rowc['cnt'];
	}

    // get list of processing
    $resList = q("SELECT 
                        i.status,
                        i.hash,
                        i.id,
                        i.uid,
                        i.invoiceid,
                        i.recipient,
                        i.value,
                        i.datestamp,
                        i.duedate,
                        m.datestamp AS meta_datestamp,
                        mm.meta_data AS caseno,
                        mmm.meta_data AS creditor_id
                    FROM
                        metadata m
                            LEFT JOIN
                        invoice i ON i.id = SUBSTR(m.meta_key, 18)
                            LEFT JOIN
                        metadata mm ON mm.meta_key = CONCAT('collectio_case_',
                                SUBSTR(m.meta_key, 18))
                            LEFT JOIN 
                        metadata mmm ON mmm.meta_key = 'collectio_creditor_id' and mmm.uid = i.uid
                              
                    WHERE
                        m.meta_key LIKE 'collectio_status_%'
                            AND m.meta_data = 'processing'");

     // get list of completed
        $resListCompleted = q("SELECT 
                            i.status,
                            i.hash,
                            i.id,
                            i.uid,
                            i.invoiceid,
                            i.recipient,
                            i.value,
                            i.datestamp,
                            i.duedate,
                            m.datestamp AS meta_datestamp,
                            m.meta_data AS collectio_status,
                            mm.meta_data AS caseno,
                            mmm.meta_data AS creditor_id
                        FROM
                            metadata m
                                LEFT JOIN
                            invoice i ON i.id = SUBSTR(m.meta_key, 18)
                                LEFT JOIN
                            metadata mm ON mm.meta_key = CONCAT('collectio_case_',
                                    SUBSTR(m.meta_key, 18))
                                LEFT JOIN 
                            metadata mmm ON mmm.meta_key = 'collectio_creditor_id' and mmm.uid = i.uid
                                
                        WHERE
                            m.meta_key LIKE 'collectio_status_%'
                                AND m.meta_data = 'completed' and  mm.meta_data is not null");

         // get list of completed
         $resListRest = q("SELECT 
                            i.status,
                            i.hash,
                            i.id,
                            i.uid,
                            i.invoiceid,
                            i.recipient,
                            i.value,
                            i.datestamp,
                            i.duedate,
                            m.datestamp AS meta_datestamp,
                            m.meta_data AS collectio_status,
                            mm.meta_data AS caseno,
                            mmm.meta_data AS creditor_id
                        FROM
                            metadata m
                                LEFT JOIN
                            invoice i ON i.id = SUBSTR(m.meta_key, 18)
                                LEFT JOIN
                            metadata mm ON mm.meta_key = CONCAT('collectio_case_',
                                    SUBSTR(m.meta_key, 18))
                                LEFT JOIN 
                            metadata mmm ON mmm.meta_key = 'collectio_creditor_id' and mmm.uid = i.uid
                                
                        WHERE
                            m.meta_key LIKE 'collectio_status_%'
                                AND m.meta_data not in ('completed', 'processing') and  mm.meta_data is not null");

    // potential earnings
    $potRes = q("SELECT 
                    meta_data, COUNT(meta_data) AS cnt, SUM(i.value) as potential
                FROM
                    metadata m
                        LEFT JOIN
                    invoice i ON i.id = SUBSTR(m.meta_key, 18)
                WHERE
                    m.meta_key LIKE 'collectio_status_%'
                    and
                    m.meta_data in ('processing', 'completed')
                GROUP BY m.meta_data;");

    $potential = array("processing" => 0, "completed"=>0);
    while ($rowp = mfa($potRes)) {
        $potential[$rowp['meta_data']] = $rowp['potential'];
    }


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

                            <?
                               //echo icon_button("?go=portal", "Oversikten", "back", "btn btn-sm btn-special-home mt-1 col");
                               //echo "viser: ".count($resultList);
                            ?>


                        </div>
                    </div>
                </div>
            </div>

            <hr class="d-none d-md-block mt-0">



            <div class="row mt-2 ">
                <div class="col-md-12 order-md-0">
                    <div class="card card-subtle bg-white p-md-5 pt-md-2">
                        <div class="card-body p-0">

                            <div class="container-fluid">
                                <canvas id="soapTypeChart"></canvas>
                            </div>

                            <script>
                                const chartData = <?php echo json_encode($chartData); ?>;

                                const ctx = document.getElementById('soapTypeChart').getContext('2d');
                                const soapTypeChart = new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: chartData.labels,
                                        datasets: [{
                                            label: 'Success',
                                            data: chartData.success_counts,
                                            backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                            stack: 'Stack 0',
                                        }, {
                                            label: 'Error',
                                            data: chartData.error_counts,
                                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                            stack: 'Stack 0',
                                        }]
                                    },
                                    options: {
                                        scales: {
                                            y: {
                                                beginAtZero: true
                                            }
                                        }
                                    }
                                });
                            </script>


                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class='card-title mt-md-0 pb-2'>
                                    Cumulative
                                </h4>

                            </div>
                           

                            <div class="row">
                                <?php foreach ($cumulative as $status => $value): ?>
                                    <div class="col-md-3">
                                        <div class="box bg-light py-4 text-center">
                                        <?php echo '<h5 class="m-0">'.$status . '</h5><h4 class="m-0"><b>' . $value.'</b></h4>'; 
                                        
                                        if (array_key_exists( $status, $potential)) {
                                            echo '<h6 class="m-0">Potential: <b>' . currency_nor2($potential[$status]).'</b></h6>'; 
                                        }
                                        
                                        ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>


                            <div class="d-flex justify-content-between align-items-center mt-5">
                                <h4 class='card-title mt-md-0 pb-2'>
                                    List of processing
                                </h4>
                            </div>
                          

                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table table-striped table-hover table-sm">
                                        <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">UID</th>
                                            <th scope="col">CASENO</th>
                                            <th scope="col">CREDITOR</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Invoice</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col" class="text-right">Amount</th>
                                            <th scope="col" class="text-right">Metadata date</th>
                                            <th scope="col" class="text-right">Due Date</th>
                                            <th scope="col" class="text-right">krediter</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($row = mfa($resList)): ?>
                                            <tr data-row="<?=$row['hash']?>">
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo $row['uid']; ?></td>
                                                <td><a href="#" class="openDialog" data-hash="<?=$row['hash']?>"><?php echo $row['caseno']; ?></a></td>
                                                <td><?php echo $row['creditor_id']; ?></td>
                                                <td><?php echo $row['status']; ?></td>
                                                <td><?php echo $row['invoiceid']; ?></td>
                                                <td><?php echo $row['recipient']; ?></td>
                                                <td class="text-right"><?php echo $row['value']; ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['meta_datestamp']); ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['duedate']); ?></td>
                                                <td class="text-right"><a href="#" class="creditLine">krediter</a></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-5">
                                <h4 class='card-title mt-md-0 pb-2'>
                                    List of completed
                                </h4>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table table-striped table-hover table-sm">
                                        <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">UID</th>
                                            <th scope="col">CASENO</th>
                                            <th scope="col">CREDITOR</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Invoice</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col" class="text-right">Amount</th>
                                            <th scope="col" class="text-right">Metadata date</th>
                                            <th scope="col" class="text-right">Due Date</th>
                                            <td class="text-right"><a href="#" class="creditLine">krediter</a></td>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($row = mfa($resListCompleted)): ?>
                                            <tr data-row="<?=$row['hash']?>">
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo $row['uid']; ?></td>
                                                <td><a href="#" class="openDialog" data-hash="<?=$row['hash']?>"><?php echo $row['caseno']; ?></a></td>
                                                <td><?php echo $row['creditor_id']; ?></td>
                                                <td><?php echo $row['status']; ?> (<?=$row['collectio_status']?>)</td>
                                                <td><?php echo $row['invoiceid']; ?></td>
                                                <td><?php echo $row['recipient']; ?></td>
                                                <td class="text-right"><?php echo $row['value']; ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['meta_datestamp']); ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['duedate']); ?></td>
                                                <td class="text-right"><a href="#" class="creditLine">krediter</a></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>


                            <div class="d-flex justify-content-between align-items-center mt-5">
                                <h4 class='card-title mt-md-0 pb-2'>
                                    List of rest
                                </h4>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table table-striped table-hover table-sm">
                                        <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">UID</th>
                                            <th scope="col">CASENO</th>
                                            <th scope="col">CREDITOR</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Invoice</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col" class="text-right">Amount</th>
                                            <th scope="col" class="text-right">Metadata date</th>
                                            <th scope="col" class="text-right">Due Date</th>
                                            <td class="text-right"><a href="#" class="creditLine">krediter</a></td>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($row = mfa($resListRest)): ?>
                                            <tr data-row="<?=$row['hash']?>">
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo $row['uid']; ?></td>
                                                <td><a href="#" class="openDialog" data-hash="<?=$row['hash']?>"><?php echo $row['caseno']; ?></a></td>
                                                <td><?php echo $row['creditor_id']; ?></td>
                                                <td><?php echo $row['status']; ?> (<?=$row['collectio_status']?>)</td>
                                                <td><?php echo $row['invoiceid']; ?></td>
                                                <td><?php echo $row['recipient']; ?></td>
                                                <td class="text-right"><?php echo $row['value']; ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['meta_datestamp']); ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['duedate']); ?></td>
                                                <td class="text-right"><a href="#" class="creditLine">krediter</a></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
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
        $('body').on('click', '.creditLine', function(event) {
            event.preventDefault();
            var row = $(this).closest('tr');
            var hash = row.data('row');
            var url = "/plugins/collectio/ajax/ajax.php";

            $.fn.dialogue({
                title: "Trekk saken fra oppfølging",
                content: "<center>Vil du trekke denne saken fra Collectio?</center>",
                closeIcon: true,
                buttons: [
                    
                    {
                        text: "KREDITER",
                        id: $.utils.createUUID(),
                        click: function($modal) {
                          
                            // do ajax call
                            $.ajax({
                                url: url,
                                type: "GET",
                                dataType: "JSON",
                                data: {
                                        req: 'credit_line',
                                        hash: hash
                                    }
                                })
                                .done(function(response) {
                                    $modal.dismiss();
                                    // if response.result is not empty
                                    if (response.status && response.status.length > 0) {
                                        // create html
                                        if (response.status == 'success'){
                                            var message = '<div class="row"><div class="col-md-12"><p>Saken er trukket fra Collectio</p></div></div>';
                                        }else{
                                            var message = '<div class="row"><div class="col-md-12"><p>Det oppstod en feil</p></div></div>';
                                        }

                                        $.fn.dialogue({
                                            title: "Kreditering",
                                            content: message,
                                            closeIcon: true,
                                            buttons: [
                                                {
                                                    text: "Lukk",
                                                    id: $.utils.createUUID(),
                                                    click: (function(currentResult) { //IIFE = Immediately Invoked Function Expression
                                                        return function($modal) {
                                                            // find row by data-row that contians a hash and remove row from html if success
                                                            if (currentResult.status == 'success'){
                                                                // remove fading
                                                                $('tr[data-row="'+hash+'"]').fadeOut(500, function() {
                                                                    $(this).remove();
                                                                });
                                                                $modal.dismiss();

                                                            }else{
                                                                $.fn.alert('En feil oppstod, sjekk logg for mer informasjon');
                                                                $modal.dismiss();
                                                            }
                                                        }
                                                    })(response)
                                                }
                                            ]
                                        });
                                    }else{
                                        $.fn.alert('En feil oppstod, sjekk logg for mer informasjon');
                                    }
                                
                                });
                        }
                    },
                    {
                        text: "NEI",
                        id: $.utils.createUUID(),
                        click: function($modal) {
                            $modal.dismiss();
                        }
                    }
                ]
            });
        });

            
            

        // open bootstrap 4 dialog
        $(".openDialog").click(function(event) {
            event.preventDefault();
            var hash = $(this).data('hash');
            var url = "/plugins/collectio/ajax/ajax.php";
            // do ajax call
            $.ajax({
                url: url,
                type: "GET",
                dataType: "JSON",
				data: {
                        req: 'get_status_by_payments',
                        hash: hash
                    }
				})
                .done(function(data) {
                    // if data.result is not empty
                    if (data.result.length > 0) {
                        // create html
                        var html = '<div class="row"><div class="col-md-12"><table class="table table-striped table-hover table-sm"><thead><tr><th scope="col">Status</th></tr></thead><tbody>';
                        $.each(data.result, function(i, item) {
                            html += '<tr><td>'+item+'</td></tr>';
                        });
                        html += '</tbody></table></div></div>';
                       
                    }else{
                        var html = '<div class="row"><div class="col-md-12"><p>Ingen data</p></div></div>';
                    }
                   
                    //console.log(html);
                    // show dialog
                    $.fn.dialogue({
                        title: "Status fra predator",
                        content: html,
                        closeIcon: true,
                        buttons: [
                            {
                                text: "Lukk",
                                id: $.utils.createUUID(),
                                click: function($modal) {
                                    $modal.dismiss();
                                }
                            }
                        ]
                    });
                });
           

            return false;
        });
    });

</script>

<?php
} else {
    echo "Du har ikke tilgang til denne siden";
}