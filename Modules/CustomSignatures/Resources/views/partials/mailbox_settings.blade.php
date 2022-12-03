@if (Auth::user()->can('updateSettings', $mailbox) || Auth::user()->can('updateEmailSignature', $mailbox))

	@foreach($signatures as $signature)
		@include('customsignatures::partials/signature_form')
	@endforeach

	<div id="cs-signature-pattern" class="hidden">
		@include('customsignatures::partials/signature_form', ['signature' => null])
	</div>
	<div class="form-group">
	    <div class="col-md-9 col-sm-offset-2">
	        <button type="button" class="btn btn-default pull-right" id="cs-add-signature">
	            <small class="glyphicon glyphicon-plus"></small> {{ __('Add Signature') }}
	        </button>
	    </div>
	</div>
@endif
