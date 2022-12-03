<?php

namespace Modules\CustomerDataEnrichment\Providers;

use App\Customer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class CustomerDataEnrichmentServiceProvider extends ServiceProvider
{
    const ENRICHED_NOT_EXECUTED = 0;
    const ENRICHED_DONE         = 1;
    const ENRICHED_DONE_NO_DATA = 2;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Run enrichment for 
        \Eventy::addAction('email.created', function($email) {

            if ($email->customer->enriched) {
                return;
            }
            
            // Create a background job.
            \Helper::backgroundAction('customer.enrich', [
                $email->customer,
                $email->email
            ]);
        });

        \Eventy::addAction('customer.enrich', function($customer, $email) {
            self::enrichCustomer($customer, $email);
        }, 20, 2);
    }

    public static function enrichCustomer($customer, $email)
    {
        if ($customer->enriched) {
            return;
        }
        
        $result_photo = self::enrichCustomerPhoto($customer, $email);
        
        // Does not work anymore.
        //$result_data = self::enrichCustomerData($customer, $email);
        $result_data = false;

        if ($result_photo || $result_data) {
            $customer->enriched = self::ENRICHED_DONE;
        } else {
            $customer->enriched = self::ENRICHED_DONE_NO_DATA;
        }

        $customer->save();
    }

    public static function enrichCustomerPhoto($customer, $email)
    {
        if ($customer->photo_url) {
            return false;
        }

        $hash = md5($email);
        $uri = 'http://www.gravatar.com/avatar/' . $hash . '?d=404';

        try {
            $headers = get_headers($uri);

            if (!preg_match("/200/", $headers[0])) {
                return false;
            }

            $image_data = file_get_contents($uri);

            if (!$image_data) {
                return false;
            }

            $temp_file = tempnam(sys_get_temp_dir(), 'photo');

            \File::put($temp_file, $image_data);

            $photo_url = $customer->savePhoto($temp_file, \File::mimeType($temp_file));

            if ($photo_url) {
                $customer->photo_url = $photo_url;
                return true;
            } else {
                return false;
            }

        } catch (\Exception $e) {
            \Log::error('Error on enriching customer photo: '.$e->getMessage());
            return false;
        }
    }

    public static function enrichCustomerData($customer, $email)
    {
        $enriched = false;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://domainbigdata.com');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                '__VIEWSTATE'          => '/wEPDwUKLTIyNzI4NTM3OWRkbQTocXRG4G/bwHJusFGsCAJXMwZGTRCvHdnsaRpPEvw=',
                '__VIEWSTATEGENERATOR' => 'CA0B0334',
                '__EVENTTARGET'        => 'search',
                '__EVENTARGUMENT'      => $email,
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $html = curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            \Log::error('Error on enriching customer data: '.$e->getMessage());
            return false;
        }

        if (strstr($html, 'is associated to this person')) {
            $first_name = '';
            $last_name = '';
            preg_match("#Name</td>[\r\n\t ]+<td>[^\r\n]+>([^<\r\n]+)</a></td>#", $html, $m);

            if (!empty($m[1])) {
                $m[1] = \Helper::entities2utf8($m[1]);
                if (strstr($m[1], ' ')) {
                    list($first_name, $last_name) = explode(' ', $m[1], 2);
                } else {
                    $first_name = $m[1];
                }
            }

            $address = '';
            preg_match("#Address</td>[\r\n\t ]+<td>([^<\r\n]+)</td>+#", $html, $m);
            if (!empty($m[1])) {
                $address = \Helper::entities2utf8($m[1]);
            }

            $city = '';
            preg_match("#City</td>[\r\n\t ]+<td>([^<\r\n]+)</td>+#", $html, $m);
            if (!empty($m[1])) {
                $city = \Helper::entities2utf8($m[1]);
            }

            $state = '';
            preg_match('#State</td>[\r\n\t ]+<td colspan="2">([^<\r\n]+)</td>+#', $html, $m);
            if (!empty($m[1])) {
                $state = \Helper::entities2utf8($m[1]);
            }

            $country = '';
            preg_match("#Country</td>[\r\n\t ]+<td>[^\r\n]+([A-Z][A-Z])\.png#", $html, $m);
            if (!empty($m[1])) {
                $country = \Helper::entities2utf8($m[1]);
            }

            $phone = '';
            preg_match('#Phone</td>[\r\n\t ]+<td colspan="2">([^<\r\n]+)</td>+#', $html, $m);
            if (!empty($m[1])) {
                $phone = \Helper::entities2utf8($m[1]);
            }

            $websites = [];
            preg_match_all("#domain details[^>\r\n]+>([^<]+)<#", $html, $m);
            if (!empty($m[1])) {
                foreach ($m[1] as $i => $website) {
                    if ($i >= 10) {
                        break;
                    }
                    $customer->addWebsite(\Helper::entities2utf8($website));
                }
                $enriched = true;
            }

            if ($first_name && !$customer->first_name) {
                $customer->first_name = $first_name;
                $enriched = true;
            }
            if ($last_name && !$customer->last_name) {
                $customer->last_name = $last_name;
                $enriched = true;
            }
            if ($address && !$customer->address) {
                $customer->address = $address;
                $enriched = true;
            }
            if ($city && !$customer->city) {
                $customer->city = $city;
                $enriched = true;
            }
            if ($state && !$customer->state) {
                $customer->state = $state;
                $enriched = true;
            }
            if ($country && !$customer->country) {
                $customer->country = $country;
                $enriched = true;
            }
            if ($phone) {
                $other_customer = Customer::findByPhone($phone);
                if (!$other_customer) {
                    $customer->addPhone($phone);
                    $enriched = true;
                }
            }

            return $enriched;
        }

        return false;
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('customerdataenrichment.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'customerdataenrichment'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/customerdataenrichment');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/customerdataenrichment';
        }, \Config::get('view.paths')), [$sourcePath]), 'customerdataenrichment');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
