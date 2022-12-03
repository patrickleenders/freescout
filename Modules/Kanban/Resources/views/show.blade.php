@extends('layouts.app')

@section('title', __('Kanban Boards'))
@section('content_class', 'content-full')
@section('body_class', 'footer-hide')

@section('body_attrs')@parent data-mailbox_id="{{ $mailbox_id }}" data-board_id="{{ $board_id }}"@endsection

@section('content')
<div class="container">
    <div class="main-heading" id="kn-heading">
        @if ($mailbox_id)
            {{ __('Kanban View') }}
        @elseif ($board_id)
            {{ __('Kanban Board') }}
        @else
            {{ __('Kanban') }}
        @endif

        <a href="" id="kn-btn-refresh" class="btn btn-transparent kn-main-btn" data-toggle="tooltip" title="{{ __('Refresh') }}"><i class="glyphicon glyphicon-refresh"></i></a>

        <div class="in-heading">
            <div class="in-heading-item">
                <select class="form-control" id='kn-boards-list' autocomplete="off">
                    @if ($empty_data)
                        <option value=""></option>
                    @endif
                    @if ($boards)
                        <option value="#" disabled class="kn-disabled-option">-- {{ __('Boards') }} --</option>
                    @endif
                    @foreach ($boards as $board_item)
                        <option value="{{ \Kanban::url(['board_id' => $board_item->id]) }}" @if ($board_item->id == $board_id) selected @endif>{{ $board_item->name }} @if ($board_item->mailbox)({{ $board_item->mailbox->name }})@endif</option>
                    @endforeach
                    @if (count($boards) && count($mailboxes))
                        <option value="#" disabled class="kn-disabled-option">-- {{ __('Mailboxes') }} --</option>
                    @endif
                    <option value="{{ route('kanban.show', ['kn' => ['mailbox_id' => \Kanban::ALL_MAILBOXES]]) }}" @if (\Kanban::ALL_MAILBOXES == $mailbox_id) selected @endif>[{{ __('All Mailboxes') }}]</option>
                    @foreach ($mailboxes as $mailbox_item)
                        <option value="{{ route('kanban.show', ['kn' => ['mailbox_id' => $mailbox_item->id]]) }}" @if ($mailbox_item->id == $mailbox_id) selected @endif>{{ $mailbox_item->name }}</option>
                    @endforeach
                </select>

                <span class="dropdown">
                    <a href="" class="btn btn-primary btn-input-size" data-toggle="dropdown"><span class="caret"></span></a>
                    <ul class="dropdown-menu with-icons pull-right">
                        @if ($board && $board->userCanUpdate($user))
                            <li><a href="{{ route('kanban.ajax_html', ['action' => 'update_board', 'board_id' => $board_id]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ $board->name }}" data-modal-on-show="knInitBoardModal"><i class="glyphicon glyphicon-cog"></i> {{ __('Board Settings') }}</a></li>
                        @endif
                        @if ($board && $board->mailbox)
                            <li><a href="{{ $board->mailbox->url() }}" target="_blank"><i class="glyphicon glyphicon-arrow-right"></i> {{ __('Open Mailbox') }}</a></li>
                        @endif
                        @if ($board && $board->userCanDelete($user))
                            <li><a href="" id="kn-delete-board"><i class="glyphicon glyphicon-trash"></i> {{ __('Delete Board') }}</a></li>
                        @endif
                        <li>
                            <a href="{{ route('kanban.ajax_html', ['action' => 'new_board']) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('New Board') }}" data-modal-on-show="knInitBoardModal"><i class="glyphicon glyphicon-th"></i> {{ __('New Board') }}</a>
                        </li>
                        <li><a href="" id="kn-copy-link"><i class="glyphicon glyphicon-link"></i> {{ __('Copy Link') }}</a></li>
                    </ul>
                </span>

                <span class="dropdown kn-param" data-toggle="tooltip" title="{{ __('Sorting') }}" data-param="sort">
                    <a href="" class="btn btn-primary btn-input-size" data-toggle="dropdown"><i class="glyphicon glyphicon-sort-by-attributes"></i> <span class="caret"></span></a>
                    <ul class="dropdown-menu pull-right">
                        @if (!$mailbox_id)
                            <li @if ($params['sort'] == \Kanban::SORT_MANUAL) class="active" @endif data-param-value="{{ \Kanban::SORT_MANUAL }}"><a href="">{{ __('Manual Sorting') }}</a></li>
                        @endif
                        <li @if ($params['sort'] == \Kanban::SORT_ACTIVE) class="active" @endif data-param-value="{{ \Kanban::SORT_ACTIVE }}"><a href="">{{ __('Last Reply') }} → {{ __('Active First') }}</a></li>
                        <li @if ($params['sort'] == \Kanban::SORT_LAST_REPLY_NEW) class="active" @endif data-param-value="{{ \Kanban::SORT_LAST_REPLY_NEW }}"><a href="">{{ __('Last Reply') }} → {{ __('New First') }}</a></li>
                        <li @if ($params['sort'] == \Kanban::SORT_LAST_REPLY_OLD) class="active" @endif data-param-value="{{ \Kanban::SORT_LAST_REPLY_OLD }}"><a href="">{{ __('Last Reply') }} → {{ __('Old First') }}</a></li>
                        <li @if ($params['sort'] == \Kanban::SORT_CREATED_NEW) class="active" @endif data-param-value="{{ \Kanban::SORT_CREATED_NEW }}"><a href="">{{ __('Created On') }} → {{ __('New First') }}</a></li>
                        <li @if ($params['sort'] == \Kanban::SORT_CREATED_OLD) class="active" @endif data-param-value="{{ \Kanban::SORT_CREATED_OLD }}"><a href="">{{ __('Created On') }} → {{ __('Old First') }}</a></li>
                    </ul>
                </span>

                <span class="dropdown kn-param" data-toggle="tooltip" title="{{ __('Filters') }}" data-param="filters">
                    <a href="" class="btn btn-primary btn-input-size" data-toggle="dropdown"><i class="glyphicon glyphicon-filter"></i><span class="kn-param-counter"></span> <span class="caret"></span></a>
                    <ul class="dropdown-menu pull-right">
                        <li @if (!empty($params['filters']['status'])) class="active" @endif data-param-name="{{ \Kanban::FILTER_BY_STATUS }}" data-param-value="{{ implode(\Kanban::FILTERS_SEPARATOR, $params['filters']['status']) }}"><a href="{{ route('kanban.ajax_html', ['action' => 'filter', 'mailbox_id'=>$mailbox_id, 'board_id'=>$board_id, 'filter'=>\Kanban::FILTER_BY_STATUS, 'selected' => $params['filters']['status']]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('Filter') }}: {{ __('Status') }}" data-modal-size="sm" data-modal-on-show="knInitFilterModal">{{ __('Status') }} <span class="kn-filter-counter">@if (count($params['filters']['status']))({{ count($params['filters']['status']) }})@endif</span></a></li>
                        
                        <li @if (!empty($params['filters']['user_id'])) class="active" @endif data-param-name="{{ \Kanban::FILTER_BY_ASSIGNEE }}" data-param-value="{{ implode(\Kanban::FILTERS_SEPARATOR, $params['filters']['user_id']) }}"><a href="{{ route('kanban.ajax_html', ['action' => 'filter', 'mailbox_id'=>$mailbox_id, 'board_id'=>$board_id, 'filter'=>\Kanban::FILTER_BY_ASSIGNEE, 'selected' => $params['filters']['user_id']]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('Filter') }}: {{ __('Assignee') }}" data-modal-size="sm" data-modal-on-show="knInitFilterModal">{{ __('Assignee') }} <span class="kn-filter-counter">@if (count($params['filters']['user_id']))({{ count($params['filters']['user_id']) }})@endif</span></a></li>
                        
                        <li @if (!empty($params['filters']['state'])) class="active" @endif data-param-name="{{ \Kanban::FILTER_BY_STATE }}" data-param-value="{{ implode(\Kanban::FILTERS_SEPARATOR, $params['filters']['state']) }}"><a href="{{ route('kanban.ajax_html', ['action' => 'filter', 'mailbox_id'=>$mailbox_id, 'board_id'=>$board_id, 'filter'=>\Kanban::FILTER_BY_STATE, 'selected' => $params['filters']['state']]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('Filter') }}: {{ __('State') }}" data-modal-size="sm" data-modal-on-show="knInitFilterModal">{{ __('State') }} <span class="kn-filter-counter">@if (count($params['filters']['state']))({{ count($params['filters']['state']) }})@endif</span></a></li>
                        
                        <li @if (!empty($params['filters']['tag'])) class="active" @endif data-param-name="{{ \Kanban::FILTER_BY_TAG }}" data-param-value="{{ implode(\Kanban::FILTERS_SEPARATOR, $params['filters']['tag']) }}"><a href="{{ route('kanban.ajax_html', ['action' => 'filter', 'mailbox_id'=>$mailbox_id, 'board_id'=>$board_id, 'filter'=>\Kanban::FILTER_BY_TAG]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('Filter') }}: {{ __('Tag') }}" data-modal-size="sm" data-modal-on-show="knInitFilterModal">{{ __('Tag') }} <span class="kn-filter-counter">@if (count($params['filters']['tag']))({{ count($params['filters']['tag']) }})@endif</span></a></li>
                        
                        @if (!$mailbox_id)
                            <li @if (!empty($params['filters'][\Kanban::FILTER_BY_COLUMN])) class="active" @endif data-param-name="{{ \Kanban::FILTER_BY_COLUMN }}" data-param-value="{{ implode(\Kanban::FILTERS_SEPARATOR, $params['filters'][\Kanban::FILTER_BY_COLUMN]) }}"><a href="{{ route('kanban.ajax_html', ['action' => 'filter', 'selected' => $params['filters'][\Kanban::FILTER_BY_COLUMN], 'mailbox_id'=>$mailbox_id, 'board_id'=>$board_id, 'filter'=>\Kanban::FILTER_BY_COLUMN, 'selected' => $params['filters']['tag']]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('Filter') }}: {{ __('Column') }}" data-modal-size="sm" data-modal-on-show="knInitFilterModal">{{ __('Column') }} <span class="kn-filter-counter">@if (count($params['filters'][\Kanban::FILTER_BY_COLUMN]))({{ count($params['filters'][\Kanban::FILTER_BY_COLUMN]) }})@endif</span></a></li>
                        @endif
                        
                        @if (\Module::isActive('customfields') && $mailbox_id != \Kanban::ALL_MAILBOXES)
                            <li @if (!empty($params['filters']['custom_field'])) class="active" @endif data-param-name="{{ \Kanban::FILTER_BY_CF }}" data-param-value="{{ implode(\Kanban::FILTERS_SEPARATOR, $params['filters']['custom_field']) }}"><a href="{{ route('kanban.ajax_html', ['action' => 'filter', 'mailbox_id'=>$mailbox_id, 'board_id'=>$board_id, 'filter'=>\Kanban::FILTER_BY_CF, 'selected' => $params['filters']['custom_field']]) }}" data-trigger="modal" data-modal-no-footer="true" data-modal-title="{{ __('Filter') }}: {{ __('Custom Fields') }}" data-modal-on-show="knInitFilterModal">{{ __('Custom Fields') }} <span class="kn-filter-counter">@if (count($params['filters']['custom_field']))({{ count($params['filters']['custom_field']) }})@endif</span></a></li>
                        @endif

                        <li class="divider kn-reset-filter @if (\Kanban::$default_filters == $params['filters']) hidden @endif"></li>
                        <li class="kn-reset-filter @if (\Kanban::$default_filters == $params['filters']) hidden @endif"><a href="{{ \Kanban::url(['mailbox_id' => $mailbox_id, 'board_id' => $board_id]) }}" id="kn-reset-filter">{{ __('Reset Filters') }}</a></li>
                    </ul>
                </span>

                <span class="dropdown kn-param" data-toggle="tooltip" title="{{ __('Group By') }}" data-param="group_by">
                    <a href="" class="btn btn-primary btn-input-size" data-toggle="dropdown"><i class="glyphicon glyphicon-list-alt"></i> <span class="caret"></span></a>
                    <ul class="dropdown-menu pull-right">
                        @if ($board_id)
                            <li @if ($params['group_by'] ==  \Kanban::GROUP_BY_COLUMN) class="active" @endif data-param-value="{{ \Kanban::GROUP_BY_COLUMN }}"><a href="">{{ __('Columns') }}</a></li>
                        @endif
                        <li @if ($params['group_by'] == \Kanban::GROUP_BY_STATUS) class="active" @endif data-param-value="{{ \Kanban::GROUP_BY_STATUS }}"><a href="">{{ __('Status') }}</a></li>
                        <li @if ($params['group_by'] == \Kanban::GROUP_BY_ASSIGNEE) class="active" @endif data-param-value="{{ \Kanban::GROUP_BY_ASSIGNEE }}"><a href="">{{ __('Assignee') }}</a></li>
                        <li @if ($params['group_by'] == \Kanban::GROUP_BY_TAG) class="active" @endif data-param-value="{{ \Kanban::GROUP_BY_TAG }}"><a href="">{{ __('Tag') }}</a></li>
                    </ul>
                </span>
            </div>
        </div>
    </div>
    
    @if (!empty($empty_data))
        @include('partials/empty', $empty_data ?: ['icon' => 'th', 'empty_text' => __('There are no cards here yet')])
    @else
        @if (empty($data))
            @include('partials/empty', $empty_data ?: ['icon' => 'th', 'empty_text' => __('There are no cards here yet')])
        @else
            <div id="kn-board">
                @include('kanban::partials/board')
            </div>
        @endif
    @endif

</div>
@endsection

@include('partials/editor')
@include('partials/include_datepicker')

@section('javascript')
    @parent
    knInit("{{ __("Are you sure you want to delete the board?") }}");
    var kn_text_delete_card = '{{ __("Are you sure you want to delete the card? The linked conversation will be preserved.") }}';
@endsection