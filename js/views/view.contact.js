/*
Name: 			View - Contact
Written by: 	Okler Themes - (http://www.okler.net)
Version: 		3.4.1
*/

(function($) {

	'use strict';

	/*
	Contact Form: Basic
	*/

	$('#contactForm:not([data-type=advanced])').validate({

		submitHandler: function(form) {

			var $form = $(form),

				$messageSuccess = $('#contactSuccess'),
				$messageError = $('#contactError'),
				$submitButton = $(this.submitButton);

			$submitButton.button('loading');



			// Ajax Submit
			$.ajax({

				type: 'POST',
				url: $form.attr('action'),
				data: {

					summonerName: $form.find('#summonerName').val(),
					region: $form.find('#region').val(),
					tsNick: $form.find('#tsNick').val(),
					firstName: $form.find('#firstName').val(),
					surname: $form.find('#surname').val(),
					email: $form.find('#email').val(),
					dgl: $form.find('#dgl').val(),
					terms: $form.find('#terms').val()

				},

				dataType: 'json',

				complete: function(data) {

					if (typeof data.responseJSON === 'object') {

						if (data.responseJSON.response == 'success') {

							$messageSuccess.removeClass('hidden');
							$messageError.addClass('hidden');

							// Reset Form
							$form.find('.form-control')

								.val('')
								.blur()
								.parent()
								.removeClass('has-success')
								.removeClass('has-error')
								.find('label.error')
								.remove();

							if (($messageSuccess.offset().top - 80) < $(window).scrollTop()) {

								$('html, body').animate({
									scrollTop: $messageSuccess.offset().top - 80
								}, 300);
							}

							$submitButton.button('reset');
							return;

						} 
						
					}

					if (typeof data.responseJSON === 'object') {

						var msg = (data.responseJSON.reason);		
						document.getElementById('contactError').innerHTML = " Whoa there, looks like an error occurred: <b>" + msg + "</b>";
					}

					$messageError.removeClass('hidden');
					$messageSuccess.addClass('hidden');
					
					

					if (($messageError.offset().top - 80) < $(window).scrollTop()) {

						$('html, body').animate({
							scrollTop: $messageError.offset().top - 80
						}, 300);
					}

					$form.find('.has-success')
						.removeClass('has-success');

					$submitButton.button('reset');
				}
			});
		}
	});



	/*
	Contact Form: Advanced
	*/

	$('#contactFormAdvanced, #contactForm[data-type=advanced]').validate({

		onkeyup: false,
		onclick: false,
		onfocusout: false,

		rules: {

			'captcha': {

				captcha: true

			},

			'checkboxes[]': {

				required: true

			},

			'radios': {

				required: true
			}
		}
	});

}).apply(this, [jQuery]);