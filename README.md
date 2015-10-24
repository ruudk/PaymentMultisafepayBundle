RuudkPaymentMultisafepayBundle
============================

A Symfony2 Bundle that provides access to the MultiSafepay API. Based on JMSPaymentCoreBundle.

## Installation

### Step1: Require the package with Composer

````
php composer.phar require ruudk/payment-multisafepay-bundle
````

### Step2: Enable the bundle

Enable the bundle in the kernel:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...

        new Ruudk\Payment\MultisafepayBundle\RuudkPaymentMultisafepayBundle(),
    );
}
```

### Step3: Configure

Add the following to your routing.yml:
```yaml
ruudk_payment_multisafepay_notifications:
    pattern:  /webhook/multisafepay
    defaults: { _controller: ruudk_payment_multisafepay.controller.notification:processNotification }
    methods:  [GET, POST]
```

Add the following to your config.yml:
```yaml
ruudk_payment_multisafepay:
    account_id:         Your account id
    site_id:            Your site id
    site_code:          Your secure site code
    test:               true/false   # Default true
    report_url:         http://host/webhook/multisafepay
    logger:             true/false   # Default true
    methods:
        - ideal
        - mister_cash
        - giropay
        - direct_ebanking
        - visa
        - mastercard
        - maestro
        - bank_transfer
        - direct_debit
```

Make sure you set the `return_url`, `cancel_url` and `client_ip` in the `predefined_data` for every payment method you enable:
````php
    $form = $this->getFormFactory()->create('jms_choose_payment_method', null, array(
        'amount'   => $order->getAmount(),
        'currency' => 'EUR',
        'predefined_data' => array(
            'multisafepay_ideal' => array(
                'return_url' => $this->generateUrl('order_complete', array(), true),
                'cancel_url' => $this->generateUrl('payment_cancelled', array(), true),
                'client_ip' => $request->getClientIp(),
            ),
            'multisafepay_mister_cash' => array(
                'return_url' => $this->generateUrl('order_complete', array(), true),
                'cancel_url' => $this->generateUrl('payment_cancelled', array(), true),
                'client_ip' => $request->getClientIp(),
            ),
            // etc...
        ),
    ));
````
It's also possible to set a `description` for the transaction in the `predefined_data`.

See [JMSPaymentCoreBundle documentation](http://jmsyst.com/bundles/JMSPaymentCoreBundle/master/usage) for more info.
