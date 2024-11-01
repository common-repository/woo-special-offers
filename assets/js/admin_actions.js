
  						var is_blocked = function( $node ) {
							return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
						};

						/**
						 * Block a node visually for processing.
						 *
						 * @param {JQuery Object} $node
						 */
						var block = function( $node ) {
							if ( ! is_blocked( $node ) ) {
								$node.addClass( 'processing' ).block( {
									message: null,
									overlayCSS: {
										background: '#fff',
										opacity: 0.6
									}
								} );
							}
						};

						/**
						 * Unblock a node after processing is complete.
						 *
						 * @param {JQuery Object} $node
						 */
						var unblock = function( $node ) {
							$node.removeClass( 'processing' ).unblock();
						};





jQuery(document).ready(function($){


	var lastChosenOffers = $('#special_offers_selected_products').html();


	$('#special_offers_selected_categories').change(function(){
		categories_ids = $(this).val();
		selectElement = $(this);

		block($('#special_offers_selected_products'))
		$.ajax({
			url:woospecialoffers_ajax_data.ajaxUrl,
			dataType:'json',
			method:'post',
			data:{
				type:'selected_categories',
				categories_ids:categories_ids,
				action:'get_products_of_selected_categories',
				nonce:woospecialoffers_ajax_data.nonce
			},

			success:function(res){
				selectElement.parent().siblings('.special_offers_selected_products_field').find('#special_offers_selected_products').html(res);

			},
			complete:function(){
				unblock($('#special_offers_selected_products'))

			}


		})

	});


	$('body').on('click','.remove-special-offer-product-btn',function(){
		$(this).parent().remove();
	});




	$('#special_offers_product_search_input').on('keyup',function(){
		selectElement = $(this);

		var product_name = $(this).val();

		block($('#special_offers_selected_products'))

		var product_search_ajax = null;

		product_search_ajax = $.ajax({
			url:woospecialoffers_ajax_data.ajaxUrl,
			method:'post',
			data:{
				type:'search_product_name',
				product_name:product_name,
				action:'special_offers_product_search',
				nonce:woospecialoffers_ajax_data.nonce
			},
			beforeSend:function(){
				if(product_search_ajax != null)
					product_search_ajax.abort();
			},
			success:function(res){
				selectElement.parent().siblings('.special_offers_selected_products_field').find('#special_offers_selected_products').empty().prepend(res);

			},
			complete:function(){
				unblock($('#special_offers_selected_products'))
			}


		});

	});


	$('.special_offer_products_search_clear').on('click',function(e){
		e.preventDefault();
		$('#special_offers_selected_products').empty();

	});


	$('.special_offer_restore_offers').on('click',function(e){

		e.preventDefault();
		$('#special_offers_selected_products').html(lastChosenOffers);
	})


});