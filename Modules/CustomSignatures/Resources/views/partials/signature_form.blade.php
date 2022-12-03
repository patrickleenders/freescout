@php
	$signature_suffix = '';
	if (empty($signature)) {
		$signature = new \CustomSignature();
		$signature_suffix = '_new';
	}
@endphp
<div class="form-group cs-signature-block">
    <div class="col-md-9 col-sm-offset-2">
        <div class="input-group" style="position:relative; top:1px;">
            <input type="text" class="form-control cs-signature-name" name="cs_signature_name{{ $signature_suffix }}[{{ $signature->id }}]" value="{{ $signature->name }}" placeholder="{{ __('Signature Name') }}" maxlength="75">
            <span class="input-group-btn">
                <button class="btn btn-default cs-delete" type="button"><i class="glyphicon glyphicon-trash"></i></button>
            </span>
        </div>
        <textarea class="form-control cs-signature-text" id="cs_signature_text_{{ $signature->id }}" name="cs_signature_text{{ $signature_suffix }}[{{ $signature->id }}]" rows="8">{{ $signature->text ?: \App\Mailbox::DEFAULT_SIGNATURE }}</textarea>
    </div>
</div>