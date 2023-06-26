<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Enovate extends Module
{
    private $_ps_new_style = false;

    private $_table_orders             = 'enovate_orders';
    private $_table_products           = 'enovate_products';
    const PS_OS_NAME = 'PS_OS_ENOVATE';
    const INVOICE    = 1;
    const PROFORMA   = 2;

    private $_tags = [
        'id'        => 'id',
        'reference' => 'reference',
        'date_add'  => 'date',
        'payment'   => 'payment',
    ];

    public function __construct()
    {
        $this->name = 'enovate';
        $this->tab = 'billing_invoicing';
        $this->version = '1.0.2';
        $this->author = 'spiderr';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->_ps_new_style = version_compare(_PS_VERSION_, '1.7.7') === 1;

        parent::__construct();

        $this->displayName = $this->l('ENOVATE module');
        $this->description = $this->l('integration with ENOVATE APIs');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('enovate')) {
            $this->warning = $this->l('No name provided');
        }
    }

    public function install()
    {
        return
            parent::install()
            && Configuration::updateValue('enovate_api_key', 'enovateSecret')
            && $this->installDb()
//            && $this->registerHook('displayAdminOrderMainBottom')
            && $this->installTab();
    }

    public function uninstall()
    {
        return
            $this->uninstallTab()
            && Configuration::updateValue('enovate_api_key', null)
            && parent::uninstall();
    }

    private function uninstallTab()
    {
        $tabs = [
//            'AdminEnovateMentor',
            'AdminEnovateData'
        ];
        foreach ($tabs as $tab) {
            $id_tab = Tab::getIdFromClassName($tab);
            if ($id_tab) {
                $tab = new Tab($id_tab);
                $tab->delete();
            }
        }
        return true;
    }

    private function installTab()
    {
        $tabs = [
//            [
//                'name'   => $this->name,
//                'class'  => 'AdminEnovateOrder',
//                'parent' => -1
//            ],
            [
                'name'   => 'Sincronizare ENOVATE',
                'class'  => 'AdminEnovateData',
                'parent' => 9
            ],
        ];
        $langs = language::getLanguages();
        foreach ($tabs as $_tab) {
            $tab = new Tab();
            $tab->name = [];
            foreach ($langs as $lang) {
                $tab->name[$lang['id_lang']] = $_tab['name'];
            }
            $tab->module = $this->name;
            $tab->id_parent = $_tab['parent'];
            $tab->class_name = $_tab['class'];
            $tab->save();
        }
        return true;
    }

    public function hookDisplayAdminOrderMainBottom(array $data)
    {
        return $this->hookDisplayAdminOrderLeft($data);
    }

    public function getInvoice($id_order, $options = [])
    {
        $sql = sprintf('SELECT * FROM ' . _DB_PREFIX_ . $this->_table_orders . ' WHERE `id_order`=%d', $id_order);
        $invoice = Db::getInstance()->getRow($sql);
        if ($invoice) {
            $sql = 'SELECT MAX(`invoice_number`) AS last_invoice_number FROM ' . _DB_PREFIX_ . $this->_table_orders;
            $last_number = Db::getInstance()->getRow($sql);
            $invoice['invoice_is_last'] = $last_number['last_invoice_number'] === $invoice['invoice_number'];
        }
        return $invoice;
    }

    public function hookDisplayAdminOrderLeft($data)
    {
        $invoice = $this->getInvoice($data['id_order']);

        $this->smarty->assign([
            'id_order'      => $data['id_order'],
            '_ps_new_style' => $this->_ps_new_style,
            'enovate'       => [
                'invoice_series'   => $invoice ? $invoice['invoice_series'] : '',
                'invoice_number'   => $invoice ? $invoice['invoice_number'] : '',
                'invoice_is_last'  => $invoice ? $invoice['invoice_is_last'] : 0,
                'has_stock_active' => strval(Configuration::get('oblio_company_management')) !== '',
                'is_last'          => $invoice ? $invoice['invoice_is_last'] : 0,
            ]
        ]);

        return $this->display(__FILE__, 'views/admin/order/view.tpl');
    }

    public function sendOrderToMentor($order, $options = [])
    {
        return array();
//        dd($order);
        if (!$order) {
            return array();
        }
        $cui = Configuration::get('oblio_company_cui');
        $email = Configuration::get('oblio_api_email');
        $secret = Configuration::get('oblio_api_secret');
        $workstation = Configuration::get('oblio_company_workstation');
        $management = Configuration::get('oblio_company_management');

        $exclude_reference = array();
        $oblio_exclude_reference = Configuration::get('oblio_exclude_reference');
        if (trim($oblio_exclude_reference) !== '') {
            $exclude_reference = array_map('trim', explode(',', $oblio_exclude_reference));
        }
        $oblio_product_category_on_invoice = (bool)Configuration::get('oblio_product_category_on_invoice');
        $oblio_product_discount_included = (bool)Configuration::get('oblio_product_discount_included');
        $oblio_company_products_type = strval(Configuration::get('oblio_company_products_type'));
        $oblio_generate_email_state = strval(Configuration::get('oblio_generate_email_state'));

        $fields = [
            'issuer_name',
            'issuer_id',
            'deputy_name',
            'deputy_identity_card',
            'deputy_auto',
            'seles_agent',
            'mentions'
        ];
        foreach ($fields as $field) {
            if (isset($options[$field])) {
                ${$field} = $options[$field];
                // Configuration::updateValue('oblio_' . $field, ${$field});
            } else {
                ${$field} = Configuration::get('oblio_' . $field);
            }
        }

        foreach ($this->_tags as $key => $tag) {
            switch ($tag) {
                case 'payment':
                    $payments = $order->getOrderPayments();
                    if (empty($payments)) {
                        $payment = 'Neplatit';
                    } else {
                        $lastPayment = end($payments);
                        $payment = $lastPayment->payment_method;
                    }
                    $mentions = str_replace('#' . $tag . '#', $payment, $mentions);
                    break;
                default:
                    $mentions = str_replace('#' . $tag . '#', $order->{$key}, $mentions);
            }
        }

        if (empty($options['docType'])) {
            $options['docType'] = 'invoice';
        }
        switch ($options['docType']) {
            case 'proforma':
                $series_name = Configuration::get('oblio_company_series_name_proforma');
                break;
            default:
                $series_name = Configuration::get('oblio_company_series_name');
        }

//        if (!$cui || !$email || !$secret || !$series_name) {
//            return [
//                'error' => 'Intra la module si configureaza datele pentru API eNovate'
//            ];
//        }

        require_once 'classes/EnovateApi.php';
        require_once 'classes/EnovateApiPrestashopAccessTokenHandler.php';
        require_once 'classes/WMEProductDTO.php';

        $row = $this->getInvoice($order->id, $options);
        if (!empty($row['invoice_number'])) {
            try {
                $api = new EnovateApi($email, $secret, new EnovateApiPrestashopAccessTokenHandler());
                $api->setCif($cui);

                $result = $api->get($options['docType'], $row['invoice_series'], $row['invoice_number']);
                return $result['data'];
            } catch (Exception $e) {
                // delete old
                // Db::getInstance()->delete($this->_table_invoice, sprintf('`id_order`=%d', $order->id));
                $this->updateNumbers($order->id, [
                    $options['docType'] . '_series' => '',
                    $options['docType'] . '_number' => 0,
                ]);
            }
        }

        $address = new Address((int)$order->id_address_invoice);
        $customer = new Customer((int)$order->id_customer);
        $currency = new Currency($order->id_currency);
        $products = $order->getCartProducts();
//        dd($order->getDetails);
        try {
            $contact = $address->firstname . ' ' . $address->lastname;
            $cuiClient = empty($address->vat_number) ? $address->dni : $address->vat_number;
            if ($cuiClient === 'dni') {
                $cuiClient = '';
            }

            $rc = '';
            $iban = '';
            $bank = '';
            $invoiceAddress = $address->address1;
            if (property_exists($address, 'address_facturare')) {
                $rc = $address->address_reg_com;
                $iban = $address->address_account;
                $bank = $address->address_bank;
                if (strlen(trim($address->address_info)) > 0) {
                    $invoiceAddress = trim($address->address_info);
                }
            }

            $data = array(
                'cif'                => $cui,
                'client'             => [
                    'cif'      => $cuiClient,
                    'name'     => empty(trim($address->company)) ? $contact : $address->company,
                    'rc'       => $rc,
                    'code'     => '',
                    'address'  => $invoiceAddress,
                    'state'    => State::getNameById($address->id_state),
                    'city'     => $address->city,
                    'country'  => $address->country,
                    'iban'     => $iban,
                    'bank'     => $bank,
                    'email'    => $customer->email,
                    'phone'    => $address->phone == '' ? $address->phone_mobile : $address->phone,
                    'contact'  => $contact,
                    'vatPayer' => preg_match('/RO/i', $cuiClient),
                    'save'     => true,
                ],
                'issueDate'          => date('Y-m-d'),
                'dueDate'            => '',
                'deliveryDate'       => '',
                'collectDate'        => '',
                'seriesName'         => $series_name,
                'language'           => 'RO',
                'precision'          => 2,
                'currency'           => $currency->iso_code,
                // 'exchangeRate'       => 1 / $currency->conversion_rate,
                'products'           => [],
                'issuerName'         => $issuer_name,
                'issuerId'           => $issuer_id,
                'noticeNumber'       => '',
                'internalNote'       => '',
                'deputyName'         => $deputy_name,
                'deputyIdentityCard' => $deputy_identity_card,
                'deputyAuto'         => $deputy_auto,
                'selesAgent'         => $seles_agent,
                'mentions'           => $mentions,
                'value'              => 0,
                'workStation'        => $workstation,
                'useStock'           => isset($options['useStock']) ? (int)$options['useStock'] : 0,
                'sendEmail'          => $oblio_generate_email_state,
                'orderId'            => $order->id
            );

            if (empty($data['referenceDocument'])) {
                if ($order->total_discounts_tax_incl > 0) {
                    $oblio_product_discount_included = true;
                }
                foreach ($products as $item) {
                    if (!empty($exclude_reference) && in_array($item['product_reference'], $exclude_reference)) {
                        continue;
                    }
                    $name = $item['product_name'];
                    $code = $item['product_reference'];
                    $vatName = $item['tax_rate'] > 0 ? null : 'SDD';
                    $productType = $this->getProductAttribute($item['product_id'], 'type');
                    if (!$productType) {
                        $productType = $oblio_company_products_type ? $oblio_company_products_type : 'Marfa';
                    }
                    if ($oblio_product_category_on_invoice) {
                        $product = new Product($item['product_id']);
                        $category = new Category((int)$product->id_category_default, (int)$this->context->language->id);
                        $name = $category->name;
                        $code = '';
                        $productType = 'Serviciu';
                    }
                    $price = self::getPrice($item, $currency, true);
                    $fullPrice = self::getPrice($item, $currency, false);
                    if ($oblio_product_discount_included) {
                        $fullPrice = $price;
                    }
                    $package_number = 1;
                    if ($item['id_product_attribute'] > 0) {
                        $package_number_key = 'package_number_' . $item['id_product_attribute'];
                        $package_number = (int)$this->getProductAttribute($item['id_product'], $package_number_key);
                    }
                    if ($package_number === 0) {
                        $package_number = (int)$this->getProductAttribute($item['id_product'], 'package_number');
                        if ($package_number === 0) {
                            $package_number = 1;
                        }
                    }
                    $data['products'][] = [
                        'name'          => $name,
                        'code'          => $code,
                        'description'   => '',
                        'price'         => $fullPrice / $package_number,
                        'measuringUnit' => 'buc',
                        'currency'      => $currency->iso_code,
                        'vatName'       => $vatName,
                        'vatPercentage' => $item['tax_rate'],
                        'vatIncluded'   => true,
                        'quantity'      => $item['product_quantity'] * $package_number,
                        'productType'   => $productType,
                        'management'    => $management,
                    ];
                    if (!$oblio_product_discount_included && $price !== $fullPrice) {
                        $totalOriginalPrice = $fullPrice * $item['product_quantity'];
                        $data['products'][] = [
                            'name'         => sprintf('Discount "%s"', $name),
                            'discount'     => round(
                                $totalOriginalPrice - $item['total_price_tax_incl'],
                                $data['precision']
                            ),
                            'discountType' => 'valoric',
                        ];
                    }
                }
                if ($order->total_shipping_tax_incl > 0) {
                    $data['products'][] = [
                        'name'          => 'Transport',
                        'code'          => '',
                        'description'   => '',
                        'price'         => $order->total_shipping_tax_incl,
                        'measuringUnit' => 'buc',
                        'currency'      => $currency->iso_code,
                        // 'vatName'       => 'Normala',
                        'vatPercentage' => round(
                                $order->total_shipping_tax_incl / $order->total_shipping_tax_excl * 100
                            ) - 100,
                        'vatIncluded'   => true,
                        'quantity'      => 1,
                        'productType'   => 'Serviciu',
                    ];
                }
                if ($order->total_discounts_tax_incl > 0) {
                    $data['products'][] = [
                        'name'         => 'Discount',
                        'discount'     => $order->total_discounts_tax_incl,
                        'discountType' => 'valoric',
                    ];
                }
            }

            $api = new EnovateApi($email, $secret, new EnovateApiPrestashopAccessTokenHandler());

//            dd($data);
            $result = $api->createInvoice($data);

            $changeState = Configuration::get('oblio_generate_change_state');
            $state_id = (int)Configuration::get(self::PS_OS_NAME);
            if ($changeState && $state_id) {
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
                $history->id_employee = (int)$this->context->employee->id;

                $use_existings_payment = !$order->hasInvoice();
                $history->changeIdOrderState($state_id, $order, $use_existings_payment);
                $history->addWithemail(true, []);
            }


            $this->updateNumbers($order->id, [
                $options['docType'] . '_series' => $result['data']['seriesName'],
                $options['docType'] . '_number' => $result['data']['number'],
            ]);
            return $result['data'];
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage()
            );
        }
    }

    public function getProductAttribute($id_product, $attribute)
    {
        $sql = sprintf(
            'SELECT `value` FROM %s WHERE `id_product`=%d AND `attribute`="%s"',
            _DB_PREFIX_ . $this->_table_product_attributes,
            $id_product,
            pSQL($attribute)
        );
        $result = Db::getInstance()->getValue($sql);
        return $result;
    }

    public static function getPrice($item, $currencyTo, $usereduc = false)
    {
        if ($usereduc) {
            $price = $item['unit_price_tax_incl'];
        } else {
            $price = ($item['original_product_price'] * (1 + $item['tax_rate'] / 100));
        }
        return number_format($price, 6, '.', '');
    }

    public function updateNumbers($id_order, $options = [])
    {
        $invoice = [];
        if (isset($options['invoice_series']) && isset($options['invoice_number'])) {
            $invoice = [
                'id_order'       => (int)$id_order,
                'type'           => self::INVOICE,
                'invoice_series' => pSQL($options['invoice_series']),
                'invoice_number' => (int)$options['invoice_number'],
            ];
        } else {
            if (isset($options['proforma_series']) && isset($options['proforma_number'])) {
                $invoice = [
                    'id_order'       => (int)$id_order,
                    'type'           => self::PROFORMA,
                    'invoice_series' => pSQL($options['proforma_series']),
                    'invoice_number' => (int)$options['proforma_number'],
                ];
            }
        }

        if (empty($invoice)) {
            return false;
        }
        if ($invoice['invoice_number'] === 0) {
            $where = sprintf('`id_order`=%d AND `type`=%d', $invoice['id_order'], $invoice['type']);
            Db::getInstance()->delete($this->_table_orders, $where);
        } else {
            Db::getInstance()->insert($this->_table_orders, $invoice);
        }
        return true;
    }

    private function installDb()
    {
        $createSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . $this->_table_orders . '` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `id_order` int unsigned NOT NULL,
        `status` tinyint NOT NULL DEFAULT 0,
        `wme_number` varchar(20) NULL DEFAULT NULL,
        `request` text NULL,
        `response` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        ) ENGINE = ' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8;';
        $result_table_orders = Db::getInstance()->execute($createSql);

        $createSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . $this->_table_products . '` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `id_product` int unsigned NOT NULL,
        `status` tinyint NOT NULL DEFAULT 0,
        `wme_number` varchar(20) NULL DEFAULT NULL,
        `request` text NULL,
        `response` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        ) ENGINE = ' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8;';
        $result_table_products = Db::getInstance()->execute($createSql);

        return $result_table_orders && $result_table_products;
    }

    public function syncProducts()
    {
        require_once 'classes/EnovateProducts.php';
        $model = new EnovateProducts();
        $encoders = [new \Symfony\Component\Serializer\Encoder\JsonEncoder()];
        $normalizers = [new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer()];

        $serializer = new \Symfony\Component\Serializer\Serializer($normalizers, $encoders);

        $items = $model->getAll();

        $context = Context::getContext();
        $id_shop = $context->shop->id;
        $id_lang = $context->language->id;

        // retrieve address informations
        $address = new Address();
        $address->id_country = $context->country->id;
        $address->id_state = 0;
        $address->postcode = 0;

        $batch = [];

        foreach ($items as $item) {
            $product = new Product($item['id_product'], false, $id_lang);
            $tax_manager = TaxManagerFactory::getManager(
                $address,
                Product::getIdTaxRulesGroupByIdProduct((int)$item['id_product'], $context)
            );
            $product_tax_calculator = $tax_manager->getTaxCalculator();

            $enovateProduct = new EnovateProductDTO();
            $enovateProduct->setId($product->id);
            $enovateProduct->setName($product->name);
            $enovateProduct->setCode($product->reference);
            $enovateProduct->setVatRate($product_tax_calculator->getTotalRate());
            $enovateProduct->setUm('buc');
            $enovateProduct->setPrice(Product::getPriceStatic($product->id, true, null, 2));

            $batch[] = $serializer->normalize($enovateProduct);

//            $combinations = $this->getCombinations($product->id, $id_lang);
//            if (!empty($combinations)) {
//                foreach ($combinations as $combination) {
//
//                    $line[0] = $product->name . $combination['attribute_designation'];
//                    $line[2] = $combination['reference'];
//                    $line[3] = $combination['quantity'];
//                }
//            }
        }

        $response = count($batch);

        if ($response > 0) {
            try {
                require_once 'classes/EnovateApi.php';
                require_once 'classes/EnovateApiPrestashopAccessTokenHandler.php';
                $accessTokenHandler = new EnovateApiPrestashopAccessTokenHandler();
                $apiKey = Configuration::get('enovate_api_key');
                $api = new EnovateApi($apiKey, $accessTokenHandler);

                $result = $api->createProducts($batch);

                if ($result['status'] == 'error') {
                    $message = json_decode($result['message']);
                    $error = implode('<br>', $message->ErrorList);
                } else {
                    $this->updateEnovateProducts($batch);
                }
                $this->updateEnovateProducts($batch);
            } catch (Exception $e) {
                $error = $e->getMessage();
                // $accessTokenHandler->clear();
            }
        }

        echo json_encode([$response, ($error ?? null)]);
    }

    public function updateEnovateProducts(array $batch)
    {
        $newRecords = [];
        foreach ($batch as $item) {
            $newRecords[] = [
                'id_product' => $item['id'],
                'status'     => 1,
                'wme_number' => $item['code']
            ];
        }

        if (empty($newRecords)) {
            return false;
        }

        Db::getInstance()->insert($this->_table_products, $newRecords);

        return true;
    }

    public function syncPrices(&$error = '')
    {
        $apiKey = Configuration::get('enovate_api_key');

        if (!$apiKey) {
            return 0;
        }

        $total = 0;
        $updated = 0;
        $notFound = 0;

        try {
            require_once 'classes/EnovateProducts.php';
            require_once 'classes/EnovateApi.php';
            require_once 'classes/EnovateApiPrestashopAccessTokenHandler.php';
            $accessTokenHandler = new EnovateApiPrestashopAccessTokenHandler();
            $api = new EnovateApi($apiKey, $accessTokenHandler);

            $offset = 0;
            $limitPerPage = 200;

            $model = new EnovateProducts();
            do {
                if ($offset > 0) {
                    usleep(200000);
                }
                $products = $api->nomenclature('getPricesFromMentor', null, [
                    'offset'      => $offset,
                ]);
                $index = 0;

                foreach ($products as $product) {
                    $post = $model->find($product);
                    if ($post) {
                        $model->updatePrice($post->id, $product);
                        $updated++;
                    } else {
                        $notFound++;
                        // $model->insert($product);
                    }
                    $index++;
                }
                $offset += $limitPerPage; // next page
            } while ($index === $limitPerPage);
            $total = $offset - $limitPerPage + $index;
        } catch (Exception $e) {
            $error = $e->getMessage();
            // $accessTokenHandler->clear();
        }

        return [
            'total' => $total,
            'updated' => $updated,
            'notFound' => $notFound
        ];
    }

    public function syncStock(&$error = '')
    {
        $apiKey = Configuration::get('enovate_api_key');

        if (!$apiKey) {
            return 0;
        }

        $total = 0;
        $updated = 0;
        $notFound = 0;

        try {
            require_once 'classes/EnovateProducts.php';
            require_once 'classes/EnovateApi.php';
            require_once 'classes/EnovateApiPrestashopAccessTokenHandler.php';
            $accessTokenHandler = new EnovateApiPrestashopAccessTokenHandler();
            $api = new EnovateApi($apiKey, $accessTokenHandler);

            $offset = 0;
            $limitPerPage = 200;

            $model = new EnovateProducts();
            do {
                if ($offset > 0) {
                    usleep(200000);
                }
                $products = $api->nomenclature('getStockFromMentor', null, [
                    'offset'      => $offset,
                ]);
                $index = 0;

                foreach ($products as $product) {
                    $post = $model->find($product);
                    if ($post) {
                        $model->updateStock($post->id, $product);
                        $updated++;
                    } else {
                        $notFound++;
                        // $model->insert($product);
                    }
                    $index++;
                }
                $offset += $limitPerPage; // next page
            } while ($index === $limitPerPage);
            $total = $offset - $limitPerPage + $index;
        } catch (Exception $e) {
            $error = $e->getMessage();
            // $accessTokenHandler->clear();
        }

        return [
            'total' => $total,
            'updated' => $updated,
            'notFound' => $notFound
        ];
    }

    public function sendPrices()
    {
        require_once 'classes/EnovateProducts.php';
        $model = new EnovateProducts();
        $encoders = [new \Symfony\Component\Serializer\Encoder\JsonEncoder()];
        $normalizers = [new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer()];

        $serializer = new \Symfony\Component\Serializer\Serializer($normalizers, $encoders);

        $items = $model->getAllSynced();

        $context = Context::getContext();
        $id_shop = $context->shop->id;
        $id_lang = $context->language->id;

        // retrieve address information
        $address = new Address();
        $address->id_country = $context->country->id;
        $address->id_state = 0;
        $address->postcode = 0;

        $batch = [];

        foreach ($items as $item) {
            $product = new Product($item['id_product'], false, $id_lang);
            $tax_manager = TaxManagerFactory::getManager(
                $address,
                Product::getIdTaxRulesGroupByIdProduct((int)$item['id_product'], $context)
            );
            $product_tax_calculator = $tax_manager->getTaxCalculator();

            $enovateProduct = new EnovateProductPriceDTO();
            $enovateProduct->setId($product->id);
            $enovateProduct->setCode($product->reference);
            $enovateProduct->setPrice(Product::getPriceStatic($product->id, true, null, 2));

            $batch[] = $serializer->normalize($enovateProduct);

//            $combinations = $this->getCombinations($product->id, $id_lang);
//            if (!empty($combinations)) {
//                foreach ($combinations as $combination) {
//
//                    $line[0] = $product->name . $combination['attribute_designation'];
//                    $line[2] = $combination['reference'];
//                    $line[3] = $combination['quantity'];
//                }
//            }
        }

        $response = count($batch);

        if ($response > 0) {
            try {
                require_once 'classes/EnovateApi.php';
                require_once 'classes/EnovateApiPrestashopAccessTokenHandler.php';
                $accessTokenHandler = new EnovateApiPrestashopAccessTokenHandler();
                $apiKey = Configuration::get('enovate_api_key');
                $api = new EnovateApi($apiKey, $accessTokenHandler);
                $errors = null;

                $result = $api->sendPrices($batch);

                if (!empty($result['errors'])) {
                    $errors = implode('<br>', $result['errors']);
                } else {
                    $this->updateEnovateProducts($batch);
                }
//                $this->updateEnovateProducts($batch);

            } catch (Exception $e) {
                $error = $e->getMessage();
                // $accessTokenHandler->clear();
            }
        }

        echo json_encode([$result['data'], $errors, ($error ?? null)]);
    }
}
