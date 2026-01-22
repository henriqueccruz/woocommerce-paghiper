jQuery(function ($) {
    'use strict';

	// Deal with dismissable notices
	$( '.paghiper-dismiss-notice' ).on( 'click', '.notice-dismiss', function() {
		console.log('Clicked!');
		let noticeId = $(this).parent().data('notice-id');
		var data = {
			action: 'paghiper_dismiss_notice',
			notice: noticeId
		};
		
		$.post( notice_params.ajaxurl, data, function() {
		});
	});

	$( '.paghiper-notice' ).on( 'click', '.ajax-action', function() {
		console.log('Clicked II!');

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
});