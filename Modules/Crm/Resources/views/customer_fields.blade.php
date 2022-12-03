@extends('layouts.app')

@section('title', __('Customer Fields'))

@section('content')
	<div class="section-heading">
        {{ __('Customer Fields') }}<a href="{{ route('crm.ajax_html', ['action' => 'create_customer_field']) }}" class="btn btn-primary margin-left new-custom-field" data-trigger="modal" data-modal-title="{{ __('New Customer Field') }}" data-modal-no-footer="true" data-modal-size="lg" data-modal-on-show="crmInitNewCustomerField">{{ __('New Customer Field') }}</a>
    </div>
    @if (count($settings['customer_fields']))
	    <div class="row-container">
	    	<div class="col-md-11">
				<div class="panel-group accordion margin-top" id="cmr-customer-fields-index">
					@foreach ($settings['customer_fields'] as $customer_field)
				        <div class="panel panel-default panel-sortable" id="crm-customer-field-{{ $customer_field->id }}" data-customer-field-id="{{ $customer_field->id }}">
				            <div class="panel-heading">
				            	<span class="handle"><i class="glyphicon glyphicon-menu-hamburger"></i></span>
				                <h4 class="panel-title">
				                    <a data-toggle="collapse" data-parent="#accordion" href="#collapse-{{ $customer_field->id }}">
				                    	<span><small class="glyphicon @if ($customer_field->display) glyphicon-eye-open @else glyphicon-eye-close @endif" title="{{ __('Display in Profile') }}"></small> {{ $customer_field->name }} <small>(ID: {{ $customer_field->id }})</small>@if ($customer_field->required) <i class="required-asterisk"></i>@endif</span>
				                    </a>
				                </h4>
				            </div>
				            <div id="collapse-{{ $customer_field->id }}" class="panel-collapse collapse">
				                <div class="panel-body">
									<form class="form-horizontal crm-customer-field-form" method="POST" action="" data-customer_field_id="{{ $customer_field->id }}" >

										@include('crm::partials/customer_fields_form_update', ['mode' => 'update'])

										<div class="form-group margin-top margin-bottom-10">
									        <div class="col-sm-10 col-sm-offset-2">
									            <button class="btn btn-primary" data-loading-text="{{ __('Saving') }}…">{{ __('Save Field') }}</button> 
									            <a href="#" class="btn btn-link text-danger crm-customer-field-delete" data-loading-text="{{ __('Deleting') }}…" data-customer_field_id="{{ $customer_field->id }}">{{ __('Delete') }}</a>
									        </div>
									    </div>
									</form>
				                </div>
				            </div>
				        </div>
				    @endforeach
			    </div>
			</div>
		</div>
	@else
		@include('partials/empty', ['icon' => 'list-alt', 'empty_header' => __("Customer Fields")])
	@endif
@endsection

@section('javascript')
    @parent
    crmInitCustomerFieldsAdmin();
@endsection