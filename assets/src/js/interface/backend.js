jQuery(document).ready( function($){
	
	// Deal with dismissable notices
	$( '.paghiper-dismiss-notice' ).on( 'click', '.notice-dismiss', function() {
		let noticeId = $(this).parent().data('notice-id');
		var data = {
			action: 'paghiper_dismiss_notice',
			notice: noticeId
		};
		
		$.post( notice_params.ajaxurl, data, function() {
		});
	});

	$( '.paghiper-notice' ).on( 'click', '.ajax-action', function() {

		let noticeId 		= $(this).data('notice-key'),
			noticeAction 	= $(this).data('action');

		var data = {
			'action'	: 'paghiper_answer_notice',
			'noticeId'	: noticeId,
			'userAction': noticeAction
		};
		
		$.post( notice_params.ajaxurl, data, function() {
		});

		$(".paghiper-review-nag").hide();

	});

	// Provides maskable fields for date operations
	if(typeof $.fn.mask === 'function') {
		$(".date").mask("00/00/0000", {placeholder: "__/__/____", clearIfNotMatch: true});
	}

});

window.copyPaghiperEmv = function() {
	// Start with objects to be selected
	let paghiperEmvBlock 	= document.querySelector('.paghiper-pix-code');
	let targetPixCode 		= paghiperEmvBlock.querySelector('textarea');
	let targetButton 		= paghiperEmvBlock.querySelector('button');

	// Select the text field
	targetPixCode.select();
	targetPixCode.setSelectionRange(0, 99999); /* For mobile devices */
  
	// Copy the text inside the text field
	navigator.clipboard.writeText(targetPixCode.value);

	// Store selection range insie button dataset
	targetButton.dataset.originalText = targetButton.innerHTML;
	targetButton.innerHTML = 'Copiado!';

	setTimeout(function(targetButton) {
		// Restore original text from dataset store value
		let originalText = targetButton.dataset.originalText;
		targetButton.innerHTML = originalText;

		// Remove selection range
		document.getSelection().removeAllRanges();
	}, 2000, targetButton,);
};