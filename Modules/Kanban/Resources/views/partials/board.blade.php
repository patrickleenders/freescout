@foreach($data as $swimlane_id => $swimlane_cols)
    @foreach($swimlanes as $swimlane)
        @if ($swimlane['id'] == $swimlane_id && count($data) > 1)
            <div class="kn-swimlane">{{ $swimlane['name'] }}</div>
        @endif
    @endforeach

    <div class="kn-row">
        @foreach($columns as $column)
            <div class="kn-col">
                @php
                    $total_count = $swimlane_cols[$column['id']]['total_count'] ?? 0;
                @endphp
                <div class="kn-col-name">{{ $column['name'] }} @if ($total_count)<span class="kn-col-counter">(@if (!empty($column['limit']) && $total_count > $column['limit'])<strong class="text-danger">@endif{{ $total_count }}@if (!empty($column['limit']) && $total_count > $column['limit'])</strong>@endif{{ '' }}@if (!empty($column['limit']))/{{ $column['limit'] }}@endif)</span>@endif</div>
                @if ($mailbox_id)
                    @if ($mailbox_id != \Kanban::ALL_MAILBOXES)
                        <a href="{{ route('conversations.create', ['mailbox_id' => $mailbox_id]) }}" target="_blank" class="btn btn-bordered btn-xs kn-add-card">+ {{ __('Add Conversation') }}</a>
                    @endif
                @else
                    <a href="{{ route('kanban.ajax_html', ['action' => 'new_card', 'kn_column_id' => $column['id'], 'kn_swimlane_id' => $swimlane_id, 'kn_board_id' => $board_id, 'group_by' => $params['group_by'], 'group_by_id' => $column['id'], 't' => time()]) }}" target="_blank" class="btn btn-bordered btn-xs kn-add-card" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('New Card') }}" data-modal-size="lg" data-modal-on-show="knInitCardModal">+ {{ __('Add Card') }}</a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="kn-row">
        @foreach($swimlane_cols as $column_id => $swimlane_col)
            <div class="kn-col kn-sortable" data-column-id="{{ $column_id }}" data-swimlane-id="{{ $swimlane_id }}">
                @include('kanban::partials/cards', ['cards' => $swimlane_col['cards'] ?? []])

                {{-- Show closed --}}
                @if (!empty($swimlane_col['closed']))
                    <div class="kn-show-closed" data-loading-text="{{ __('Show Closed') }}  ({{ $swimlane_col['closed'] }})…">
                        {{ __('Show Closed') }} ({{ $swimlane_col['closed'] }}) <small class="glyphicon glyphicon-chevron-down"></small>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach