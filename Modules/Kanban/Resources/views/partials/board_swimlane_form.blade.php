<div class="panel panel-default panel-sortable panel-sortable-input" data-swimlane-id="{{ $swimlane_id }}">
    <div class="panel-heading">
        <span class="handle" draggable="true"><i class="glyphicon glyphicon-menu-hamburger"></i></span>
        <h4 class="panel-title">
            <a>
                <span>
                    <div class="input-group">
                        <input name="swimlanes[{{ $swimlane_id }}][id]" type="hidden" value="{{ $swimlane_id }}" />
                        <input class="form-control" name="swimlanes[{{ $swimlane_id }}][name]" type="text" value="{{ $swimlane['name'] ?? '' }}" @if ($swimlane_id != \Kanban::PATTERN_ID) required @else required-x @endif>
                        <div class="input-group-btn">
                            <button type="button" class="btn btn-default kn-delete-swimlane" data-loading-text="…"><span class="glyphicon glyphicon-trash"></span></button>
                        </div>
                    </div>
                </span>
            </a>
        </h4>
    </div>
</div>