<?php

/**
 * Class Shopware_Plugins_BestitCustomerGroupAutomation_Bootstrap
 *
 * @category   Bestit
 * @package    Bestit
 * @subpackage CustomerGroupAutomation
 * @author     Ahmad El-Bardan <ahmadelbardan@hotmail.de>
 * @copyright  2016 best it GmbH & Co. KG
 * @license    http://www.bestit-online.de proprietary
 * @link       http://www.bestit-online.de
 */
class Shopware_Plugins_Backend_BestitCustomerGroupAutomation_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var array Plugin information.
     */
    private $json;

    /**
     * Shopware_Plugins_Backend_BestitCustomerGroupAutomation_Bootstrap constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        call_user_func_array('parent::__construct', func_get_args());

        $this->json = $this->_getPluginJson();
    }

    /**
     * Returns plugin information.
     *
     * @return array
     * @throws Exception
     */
    public function getInfo()
    {
        return array(
            'version' => $this->json['currentVersion'],
            'label' => $this->json['label']['de'],
            'copyright' => $this->json['copyright'],
            'author' => $this->json['author'],
            'supplier' => $this->json['supplier'],
            'description' => $this->json['description'],
            'support' => $this->json['support'],
            'link' => $this->json['link']
        );
    }

    /**
     * Get Plugin Version, check for version.
     *
     * @return mixed
     */
    public function getVersion()
    {
        return $this->json['currentVersion'];
    }

    /**
     * Do one-time jobs during plugin install (subscribe event, add templates etc.).
     *
     * @return array|bool
     */
    public function install()
    {
        try {
            $this->subscribeEvents();
            $this->createAttributes();
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }

        return array(
            'success' => true,
            'message' => ''
        );
    }

    /**
     * Remove all related data to our plugin (which is not automatically removed).
     *
     * @return array|bool
     */
    public function uninstall()
    {
        try {
            $this->deleteAttributes();
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }

        return array(
            'success' => true,
            'message' => ''
        );
    }

    /**
     * Grab country of customer after registration.
     *
     * @param Enlight_Event_EventArgs $args
     * @return void
     */
    public function onModulesAdminSaveRegisterSuccessful(Enlight_Event_EventArgs $args)
    {
        $customerId = $args->get('id');
        /** @var \Shopware\Models\Customer\Customer $customer */
        $customer = Shopware()->Models()->find("Shopware\\Models\\Customer\\Customer", $customerId);

        /** @var \Shopware\Models\Customer\Address $billingAddress */
        $billingAddress = $customer->getDefaultBillingAddress();
        $billingCountry = $billingAddress->getCountry();

        $customerGroupKey = $this->getAssociatedCustomerGroupKey($billingCountry);

        $this->setCustomerGroup($customer, $customerGroupKey);
    }

    /**
     * Returns plugin meta data if file is available.
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    private function _getPluginJson()
    {
        $json = json_decode(
            file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'),
            true
        );

        if (is_array($json) === true)
            return $json;

        throw new InvalidArgumentException('Cannot find plugin.json file.');
    }

    /**
     * Subscribe events.
     *
     * @return void
     */
    private function subscribeEvents()
    {
        $this->subscribeEvent(
            'Shopware_Modules_Admin_SaveRegister_Successful',
            'onModulesAdminSaveRegisterSuccessful'
        );
    }

    /**
     * Create attributes.
     *
     * @return void
     * @throws Exception
     */
    private function createAttributes()
    {
        $this->get('shopware_attribute.crud_service')->update(
            's_core_customergroups_attributes',
            'country_association',
            'single_selection',
            [
                'label' => 'Country Association',
                'displayInBackend' => true,
                'entity' => \Shopware\Models\Country\Country::class,
            ],
            null,
            true
        );
    }

    /**
     * Delete attributes.
     *
     * @return void
     * @throws Exception
     */
    private function deleteAttributes()
    {
        $this->get('shopware_attribute.crud_service')->delete(
            's_core_customergroups_attributes',
            'country_association'
        );
    }

    /**
     * Get the associated customer group key (return NULL if it is not set).
     *
     * @param \Shopware\Models\Country\Country $country
     * @return string|null
     */
    private function getAssociatedCustomerGroupKey(Shopware\Models\Country\Country $country)
    {
        $customerGroups = Shopware()->Models()->getRepository('\Shopware\Models\Customer\Group')->findAll();

        foreach ($customerGroups as $customerGroup)
        {
            $customerAttribute = $customerGroup->getAttribute();

            if ($customerAttribute === null)
                continue;


            $countryId = (int) $customerAttribute->getCountryAssociation();

            if ($countryId === $country->getId())
                return $customerGroup->getKey();
        }

        return NULL;
    }

    /**
     * Set the customer group.
     *
     * @param \Shopware\Models\Customer\Customer $customer
     * @param string|null $customerGroupKey
     */
    private function setCustomerGroup(Shopware\Models\Customer\Customer $customer, $customerGroupKey)
    {
        if ($customerGroupKey === NULL)
            return;

        $queryBuilder = $this->get('dbal_connection')->createQueryBuilder();
        $queryBuilder->update('s_user', 'u')
            ->set('u.customergroup', ':groupKey')
            ->where('u.id = :id')
            ->setParameter(':groupKey', $customerGroupKey)
            ->setParameter(':id', $customer->getId());

        $queryBuilder->execute();
    }
}