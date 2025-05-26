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
                'label' => 'ServiceNow CMDB Table Endpoint',
                'required' => true,
                'description' => 'API endpoint to fetch objects from (e.g.: api/now/table/cmdb_ci_computer)',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_username',
            [
                'label' => 'ServiceNow API Username',
                'required' => true,
                'description' => 'Username to authenticate at the ServiceNow API',
            ]
        );

        $form->addElement(
            'password',
            'servicenow_password',
            [
                'label' => 'ServiceNow API Password',
                'required' => true,
                'renderPassword' => true,
                'description' => 'Password to authenticate at the ServiceNow API',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_timeout',
            [
                'label' => 'ServiceNow API Timeout',
                'required' => false,
                'description' => 'Timeout in seconds used to query data from ServiceNow. Default is 20.',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_columns',
            [
                'label' => 'ServiceNow Columns',
                'required' => false,
                'description' => 'Comma separated list of columns to fetch. Leave empty to fetch all columns.',
            ]
        );

        $form->addElement(
            'text',
            'servicenow_query',
            [
                'label' => 'ServiceNow Query',
                'required' => false,
                'description' => 'Query to filter records. Leave empty to fetch all records. '
                    . 'The query filter follows the official query syntax from ServiceNow: '
                    . 'https://www.servicenow.com/docs/bundle/yokohama-platform-user-interface/page/use/using-lists/concept/c_EncodedQueryStrings.html',
            ]
        );
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

        $client = new Servicenow(
            $this->getSetting('servicenow_url'),
            $this->getSetting('servicenow_username'),
            $this->getSetting('servicenow_password'),
            self::CLIENT_TLS_VERIFY,
            $this->getSetting('servicenow_timeout') ?: self::CLIENT_TIMEOUT,
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
}
