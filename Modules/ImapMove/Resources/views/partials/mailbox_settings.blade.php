<div>
    <div class="form-group">
        <label for="imap_sent_folder" class="col-sm-2 control-label">{{ __('After Fetching') }}</label>

        <div class="col-sm-6">
            @php
                $imapmove_action = old('imapmove_action', $mailbox->getMeta('imapmove')['action'] ?? '') ?: \ImapMove::ACTION_READ;
            @endphp
            <div class="control-group">
                <label for="imapmove_action_{{ \ImapMove::ACTION_READ }}" class="radio inline plain display-block"><input type="radio" name="imapmove_action" value="{{ \ImapMove::ACTION_READ }}" id="imapmove_action_{{ \ImapMove::ACTION_READ }}" @if ($imapmove_action == \ImapMove::ACTION_READ)checked="checked"@endif> {{ __('Mark email as read') }}</label>
            </div>
            <div class="control-group">
                <label for="imapmove_action_{{ \ImapMove::ACTION_REMOVE }}" class="radio inline plain"><input type="radio" name="imapmove_action" value="{{ \ImapMove::ACTION_REMOVE }}" id="imapmove_action_{{ \ImapMove::ACTION_REMOVE }}" @if ($imapmove_action == \ImapMove::ACTION_REMOVE)checked="checked"@endif> {{ __('Remove') }}</label>
            </div>
            <div class="control-group">
                <label for="imapmove_action_{{ \ImapMove::ACTION_MOVE }}" class="radio inline"><input type="radio" name="imapmove_action" value="{{ \ImapMove::ACTION_MOVE }}" id="imapmove_action_{{ \ImapMove::ACTION_MOVE }}" @if ($imapmove_action == \ImapMove::ACTION_MOVE)checked="checked"@endif> {{ __('Move to IMAP folder') }}</label>
            </div>

            <label class="radio inline"><input type="text" class="form-control input-sized" name="imapmove_folder" value="{{ old('imapmove_folder', $mailbox->getMeta('imapmove')['folder'] ?? '') }}" maxlength="255" placeholder="{{ __('IMAP Folder') }}"></label>
        </div>
    </div>
    <hr/>
</div>