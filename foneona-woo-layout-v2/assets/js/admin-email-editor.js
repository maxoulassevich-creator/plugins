(function($){
	'use strict';

	function replacePlaceholders(value, replacements) {
		var output = value || '';
		Object.keys(replacements || {}).forEach(function(key){
			var safe = String(replacements[key] == null ? '' : replacements[key]);
			output = output.split(key).join(safe);
		});
		return output;
	}

	function buildDocument(html, css, replacements) {
		var body = replacePlaceholders(html, replacements);
		return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style type="text/css">' + (css || '') + '</style></head><body><div class="foneona-email-wrap">' + body + '</div></body></html>';
	}

	function getEditorContent(editorId) {
		if (window.tinymce) {
			var editor = window.tinymce.get(editorId);
			if (editor && !editor.isHidden()) {
				return editor.getContent();
			}
		}
		return $('#' + editorId).val() || '';
	}

	function updatePreview() {
		if (!window.foneonaEmailEditor) {
			return;
		}

		var data = window.foneonaEmailEditor;
		var frame = document.getElementById(data.previewId);
		if (!frame) {
			return;
		}

		var html = getEditorContent(data.editorId);
		var css = $('#' + data.cssFieldId).val() || '';
		var doc = buildDocument(html, css, data.replacements || {});

		try {
			frame.srcdoc = doc;
		} catch (e) {
			var iframeDocument = frame.contentDocument || frame.contentWindow.document;
			iframeDocument.open();
			iframeDocument.write(doc);
			iframeDocument.close();
		}
	}

	$(function(){
		if (!window.foneonaEmailEditor) {
			return;
		}

		var data = window.foneonaEmailEditor;
		var timer;
		var scheduleUpdate = function(){
			window.clearTimeout(timer);
			timer = window.setTimeout(updatePreview, 180);
		};

		$(document).on('input change keyup', '#' + data.editorId + ', #' + data.cssFieldId, scheduleUpdate);

		if (window.tinymce) {
			var attachTinyMce = function(){
				var editor = window.tinymce.get(data.editorId);
				if (!editor) {
					window.setTimeout(attachTinyMce, 300);
					return;
				}
				editor.on('change keyup input paste undo redo SetContent', scheduleUpdate);
			};
			attachTinyMce();
		}

		$(document).on('click', '.wp-switch-editor', function(){
			window.setTimeout(updatePreview, 250);
		});

		updatePreview();
	});
})(jQuery);
