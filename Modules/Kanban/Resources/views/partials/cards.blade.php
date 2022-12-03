@foreach($cards as $card_i => $card)
    @if ($card_i >= \Kanban::CARDS_PER_COLUMN)
        <div class="kn-more btn btn-trans" data-loading-text="…"><i class="glyphicon glyphicon-menu-down"></i></div>
        @continue
    @endif
    <div class="kn-card hover-shade @if ($card->conversation_cached() && $card->conversation_cached()->isActive()) kn-card-active @endif" data-card-id="{{ $card->id }}" @if ($card->conversation_cached()) data-conversation-id="{{ $card->conversation_cached()->id }}" @endif class="btn btn-bordered btn-xs" @if ($card->name) data-remote="{{ route('kanban.ajax_html', ['action' => 'update_card', 'card_id' => $card->id]) }}" data-modal-on-show="knInitCardModal" data-modal-title="{{ $card->name }}"@else data-modal-body='<iframe src="{{ $card->conversation_cached()->url() }}&amp;x_embed=1" frameborder="0" class="modal-iframe"></iframe>' data-modal-title="{{ $card->conversation_cached()->getSubject() }} #{{ $card->conversation_cached()->number }}" @endif data-trigger="modal" data-modal-size="lg" data-modal-no-footer="true">
        @if (!$card->conversation_cached())
            <div class="kn-card-name">{{ $card->name }}</div>
        @elseif ($card->conversation_cached())
            @if ($card->linked)
                <div class="kn-card-top">
                    @if ($card->linked && $card->conversation_cached()->customer)
                        <span class="kn-card-customer">{{ $card->conversation_cached()->customer->getFullName(true) }}</span>
                    @endif
                    <small class="kn-card-date" data-toggle="tooltip" title="@if (!$card->linked){{ auth()->user()->dateFormat($card->conversation_cached()->created_at) }}@else{{ $card->conversation_cached()->getDateTitle() }}@endif" data-html="true">{{ $card->conversation_cached()->getWaitingSince(new App\Folder()) }}</small>
                </div>
            @endif
            <div class="kn-card-name">
                <span class="kn-badges">
                    @if ($card->conversation_cached()->has_attachments)<i class="glyphicon glyphicon-paperclip"></i>@endif
                    @include('conversations/partials/badges', ['conversation' => $card->conversation_cached(), 'folder' => new App\Folder()]) 
                </span>
                @if (!$card->name){{ $card->conversation_cached()->getSubject() }}@else{{ $card->name }}@endif{{ '' }}@action('conversations_table.after_subject', $card->conversation_cached())@if ($card->conversation_cached()->threads_count > 1) <span class="kn-conv-counter">{{ $card->conversation_cached()->threads_count }}</span>@endif
            </div>
            <div class="kn-card-tags">
                @if ($card->conversation_cached()->isChat() && $card->conversation_cached()->getChannelName())<span class="fs-tag"><span class="fs-tag-name">{{ $card->conversation_cached()->getChannelName() }}</span></span>@endif
                @if (!empty($card->conversation_cached()->tags))
	                @foreach ($card->conversation_cached()->tags as $i => $tag)
	                    {!! \View::make('tags::partials/conversation_list_tag', ['tag' => $tag])->render() !!}
	                    @if ($i != count($card->conversation_cached()->tags)-1) @endif
	                @endforeach
	            @endif
            </div>
            @if (($mailbox_id && $mailbox_id == \Kanban::ALL_MAILBOXES) || ($board_id && $card->kn_board_id && $card->kn_board && $card->kn_board->mailbox_id != $card->conversation_cached()->mailbox_id))
                <div class="kn-card-preview">[{{ $card->conversation_cached()->mailbox->name }}]</div>
            @endif
            @if (($card->name && $card->conversation_cached()->preview != $card->name)
                || (!$card->name && $card->conversation_cached()->preview != $card->conversation_cached()->getSubject()))
                <div class="kn-card-preview">{{ $card->conversation_cached()->preview }}</div>
            @endif
            @if (\Module::isActive('customfields') && $card->conversation_cached()->custom_fields)
                @php
                    $kn_cf_nonempty = false;
                @endphp
                @foreach($card->conversation_cached()->custom_fields as $custom_field)
                    @if ($custom_field->getAsText())
                        @php
                            $kn_cf_nonempty = true;
                            break;
                        @endphp
                    @endif
                @endforeach
                @if ($kn_cf_nonempty)
                    <div class="kn-card-custom-fields">
                        @php
                            $kn_cf_separate = false;
                        @endphp
                        @foreach($card->conversation_cached()->custom_fields as $custom_field)
                            @if ($custom_field->getAsText())
                                <span class="kn-card-cf">
                                    @if ($kn_cf_separate) | @endif
                                    <span class="kn-card-cf-name">{{ $custom_field->name }}:</span>
                                    <i class="kn-card-cf-value text-warning">{{ $custom_field->getAsText() }}</i>
                                </span>
                                @php
                                    $kn_cf_separate = true;
                                @endphp
                            @endif
                        @endforeach
                    </div>
                @endif
            @endif
            @if ($card->linked || ($card->conversation_cached()->user_id && $card->conversation_cached()->user))
                <div class="kn-card-footer">
                    @if ($card->linked)
                        <a href="{{ $card->conversation_cached()->url() }}" target="_blank">#{{ $card->conversation_cached()->number }}</a>
                    @endif
                    @if ($card->conversation_cached()->user_id && $card->conversation_cached()->user)
                        <nobr class="kn-card-assignee"><i class="glyphicon glyphicon-user"></i> {{ $card->conversation_cached()->user->getFullName() }}</nobr>
                    @endif
                </div>
            @endif
        @endif
    </div>
@endforeach