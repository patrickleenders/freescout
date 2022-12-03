@if ($card->kn_board_id)
    <input type="hidden" name="kn_board_id" value="{{ $card->kn_board_id }}" />
@endif
@if ($card->kn_swimlane_id)
    <input type="hidden" name="kn_swimlane_id" value="{{ $card->kn_swimlane_id }}" />
@endif

<div class="form-group">
    <label class="col-sm-2 control-label">{{ __('Name') }}</label>

    <div class="col-sm-10">
        <input class="form-control kn-card-form-name" name="name" value="{{ $card->name }}" required />
    </div>
</div>

@if ($mode == 'create')
    <input type="hidden" name="group_by" value="{{ $group_by }}" />
    <input type="hidden" name="group_by_id" value="{{ $group_by_id }}" />

    @if ($group_by != \Kanban::GROUP_BY_COLUMN)
        @if (!$board)
            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Kanban Board') }}</label>

                <div class="col-sm-10">
                    <select class="form-control" name="kn_board_id" required id="kn-board-input">
                        @foreach($boards as $board_item)
                            <option value="{{ $board_item->id }}">{{ $board_item->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Column') }}</label>

                <div class="col-sm-10">
                    @foreach($boards as $i => $board_item)
                        <select class="form-control kn-board-inputs @if ($i != 0) hidden @endif" name="kn_column_id" @if ($i != 0) disabled @endif required id="kn-board-columns-select-{{ $board_item->id }}">
                            @if (is_array($board_item->columns))
                                @foreach($board_item->columns as $column)
                                    <option value="{{ $column['id'] }}">{{ $column['name'] }}</option>
                                @endforeach
                            @endif
                        </select>
                    @endforeach
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Swimlane') }}</label>

                <div class="col-sm-10">
                    @foreach($boards as $i => $board_item)
                        <select class="form-control kn-board-inputs @if ($i != 0) hidden @endif" name="kn_swimlane_id" @if ($i != 0) disabled @endif required id="kn-board-swimlane-select-{{ $board_item->id }}">
                            @if (is_array($board_item->swimlanes))
                                @foreach($board_item->swimlanes as $swimlane)
                                    <option value="{{ $swimlane['id'] }}">{{ $swimlane['name'] }}</option>
                                @endforeach
                            @endif
                        </select>
                    @endforeach
                </div>
            </div>
        @else
            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Column') }}</label>

                <div class="col-sm-10">
                    <select class="form-control" name="kn_column_id" required>
                        @foreach($board->columns as $column)
                            <option value="{{ $column['id'] }}">{{ $column['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
    @else
        <input type="hidden" name="kn_column_id" value="{{ $card->kn_column_id }}" />
    @endif
@else
    <input type="hidden" name="kn_column_id" value="{{ $card->kn_column_id }}" />
@endif

@if ($mode == 'create')

    <div class="form-group @if ($conversation_number) hidden @endif">
        <label class="col-sm-2 control-label">{{ __('Link to Conversation') }}</label>

        <div class="col-sm-10">
            <div class="onoffswitch-wrap">
                <div class="onoffswitch">
                    <input type="checkbox" name="link_to_conversation" value="1" id="link_to_conversation" class="onoffswitch-checkbox">
                    <label class="onoffswitch-label" for="link_to_conversation"></label>
                </div>
            </div>
        </div>
    </div>

    <div class="form-group kn-card-standalone @if ($conversation_number) hidden @endif">
        <label class="col-sm-2 control-label">{{ __('Description') }}</label>

        <div class="col-sm-10">
            <textarea class="form-control kn-card-body" id="kn-card-body" name="body" rows="5">{{ '' }}</textarea>
        </div>
    </div>

    <div class="form-group kn-card-standalone @if ($conversation_number) hidden @endif">
        <label class="col-sm-2 control-label">{{ __('Notify Users') }}</label>

        <div class="col-sm-10">
            <div class="onoffswitch-wrap">
                <div class="onoffswitch">
                    <input type="checkbox" name="kn_notify_users" value="1" id="kn_notify_users" class="onoffswitch-checkbox">
                    <label class="onoffswitch-label" for="kn_notify_users"></label>
                </div>
            </div>
            <p class="form-help">
                @if ($card->kn_board_id)
                    {{ __('Notify ":mailbox_name" mailbox users about new card.', ['mailbox_name' => $card->kn_board->mailbox->name]) }}
                @else
                    {{ __('Notify mailbox users about new card.') }}
                @endif
            </p>
        </div>
    </div>

    <div class="form-group kn-card-linked hidden">
        <label class="col-sm-2 control-label">{{ __('Conversation') }}</label>

        <div class="col-sm-10">
            <div class="input-group">
                <span class="input-group-addon">#</span>
                <input type="number" class="form-control" name="conversation_number" value="{{ $conversation_number }}" id="kn-card-conv" placeholder="{{ __('Conversation Number') }}">
            </div>
        </div>
    </div>
@endif
