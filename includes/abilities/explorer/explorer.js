/**
 * CDW Abilities Explorer Scripts
 */

(function($) {
	'use strict';

	function cdwEscapeHtml(text) {
		if (typeof text !== 'string') {
			return text;
		}
		return text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function initValidateButton() {
		var $validateBtn = $('#cdw-validate-input');
		var $input = $('#cdw-ability-input');
		var $validationResult = $('#cdw-validation-result');

		if (!$validateBtn.length || !$input.length) {
			return;
		}

		$validateBtn.on('click', function() {
			var input = $input.val();
			try {
				if (input.trim()) {
					JSON.parse(input);
				}
				$validationResult
					.show()
					.removeClass('cdw-result-error')
					.addClass('cdw-result-success')
					.html('<strong>&#10003;</strong> ' + cdwExplorer.i18n.validJson);
			} catch (e) {
				$validationResult
					.show()
					.removeClass('cdw-result-success')
					.addClass('cdw-result-error')
					.html('<strong>&#10007;</strong> ' + cdwExplorer.i18n.invalidJson + ' ' + cdwEscapeHtml(e.message));
			}
		});
	}

	function initInvokeForm() {
		var $form = $('#cdw-ability-test-form');
		var $input = $('#cdw-ability-input');
		var $invokeBtn = $('#cdw-invoke-ability');
		var $invokeResult = $('#cdw-invoke-result');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function(e) {
			e.preventDefault();

			$invokeBtn.prop('disabled', true).text(cdwExplorer.i18n.invoking);
			$invokeResult.hide();

			var formData = {
				action: 'cdw_ability_explorer_invoke',
				nonce: $('#cdw_explorer_nonce').val(),
				ability_name: $('input[name="ability_name"]').val(),
				input: $input.val()
			};

			$.post(ajaxurl, formData, function(response) {
				if (response.success) {
					$invokeResult
						.show()
						.removeClass('cdw-result-error')
						.addClass('cdw-result-success')
						.html('<h3>' + cdwExplorer.i18n.result + '</h3><pre>' + cdwEscapeHtml(JSON.stringify(response.data, null, 2)) + '</pre>');
				} else {
					var errorMessage = (response.data && (response.data.message || response.data.error))
						? (response.data.message || response.data.error)
						: cdwExplorer.i18n.unknownError;
					$invokeResult
						.show()
						.removeClass('cdw-result-success')
						.addClass('cdw-result-error')
						.html('<h3>' + cdwExplorer.i18n.error + '</h3><p>' + cdwEscapeHtml(errorMessage) + '</p>');
				}
			}).always(function() {
				$invokeBtn.prop('disabled', false).text(cdwExplorer.i18n.invokeAbility);
			});
		});
	}

	function initCopySchema() {
		$('.cdw-copy-schema').on('click', function() {
			var schemaType = $(this).data('schema');
			var $schemaEl = $('#cdw-' + schemaType + '-schema');

			if ($schemaEl.length) {
				navigator.clipboard.writeText($schemaEl.text()).then(function() {
					alert(cdwExplorer.i18n.copied);
				});
			}
		});
	}

	$(document).ready(function() {
		initValidateButton();
		initInvokeForm();
		initCopySchema();
	});

})(jQuery);
