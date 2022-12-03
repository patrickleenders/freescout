<div class="panel panel-default panel-sortable panel-sortable-input" data-column-id="{{ $column_id }}">
    <div class="panel-heading">
        <span class="handle" draggable="true"><i class="glyphicon glyphicon-menu-hamburger"></i></span>
        <h4 class="panel-title">
            <a data-toggle="collapse" data-parent="#accordion" href="#kn-column-settings-{{ $column_id }}" aria-expanded="false" class="collapsed">
                <span>
                    
                    <div class="input-group">
                        <input class="form-control" name="columns[{{ $column_id }}][name]" type="text" value="{{ $column['name'] ?? '' }}" @if ($column_id != \Kanban::PATTERN_ID) required @else required-x @endif>
                        <div class="input-group-btn">
                            <button type="button" class="btn btn-default kn-delete-column" data-loading-text="…"><span class="glyphicon glyphicon-trash"></span></button>
                        </div>
                    </div>
                </span>
            </a>
        </h4>
    </div>
    <div id="kn-column-settings-{{ $column_id }}" class="panel-collapse collapse">
        <div class="panel-body">
            <input name="columns[{{ $column_id }}][id]" type="hidden" value="{{ $column_id }}" />

            <div class="form-group">
                <label class="col-md-4 control-label">{{ __('WIP Limit') }}</label>
                <div class="col-md-8">
                    <input class="form-control" name="columns[{{ $column_id }}][limit]" type="number" value="{{ $column['limit'] ?? '' }}" min="2">
                </div>
            </div>
        </div>
    </div>
</div>