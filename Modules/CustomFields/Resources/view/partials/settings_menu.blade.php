<li @if (Route::is('mailboxes.custom_fields'))class="active"@endif><a href="{{ route('mailboxes.custom_fields', ['id'=>$mailbox->id]) }}"><i class="glyphicon glyphicon-th-list"></i> {{ __('Custom Fields') }}</a></li>