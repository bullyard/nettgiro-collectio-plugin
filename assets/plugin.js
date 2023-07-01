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


    $('body').on('click', '#btnStopCollection', function(event) {
        event.preventDefault();
        $.fn.dialogue({
            title: "Avbryt automatisk oppfølging",
            content: $("<div />").attr('id','createGroupDialog').html('<div style="padding:30px;text-align:center;">Er du sikker på at faktura ikke skal bli automatisk fulgt opp etter forfall ved manglende betaling?</div>'),
            closeIcon: true,
            buttons: [
                { text: "JA", id: $.utils.createUUID(), click: function ($modal) {

                $.ajax({
                    url: '/plugins/collectio/ajax/ajax.php',
                    type: 'GET',
                    dataType: 'json',
                    data: $('#btnStopCollection').data('params')
                })
                .done(function(data) {
                    $modal.dismiss();
                    top.location.href = location.href;
                });


                } },
                { text: "NEI", id: $.utils.createUUID(), click: function ($modal) { $modal.dismiss(); } }
            ]
        }).on('shown.bs.modal', function (e) {

        }).on('hidden.bs.modal', function (e) {
            $('#createGroupDialog').remove();
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