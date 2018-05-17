<?php

namespace Ruudk\Payment\MultisafepayBundle\Form;

use Omnipay\Common\Issuer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IdealType extends DefaultType
{
    /**
     * @var Issuer[]
     */
    protected $issuers = array();

    /**
     * @param string $name
     * @param array  $issuers
     */
    public function __construct($name, array $issuers)
    {
        parent::__construct($name);

        foreach ($issuers as $issuerId => $issuerName) {
            $this->issuers[] = new Issuer($issuerId, $issuerName, 'ideal');
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $banks = array();
        $defaultBank = null;

        foreach ($this->issuers as $issuer) {
            if ('ideal' !== $issuer->getPaymentMethod()) {
                continue;
            }

            $banks[$issuer->getName()] = $issuer->getId();
            $defaultBank = $issuer->getId();
        }

        if (1 !== count($banks)) {
            $defaultBank = null;
        }

        if (!empty($options['bank'])) {
            $defaultBank = $options['bank'];
        }

        $builder->add('bank', ChoiceType::class, array(
            'label'             => 'ruudk_payment_multisafepay.ideal.bank.label',
            'data'              => $defaultBank,
            'placeholder'       => 'ruudk_payment_multisafepay.ideal.bank.empty_value',
            'choices'           => $banks,
        ));
    }

    /**
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options.
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
                                   'bank' => ''
                               ));

        $resolver->setAllowedTypes('bank', 'string');
    }
}
