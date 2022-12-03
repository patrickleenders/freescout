@if ($signature_id)
	<div class="cs_signature_default hidden">
		@if ($mailbox->signature)
            {!! $conversation->replaceTextVars($mailbox->signature, [], true) !!}
        @endif
	</div>
@endif
<span class="cs-toolbar-select">
	<span class="editor-btm-text">{{ __('Signature') }}:</span> 
	<select name="cs_signature" class="form-control parsley-exclude cs-signature-select">
	    <option value="">{{ __('Default') }}</option>
	    @foreach($signatures as $signature)
	    	<option value="{{ $signature->id }}" @if ($signature->id == $signature_id) selected @endif>{{ $signature->name }}</option>
	    @endforeach
	</select> 
	<small class="note-bottom-div"></small>
</span>