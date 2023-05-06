<?php if (CONF_collectio_enable) { 
    
    // check if user has accepted terms
    $acepptedTerms = Metadata::get('collectio_terms', $uid);
    ?>
 <div
    class="col-form-label col-12 col-sm-8  pl-4 offset-sm-4 custom-control custom-checkbox">
    <input type="checkbox" name="collectionService" id="collectionService" class="custom-control-input"
        value='1' <?=($acepptedTerms?"":"disabled='disabled'")?> <?=($acepptedTerms?"checked='checked'":"")?> />
    <label class="custom-control-label" for="collectionService">
        Automatisk purring/inkasso ved manglende betaling.
        <?php 
            if ($acepptedTerms == false){
                echo '<a href="#" class="badge badge-warning rounded-circle badge-warning-alt ml-2 " data-toggle="collapse" data-target="#infoPluginAcceptTerms" aria-expanded="false" aria-controls="infoPluginAcceptTerms">?</a>';
            }
        ?>
    </label>
    
    <div class="form-group form-row mb-0" id="collectionServiceInfo"
        >
        <?php 
            if ($acepptedTerms !== false){
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

<?php } ?>