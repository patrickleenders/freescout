<div class="row-container">
	<form class="form-horizontal kn-board-form" method="POST" action="" data-board-id="{{ $board->id }}">

		@include('kanban::partials/board_form')

		<div class="form-group margin-top margin-bottom-10">
	        <div class="col-sm-10 col-sm-offset-2">
	            <button class="btn btn-primary" data-loading-text="{{ __('Save') }}…">{{ __('Save') }}</button>
	        </div>
	    </div>
	</form>
</div>