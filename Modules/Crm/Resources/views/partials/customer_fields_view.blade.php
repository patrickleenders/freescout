@if (count($customer_fields))
    @foreach ($customer_fields as $customer_field)
        @if ($customer_field->display && $customer_field->value != '')
            <div class="customer-section">
                {{ $customer_field->name }}: 
                @if ($customer_field->type == CustomerField::TYPE_DATE)
                    {{ App\User::dateFormat($customer_field->value, 'M j, Y', false) }}
                @elseif ($customer_field->type == CustomerField::TYPE_DROPDOWN)
                    @if (is_array($customer_field->options) && isset($customer_field->options[$customer_field->value]))
                        {{ $customer_field->options[$customer_field->value] }}
                    @endif
                @else
                    {{ $customer_field->value }}
                @endif
            </div>
        @endif
    @endforeach
@endif