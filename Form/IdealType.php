<?php

namespace Ruudk\Payment\MultisafepayBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class IdealType extends DefaultType
{
    /**
     * @var array
     */
    protected $banks = array(
        3151 => 'Test bank'
    );

    /**
     * @param string $name
     * @param string $cacheDir
     */
    public function __construct($name, $cacheDir = null)
    {
        $this->name = $name;

        if (null !== $cacheDir && is_file($cache = $cacheDir . '/ruudk_payment_multisafepay_ideal.php')) {
            $this->banks = require $cache;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('bank', 'choice', array(
            'label'       => 'ruudk_payment_multisafepay.ideal.bank.label',
            'empty_value' => 'ruudk_payment_multisafepay.ideal.bank.empty_value',
            'choices'     => $this->banks
        ));
    }
}
