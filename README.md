RuudkPaymentAdyenBundle
=======================

A Symfony2 Bundle that provides access to the Adyen API. Based on JMSPaymentCoreBundle.

## Installation

### Step1: Require the package with Composer

````
php composer.phar require ruudk/payment-adyen-bundle
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

        new Ruudk\Payment\AdyenBundle\RuudkPaymentAdyenBundle(),
    );
}
```

### Step3: Configure

Add the following to your routing.yml:
```yaml
ruudk_payment_adyen_notifications:
    pattern:  /webhook/adyen
    defaults: { _controller: ruudk_payment_adyen.controller.notification:processNotification }
    methods:  [GET, POST]
```

Add the following to your config.yml:
```yaml
ruudk_payment_adyen:
    merchant_account:  Your merchant account
    skin_code:         Your skin code
    secret_key:        Your secret key
    test:              true/false                  # Default true
    logger:            true/false                  # Default true
    timeout:           the timeout in seconds      # Default 5
    shopper_locale:    the locale Adyen should use # Default null
    methods:
        - ideal
        - mister_cash
        - giropay
        - direct_ebanking
        - credit_card      # amex,visa,mc
```

Make sure you set the `return_url` in the `predefined_data` for every payment method you enable:
````php
$form = $this->getFormFactory()->create('jms_choose_payment_method', null, array(
    'amount'   => $order->getAmount(),
    'currency' => 'EUR',
    'predefined_data' => array(
        'adyen_ideal' => array(
            'return_url' => $this->generateUrl('order_complete', array(), true),
        ),
        'adyen_giropay' => array(
            'return_url' => $this->generateUrl('order_complete', array(), true),
        ),
        // etc...
    ),
));
````

See [JMSPaymentCoreBundle documentation](http://jmsyst.com/bundles/JMSPaymentCoreBundle/master/usage) for more info.
