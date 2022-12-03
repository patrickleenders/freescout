<div class="row-container">
	<form class="form-horizontal kn-card-form" method="POST" action="" data-card-id="{{ $card->id }}">

		@include('kanban::partials/card_form')

		<div class="form-group margin-top margin-bottom-10">
	        <div class="col-sm-10 col-sm-offset-2">
	            <button class="btn btn-primary kn-card-submit" data-loading-text="{{ __('Save') }}…">{{ __('Save') }}</button>
	            @if (!empty($conversation_number))
	            	<button class="btn btn-default kn-card-submit-redirect" data-loading-text="{{ __('Save And Open Board') }}…">{{ __('Save And Open Board') }}</button>
	            @endif
	            @if ($mode == 'update' && $card->linked)
	            	<a href="#" id="kn-delete-card" class="btn btn-link text-danger" data-loading-text="{{ __('Delete card') }}…" data-toggle="tooltip" title="{{ __('Delete card and keep linked conversation') }}">{{ __('Delete card') }}</a>
	            @endif
	        </div>
	    </div>
	</form>

	@if ($mode == 'update')
		<hr/>
		<iframe src="{{ $card->conversation_cached()->url() }}&amp;x_embed=1" frameborder="0" class="modal-iframe"></iframe>
	@elseif ($board)
		<div class="kn-card-linked hidden">
			<hr/>
			<iframe src="{{ route('conversations.search', ['f' => ['mailbox' => $board->mailbox_id, 'custom' => \Kanban::SEARCH_CUSTOM]]) }}&amp;x_embed=1" frameborder="0" class="modal-iframe"></iframe>
		</div>
	@endif
</div>