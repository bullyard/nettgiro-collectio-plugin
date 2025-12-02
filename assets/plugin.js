$(function() {
    
    $('.collectio_accept_terms').on('click', function(event) {
        event.preventDefault();

        $('.modal').modal('hide');

		$.ajax({
				url: '/plugins/collectio/ajax/modal_dialog.php',
				type: 'GET',
				dataType: 'JSON',
				data: {
					ReqDialog: 'plugin_show_terms'
				}
			})
			.done(function(response) {
				$.fn.dialogue({
                    title: response.title,
                    content: response.html,
                    large:true,
                    closeIcon: true,
                    buttons: [{
                            text: response.button,
                            id: $.utils.createUUID(),
                            click: function($modal) {
                                
                                // check if check box is checked
                                if ($('#confirmAccountCreation').is(":checked") == true) {
                                    $modal.dismiss();
                                    collectio_accept_terms();
                                } else {
                                    do_tip_global('#confirmAccountCreationContainer label', 'Du må bekrefte før du kan fullføre registreringen.', 'top', '.modal');

                                }

                            }
                        },
                        {
                            text: "Avbryt",
                            id: $.utils.createUUID(),
                            click: function($modal) {
                                $modal.dismiss();
                            }
                        }
                    ]
                });
			});

        
    });

    $('#collectionService:disabled + .toggle').on('click', function(event) {
        event.preventDefault();
       $(this).toggle(); 
      
    });

    function collectio_accept_terms($modal){

        // set loader
        show_loader();

        $.ajax({
            url: '/plugins/collectio/ajax/ajax.php',
            type: 'GET',
            dataType: 'JSON',
            data: {
                req: 'collectio_accept_terms'
            }
        })
        .done(function(response) {

            hide_loader();
            if (response.status == "ok"){
                $.fn.alert(response.message, "Vellykket");
            }else if (response.status == "error"){
                $.fn.alert(response.error, "Misslykket");
            }else if (response.internal){
                $.fn.alert(response.internal, "Misslykket");
            }else{
                $.fn.alert("Kunne ikke fullføre", "Misslykket");
            }
        });
    }

    $('body').on('click', '#reminderOptions .reminderSendButtonAlt', function(e) {
		var url = $(this).data('ajax') || 'ajax/invoiceReq.php';
		
		invoice_sendToCollection('#reminderSend', url);
	});

    $('#collectionService').on('change', function() {
        if ($('#collectionService').is(':checked')){
            $('#collectio_show_price').show();
        }else{
            $('#collectio_show_price').hide();
        }
    });

    $('body').on('click', '#btnToggleCollection', function(event) {
        event.preventDefault();
        const $btn = $('#btnToggleCollection'); // Cache the button jQuery object
        const btnData = $btn.data('params');
        let serviceState = btnData.service_is_active; // get service state from the button data-params
        
        // Set the 'req' value and execute the AJAX call
        btnData.req = serviceState ? 'delete_case' : 'queue_case'; 
    
        $.ajax({
            url: '/plugins/collectio/ajax/ajax.php',
            type: 'GET',
            dataType: 'json',
            data: btnData
        })
        .done(function(data) {
            if(data.status === 'ok') {
                serviceState = !serviceState;
                btnData.service_is_active = serviceState; // update service status in button data-params
                
                if(serviceState) {
                    $.fn.alert('<center>Tjenesten er nå PÅ<br><em><small>Merk at dette er en betalt tjeneste. Dersom saken blir overført for ekstern oppfølging vil det påløpe overføringskostnad på NOK 35.-</small></em></center>', 'Melding', function(){window.location.reload()});
                    $btn.removeClass('btn-danger').addClass('btn-success'); // switch class to btn-success
                    
                    $(".action-text").text(" Oppfølging: PÅ");
                } else {
                    $.fn.alert('Tjenesten er nå AV', 'Melding', function(){window.location.reload()});
                    $btn.removeClass('btn-success').addClass('btn-danger'); // switch class to btn-danger
                    $(".action-text").text(" Oppfølging: AV");
                }
    
            } else if (data.status === 'failed') {
                $.fn.alert('En feil oppstod. Vennligst prøv igjen.', 'Melding', function(){window.location.reload()});
            }
            //top.location.href = location.href;
        });
    });
    
});

function invoice_sendToCollection(formID, url) {

    $.fn.confirm("Ønsker du å sende saken for oppfølging. ", (ans) => {
        if (ans) {
            show_loader();

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'JSON',
                data: $(formID).serializeArray(),
                beforeSend: function() {
                    $(formID).prop('disabled', true);
                }
            })
            .done(function(jsondata) {

                hide_loader();

                if (jsondata.status == "error") {
                    $.fn.alert(jsondata.message);

                }else if (jsondata.status == "ok") {

                    $.fn.alert(jsondata.message, 'Velykket', function(){top.location.href = location.href});
                
                } else if (jsondata.internal) {
                    $.fn.alert(jsondata.internal);
                }

            }).always(function() {
                $(formID).prop('disabled', false);
            });

        return false;
        
        }
    });   
}