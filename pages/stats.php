<?

if(__FILE__ == $_SERVER['SCRIPT_FILENAME'])
    die("This file cannot be executed directly");

if ($_SESSION['admin']==1){
	
	$adminUID = 1;
	$resCumulative = q("SELECT meta_data, count(meta_data) as cnt FROM metadata where meta_key like 'collectio_status_%' group by meta_data;");

    $cumulative = array("processing" => 0, "queued"=>0 , "canceled"=>0, "completed"=>0);
	while ($rowc = mfa($resCumulative)) {
		$cumulative[$rowc['meta_data']] = $rowc['cnt'];
	}

    // get list of preocessing
    $resList = q("SELECT 
                        i.id, i.uid, i.invoiceid, i.recipient, i.value, i.datestamp, i.duedate, m.datestamp as meta_datestamp
                    FROM
                        metadata m
                            LEFT JOIN
                        invoice i ON i.id = SUBSTR(m.meta_key, 18)
                    WHERE
                        m.meta_key LIKE 'collectio_status_%'
                            AND m.meta_data = 'processing';");


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
                                            <th scope="col">Status</th>
                                            <th scope="col">Invoice</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col" class="text-right">Amount</th>
                                            <th scope="col" class="text-right">Metadata date</th>
                                            <th scope="col" class="text-right">Due Date</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($row = mfa($resList)): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo $row['uid']; ?></td>
                                                <td><?php echo $row['status']; ?></td>
                                                <td><?php echo $row['invoiceid']; ?></td>
                                                <td><?php echo $row['recipient']; ?></td>
                                                <td class="text-right"><?php echo $row['value']; ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['meta_datestamp']); ?></td>
                                                <td class="text-right"><?php echo format_time_form($row['duedate']); ?></td>
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

<?
} else {
    echo "Du har ikke tilgang til denne siden";
}