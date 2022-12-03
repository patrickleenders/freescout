<div class="row-container">
	<form class="form-horizontal kn-filter-form" method="POST" action="">

		<input type="hidden" name="filter" value="{{ $filter }}">

		<div class="kn-filters-inputs">
			@if ($filter != \Kanban::FILTER_BY_CF)
				@foreach($filters as $filter_id => $filter_title)
					<div class="control-group">
						<label class="checkbox" for="kn_filter_input_{{ $filter_id }}">
							<input type="checkbox" name="selected[]" value="{{ $filter_id }}" id="kn_filter_input_{{ $filter_id }}" @if (in_array($filter_id, $selected))checked="checked"@endif> {{ $filter_title }}
						</label>
					</div>
				@endforeach
			@else
				@foreach($custom_fields as $custom_field)
					<div class="control-group">
						<div class="form-group">
							<label class="col-xs-12 col-sm-3 control-label">{{ $custom_field->name }}</label>
							<div class="col-xs-2" >
		                    	<select class="form-control input-sized-sm" name="selected[op][{{ $custom_field->id }}]">
			                        <option value="=" {{ ($custom_field->op == '=') ? 'selected' : '' }}>=</option>
			                        <option value="&lt;" {{ ($custom_field->op == '<') ? 'selected' : '' }}>&lt;</option>
			                        <option value="&gt;" {{ ($custom_field->op == '>') ? 'selected' : '' }}>&gt;</option>
			                        <option value="&lt;=" {{ ($custom_field->op == '<=') ? 'selected' : '' }}>&lt;=</option>
			                        <option value="&gt;=" {{ ($custom_field->op == '>=') ? 'selected' : '' }}>&gt;=</option>
			                    </select>
			                </div>
							<div class="col-xs-10 col-sm-7">
								@if ($custom_field->type == CustomField::TYPE_DROPDOWN)
				                    <select class="form-control input-sized-sm" name="selected[value][{{ $custom_field->id }}]">
				                        <option value=""></option>
			                            @foreach($custom_field->options as $option_key => $option_name)
			                                <option value="{{ $option_key }}" {{ ($custom_field->value == $option_key) ? 'selected' : '' }}>{{ $option_name }}</option>
			                            @endforeach
				                    </select>
				                @else
				                    <input name="selected[value][{{ $custom_field->id }}]" {{--pattern="[^{{ \Kanban::FILTERS_SEPARATOR }}]*"--}} class="form-control input-sized-sm @if ($custom_field->type == CustomField::TYPE_DATE) kn-type-date @endif" value="{{ $custom_field->value }}"
				                        @if ($custom_field->type == CustomField::TYPE_NUMBER)
				                            type="number"
				                        @else
				                            type="text"
				                        @endif
				                    />
				                @endif
							</div>
						</div>
					</div>
	            @endforeach
			@endif
		</div>

		<div class="form-group text-center margin-top margin-bottom-10">
	        <button class="btn btn-primary btn-inline" data-loading-text="{{ __('Apply Filter') }}…">{{ __('Apply Filter') }}</button>
	    </div>
	</form>
</div>