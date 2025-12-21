<?php


include($_SERVER['DOCUMENT_ROOT']."/config/config.php");

$connection = db_connect();

// decrypt token if passed
if ($_REQUEST['token']){
	$token = $_REQUEST['token'];
	extract(EncryptUID::decrypt($_REQUEST['token']));
}

if ($_REQUEST['slug'] && !empty($currentSlug)){
	$currentSlug = $_REQUEST['slug'];
}


if ($_REQUEST['ReqDialog']=="plugin_show_terms"){
    $title = "Godta brukervilkår og betingelser for avtale med Collectio AS";
    $ret = "ret";
    //$terms = file_get_contents(SERVER_DOC_ROOT."/plugins/collectio/terms.php");
    $terms = "
        <div  class='pt-3 pt-lg-4 pb-3 pb-lg-4'>
        <h5><b>Fordeler ved å bruke vår partner for automatisk oppfølging av ubetalte regninger.<b></h5>
        <hr class='spacer'>
        <ul class='pt-3'>
            <li><h5><b><em>Ingen</em></b> etableringsavgift</h5></li>
            <li><h5><b><em>Gratis</em></b> utsendelse av inkassovarsel.</h5></li>
            <li><h5><b><em>Ingen</em></b> månedsavgift</h5></li>
            <li><h5><b><em>8%</em></b> provisjon på inkasso, du beholder resten.</h5></li>
        </ul>
        <small>
            <!--a href='/terms' target='_blank'>Les fullsteding vilkår på ved å klikke her.</a><br><br-->
            <em>Foretakets kontaktinformasjon blir delt med partner ved inngåelse av avtale med partner. Relevant faktura-inforamsjon blir også delt med Collectio AS ved innending av saker.<em>
        </small>
        </div>
    ";
    $terms .= "<style>.popover {z-index: 1050;}</style>
        <div class='mt-3 custom-control custom-checkbox text-left align-top' id='confirmAccountCreationContainer'>
            <input type='checkbox' class='custom-control-input' name='confirmAccountCreation' id='confirmAccountCreation' value='true'>
            <label class='custom-control-label' for='confirmAccountCreation'><a href='/terms' target='_blank' class='font-weight-bold'>Jeg godtar vilkårene</a> og ønsker å opprette gratis konto hos Collectio AS.</label>
        </div>";
    echo json_encode(array("status"=> "ok", "title"=> $title, "html" => $terms, "button"=> "Fullfør"));
    
}else{
	echo json_encode(array("status"=> "failed", "message" => "Kunne ikke hente dialog. Vennlist oppdater siden og prøv igjen."));
}
