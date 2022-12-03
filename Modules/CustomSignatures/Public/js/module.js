/**
 * Module's JavaScript.
 */

function csInitMailboxSettings()
{
	$(document).ready(function() {
		$('.cs-signature-text:visible').each(function(i, el) {
			var selector = '#'+$(el).attr('id');
			csInitSignatureBlock(selector);
		});
	});

	$("#cs-add-signature").click(function(e){
		var pattern_html = $('#cs-signature-pattern').html();
		var new_block = $(pattern_html).insertBefore($('#cs-signature-pattern'));
		new_block.removeClass('hidden');
		new_block.children().find('.cs-signature-name:first').attr('required', 'required');

		var selector = 'cs_signature_text_'+generateDummyId();
		new_block.children().find('.cs-signature-text:first').attr('id', selector);

		csInitSignatureBlock('#'+selector);
	});
}

function csInitSignatureBlock(selector)
{
	summernoteInit(selector, {
		insertVar: true,
		disableDragAndDrop: false,
		callbacks: {
			onInit: function() {
				$(selector).parent().children().find('.note-statusbar').remove();
				$(selector).parent().children().find('.summernote-inservar:first').on('change', function(event) {
					$(selector).summernote('insertText', $(this).val());
					$(this).val('');
				});
			},
			onImageUpload: function(files) {
				if (!files) {
					return;
				}
				for (var i = 0; i < files.length; i++) {
					editorSendFile(files[i], undefined, false, selector);
				}
			}
		}
	});

	$(selector).parents('.cs-signature-block:first').children().find('.cs-delete:first').click(function(e){
		$(this).parents('.cs-signature-block:first').remove();
	});
}

function csInitSignatureSelect()
{
	$(document).ready(function() {
		$('.cs-signature-select').change(function(e) {
			var select = $(e.target);
			var signature_id = select.val();
			
			if (!signature_id) {
				$('#editor_signature:visible').html(
					$('#editor_signature:visible').parent().children('.cs_signature_default:first').html()
				);
				return;
			}
			select.attr('disabled', 'disabled');
			fsAjax(
				{
					action: 'load_signature',
					signature_id: signature_id,
					conversation_id: getGlobalAttr('conversation_id')
				}, 
				laroute.route('custom_signatures.ajax'), 
				function(response) {
					select.removeAttr('disabled');
					if (isAjaxSuccess(response)) {
						var signature_container = $('#editor_signature:visible');
						if (!$('.cs_signature_default:first').length) {
							$('<div class="cs_signature_default hidden">'+signature_container.html()+'</div>').insertAfter(signature_container);
						}
						signature_container.html(response.html);
					} else {
						showAjaxError(response);
					}
				}, true,
				function() {
					select.removeAttr('disabled');
				}
			);
		});
	});
}
