<?php

namespace Icinga\Module\Servicenowimport\ProvidedHook\Director;

use GuzzleHttp\Exception\GuzzleException;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Servicenowimport\Api\Servicenow;

class ImportSource extends ImportSourceHook
{
    private $objectCache = null;

    const CLIENT_TIMEOUT = 20;
    const CLIENT_TLS_VERIFY = true;

    public function getName()
    {
        return "ServiceNow Table API";
    }

    protected static function addAuthOptions(QuickForm $form)
    {
        $method = $form->getSentOrObjectSetting('servicenow_authmethod');

        if ($method === 'BASIC') {
            $form->addElement(
                'text',
                'servicenow_username',
                [
                    'label' => 'ServiceNow API username',
                    'required' => true,
                    'description' => 'Username to authenticate at the ServiceNow API',
                ]
            );

            $form->addElement(
                'password',
                'servicenow_password',
                [
                    'label' => 'ServiceNow API password',
                    'required' => true,
                    'renderPassword' => true,
                    'description' => 'Password to authenticate at the ServiceNow API',
                ]
            );
        }

        if ($method === 'BEARER') {
            $form->addElement(
                'password',
                'servicenow_token',
                [
                    'label' => 'ServiceNow API token',
                    'required' => true,
                    'renderPassword' => true,
                    'description' => 'Token to authenticate at the ServiceNow API',
                ]
            );
        }
    }

    /**
     * @param  QuickForm $form
     * @return void
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement(
            'text',
            'servicenow_url',
            [
                'label' => 'ServiceNow API URL',
                'required' => true,
                'description' => 'ServiceNow API URL. Full-qualified URL including protocol and domain (e.g. https://example.service-now.com)',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_endpoint',
            [
                'label' => 'ServiceNow CMDB table endpoint',
                'required' => true,
                'description' => 'API endpoint to fetch objects from (e.g.: api/now/table/cmdb_ci_computer)',
            ]
        );

        $form->addElement(
            'select',
            'servicenow_authmethod',
            [
                'label' => 'ServiceNow API authentification method',
                'description' => 'Authentification method for the ServiceNow API',
                'multiOptions' => [
                    'BASIC' => 'Basic Auth',
                    'BEARER' => 'API Token',
                ],
                'class' => 'autosubmit',
                'required' => true,
            ]
        );

        static::addAuthOptions($form);

        $form->addElement(
            'text',
            'servicenow_timeout',
            [
                'label' => 'ServiceNow API timeout',
                'required' => false,
                'description' => 'Timeout in seconds used to query data from ServiceNow. Default is 20.',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_columns',
            [
                'label' => 'ServiceNow columns',
                'required' => false,
                'description' => 'Comma separated list of columns to fetch. Leave empty to fetch all columns.',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_query',
            [
                'label' => 'ServiceNow query',
                'required' => false,
                'description' => 'Query to filter records. Leave empty to fetch all records. '
                    . 'The query filter follows the official query syntax from ServiceNow: '
                    . 'https://www.servicenow.com/docs/bundle/yokohama-platform-user-interface/page/use/using-lists/concept/c_EncodedQueryStrings.html',
            ]
        );

        static::addProxy($form);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function listColumns(): array
    {
        if ($this->objectCache === null) {
            $this->objectCache = $this->getRecordsFromTable();
        }

        $columns = [];

        foreach ($this->objectCache as $object) {
            foreach ($object as $key => $value) {
                $columns[$key] = true;
            }
        }

        return array_keys($columns);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function fetchData(): array
    {
        if ($this->objectCache === null) {
            $this->objectCache = $this->getRecordsFromTable();
        }

        return $this->objectCache;
    }

    /**
     * Select all records from table
     *
     * @return array
     * @throws GuzzleException
     */
    private function getRecordsFromTable(): array
    {
        $columns = null;
        $query = null;

        // Get requested columns
        if ($this->getSetting('servicenow_columns') !== "") {
            $columns = str_replace(' ', '', $this->getSetting('servicenow_columns'));
        }

        // Get query parameters
        if ($this->getSetting('servicenow_query') !== "") {
            $query = $this->getSetting('servicenow_query');
        }

        // Set endpoint
        $endpoint = sprintf('%s?sysparm_display_value=true', $this->getSetting('servicenow_endpoint'));


        $auth = [
            'method' => $this->getSetting('servicenow_authmethod'),
            'token' => $this->getSetting('servicenow_token'),
            'username' => $this->getSetting('servicenow_username'),
            'password' => $this->getSetting('servicenow_password'),
            'proxy_type' => $this->getSetting('proxy_type'),
            'proxy_address' => $this->getSetting('proxy'),
            'proxy_user' => $this->getSetting('proxy_user'),
            'proxy_password' => $this->getSetting('proxy_pass'),
        ];

        $client = new Servicenow(
            $this->getSetting('servicenow_url'),
            self::CLIENT_TLS_VERIFY,
            $this->getSetting('servicenow_timeout') ?: self::CLIENT_TIMEOUT,
            $auth
        );

        $result = $client->request(
            $endpoint,
            [
                'query' => [
                    'sysparm_fields' => $columns,
                    'sysparm_query' => rawurldecode($query),
                ]
            ]
        );



        return json_decode($result)->result;
    }

    protected static function addProxy(QuickForm $form)
    {
        $form->addElement('select', 'proxy_type', [
            'label' => $form->translate('Proxy'),
            'description' => $form->translate(
                'In case your API is only reachable through a proxy, please'
                . ' choose it\'s protocol right here'
            ),
            'multiOptions' => $form->optionalEnum([
                'HTTP'   => $form->translate('HTTP proxy'),
                'SOCKS5' => $form->translate('SOCKS5 proxy'),
            ]),
            'class' => 'autosubmit'
        ]);

        $proxyType = $form->getSentOrObjectSetting('proxy_type');

        if ($proxyType) {
            $form->addElement('text', 'proxy', [
                'label' => $form->translate('Proxy Address'),
                'description' => $form->translate(
                    'Hostname, IP or <host>:<port>'
                ),
                'required' => true,
            ]);
            if ($proxyType === 'HTTP') {
                $form->addElement('text', 'proxy_user', [
                    'label'       => $form->translate('Proxy Username'),
                    'description' => $form->translate(
                        'In case your proxy requires authentication, please'
                        . ' configure this here'
                    ),
                ]);

                $form->addElement('storedPassword', 'proxy_pass', [
                    'label'    => $form->translate('Proxy Password'),
                    'required' => strlen((string) $form->getSentOrObjectSetting('proxy_user')) > 0
                ]);
            }
        }
    }
}
