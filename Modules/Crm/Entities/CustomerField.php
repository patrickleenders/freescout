<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Watson\Rememberable\Rememberable;

class CustomerField extends Model
{
    use Rememberable;

    // This is obligatory.
    public $rememberCacheDriver = 'array';

    public $timestamps = false;

    const NAME_PREFIX       = 'cf_';

	const TYPE_DROPDOWN   = 1;
	const TYPE_SINGLE_LINE = 2;
	const TYPE_MULTI_LINE = 3;
	const TYPE_NUMBER     = 4;
	const TYPE_DATE       = 5;
	
	public static $types = [
		self::TYPE_DROPDOWN   => 'Dropdown',
		self::TYPE_SINGLE_LINE => 'Single Line',
		self::TYPE_MULTI_LINE => 'Multi Line',
		self::TYPE_NUMBER     => 'Number',
		self::TYPE_DATE       => 'Date',
	];

    protected $fillable = [
    	'name', 'type', 'required', 'options'
    ];

	protected $attributes = [
        'type' => self::TYPE_DROPDOWN,
    ];

    protected $casts = [
        'options' => 'array',
    ];

    /**
     * To make types traanslatable.
     */
    public static function getTypes()
    {
    	return [
			1 => __('Dropdown'),
			2 => __('Single Line'),
			3 => __('Multi Line'),
			4 => __('Number'),
			5 => __('Date'),
    	];
    }

    public function setSortOrderLast()
    {
    	$this->sort_order = (int)CustomerField::max('sort_order')+1;
    }

    public static function getCustomerFields($cache = false)
    {
    	$query = CustomerField::orderby('sort_order');
        if ($cache) {
            $query->rememberForever();
        }
        return $query->get();
    }

    public static function getCustomerFieldsWithValues($customer_id)
    {
        return CustomerField::/*where('customer_fields.mailbox_id', $mailbox_id)*/
            select(['customer_fields.*', 'customer_customer_field.value', 'customer_customer_field.value'])
            ->orderby('customer_fields.sort_order')
            ->leftJoin('customer_customer_field', function ($join) use ($customer_id) {
                $join->on('customer_customer_field.customer_field_id', '=', 'customer_fields.id')
                    ->where('customer_customer_field.customer_id', '=', $customer_id);
            })
            ->get();
    }

    public static function getValue($customer_id, $customer_field_id)
    {
        $field = CustomerCustomerField::where('customer_id', $customer_id)
            ->where('customer_field_id', $customer_field_id)
            ->first();

        if ($field) {
            return $field->value;
        } else {
            return '';
        }
    }

    public static function setValue($customer_id, $customer_field_id, $value)
    {
        try {
            $field = CustomerCustomerField::firstOrNew([
                'customer_id' => $customer_id,
                'customer_field_id' => $customer_field_id,
            ]);

            $field->customer_id = $customer_id;
            $field->customer_field_id = $customer_field_id;
            $field->value = $value;
            $field->save();

            \Eventy::action('crm.customer_field.value_updated', $field, $customer_id);
        } catch (\Exception $e) {
            
        }
    }

    public function getNameEncoded()
    {
        return self::NAME_PREFIX.$this->id;
    }

    public static function decodeName($field_name)
    {
        return preg_replace("/^".self::NAME_PREFIX."/", '', $field_name);
    }

    public static function sanitizeValue($value, $field)
    {
        if ($field->type == CustomerField::TYPE_DROPDOWN) {
            if (!is_numeric($value) && array_search($value, $field->options)) {
                $value = array_search($value, $field->options);
            }
        } elseif ($field->type == CustomerField::TYPE_DATE) {
            if (!preg_match("/^\d\d\d\d\-\d\d\-\d\d$/", $value)) {
                $value = date("Y-m-d", strtotime($value));
            }
        } elseif ($field->type == CustomerField::TYPE_NUMBER) {
            if ($value) {
                $value = (int)$value;
            }
        }

        return $value;
    }

    public function getAsText()
    {
        if ($this->type == self::TYPE_DROPDOWN) {
            return $this->options[$this->value] ?? $this->value;
        } else {
            return $this->value;
        }
    }
}