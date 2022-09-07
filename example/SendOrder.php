<?php

use ExactSoapClient\ExactSoapConfig;
use ExactSoapClient\ExactSoapClient;

function sendOrder(array $order_header, array $order_lines): void {
    $config = new ExactSoapConfig(
        '127.0.0.1',
        'DOMAIN\\user',
        'xxx',
        'server\\host',
        'db-name'
    );

    try {
        $soapClient = new ExactSoapClient($config);

        // The transaction key is returned by the first call.
        $transaction_key = '';

        foreach ($order_lines as $order_line) {
            $data = [
                'LineNumber' => $order_line['line'],
                'Itemcode' => $order_line['sku'],
                'Quantity' => $order_line['quantity'],
                'VATCode' => $order_line['vatcode']
            ];

            if ($transaction_key) {
                $data['TransactionKey'] = $transaction_key;
            }

            $transaction_key = $soapClient->callSoapGetProperty(ExactSoapClient::ACTION_CREATE,'SalesOrderLine', $data, 'TransactionKey');
        }

        // When values are omitted, for example the InvoiceDebtor or DeliveryDebtor, Exact will autofill the field.
        $data = [
            'OrderDebtor' => sprintf('%6s', $order_header['debtor_nr']),
            'DeliveryDebtorName' => $order_header['shipping_company'],
            'DeliveryDebtorContactperson' => $order_header['shipping_contact'],
            'DeliveryDebtorAddress1' => $order_header['shipping_address1'],
            'DeliveryDebtorAddress2' => $order_header['shipping_address2'] ?? '',
            'DeliveryDebtorAddress3' => $order_header['shipping_address3'] ?? '',
            'DeliveryDebtorPostCode' => $order_header['shipping_postal_code'],
            'DeliveryDebtorCity' => $order_header['shipping_city'],
            'DeliveryDebtorStateCode' => $order_header['shipping_state_code'],
            'DeliveryDebtorCountryCode' => $order_header['shipping_country'],
            'PaymentCondition' => $order_header['payment_condition'],
            'CurrencyCode' => $order_header['currency_code'],
            'TotalAmount' => $order_header['total_inc_vat'],
            'TotalAmountExcludingVAT' => $order_header['total_ex_vat'],
            'TotalVATAmount' => $order_header['total_vat'],
            'Reference' => $order_header['description'] ?? 'No description supplied',
            'SelectionCode' => $order_header['shop_code'],
            'YourReference' => $order_header['reference'] ?? 'No reference supplied',
            'ShippingVia' => $order_header['shipping_method']
        ];

        if ($transaction_key) {
            $data['TransactionKey'] = $transaction_key;
        }

        // Field with an empty string will result in "0" within Exact.
        // Remove all field with empty strings or other.
        foreach ($data as $i => $field) {
            if (empty($field)) {
                unset($data[$i]);
            }
        }

        $order_nr = $soapClient->callSoapGetProperty(ExactSoapClient::ACTION_CREATE,'SalesOrderHeader', $data, 'SalesOrderNumber');

        // Do something nice with the returned order nr.
    } catch (\Exception $ex) {
        print "Oh oh: {$ex->getMessage()}";
    }
}
