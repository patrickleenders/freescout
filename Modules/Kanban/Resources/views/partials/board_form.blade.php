<div class="form-group">
    <label class="col-sm-2 control-label">{{ __('Name') }}</label>

    <div class="col-sm-10">
        <input class="form-control kn-board-name" name="name" value="{{ $board->name }}" maxlength="75" required/>
    </div>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">{{ __('Mailbox') }}</label>

    <div class="col-sm-10">
    	<select name="mailbox_id" class="form-control" required>
    		@foreach (auth()->user()->mailboxesCanView() as $mailbox)
    			<option value="{{ $mailbox->id }}" @if ($board->mailbox_id == $mailbox->id) selected @endif>{{ $mailbox->name }}</option>
    		@endforeach
    	</select>

        <p class="form-help">
            {{ __('Selected mailbox determines users having access to the board.') }} @if (\Module::isActive('customfields')){{ __('Custom fields from the selected mailbox will be available as filters.') }}@endif
        </p>
    </div>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">{{ __('Columns') }}</label>

    <div class="col-sm-10">
        <div class="kn-board-columns panel-group accordion margin-bottom-5" data-max-id="@if ($board->columns){{ $board->getMaxColumnId() }}@else{{ $board->getMaxColumnId()+1 }}@endif">
            <div class="kn-board-column-pattern hidden">
                @include('kanban::partials/board_column_form', ['column_id' => \Kanban::PATTERN_ID])
            </div>

            @if ($board->columns)
                @foreach($board->columns as $column)
                    @if (isset($column['id']))
                        @include('kanban::partials/board_column_form', ['column_id' => $column['id'], 'column' => $column])
                    @endif
                @endforeach
            @else
                @include('kanban::partials/board_column_form', ['column_id' => 1])
            @endif
        </div>
        <a href="" class="kn-add-column">+ {{ __('Add') }}</a>
    </div>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">{{ __('Swimlanes') }}</label>

    <div class="col-sm-10">
        <div class="kn-board-swimlanes panel-group accordion margin-bottom-5" data-max-id="@if ($board->swimlanes){{ $board->getMaxSwimlaneId() }}@else{{ $board->getMaxSwimlaneId()+1 }}@endif">
            <div class="kn-board-swimlane-pattern hidden">
                @include('kanban::partials/board_swimlane_form', ['swimlane_id' => \Kanban::PATTERN_ID])
            </div>

            @if ($board->swimlanes)
                @foreach($board->swimlanes as $swimlane)
                    @if (isset($swimlane['id']))
                        @include('kanban::partials/board_swimlane_form', ['swimlane_id' => $swimlane['id'], 'swimlane' => $swimlane])
                    @endif
                @endforeach
            @else
                @include('kanban::partials/board_swimlane_form', ['swimlane_id' => 1, 'swimlane' => ['name' => __('Default')]])
            @endif
        </div>
        <a href="" class="kn-add-swimlane">+ {{ __('Add') }}</a>
    </div>
</div>