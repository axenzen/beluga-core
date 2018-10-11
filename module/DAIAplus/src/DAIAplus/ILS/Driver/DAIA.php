<?php
/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 * Relaunch of the daia driver developed by Oliver Goldschmidt.
 *
 * PHP version 7
 *
 * Copyright (C) Jochen Lienhard 2014.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace DAIAplus\ILS\Driver;

use DOMDocument;
use VuFind\Exception\ILS as ILSException;
use VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface;
use Zend\Log\LoggerAwareInterface as LoggerAwareInterface;

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class DAIA extends \VuFind\ILS\Driver\DAIA
{
    /**
     * API key needed for DAIA+ Service
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (isset($this->config['DAIA']['baseUrl'])) {
            $this->baseUrl = $this->config['DAIA']['baseUrl'];
        } elseif (isset($this->config['Global']['baseUrl'])) {
            throw new ILSException(
                'Deprecated [Global] section in DAIA.ini present, but no [DAIA] ' .
                'section found: please update DAIA.ini (cf. config/vufind/DAIA.ini).'
            );
        } else {
            throw new ILSException('DAIA/baseUrl configuration needs to be set.');
        }
        if (isset($this->config['DAIA']['daiaplus_api_key'])) {
            $this->apiKey = $this->config['DAIA']['daiaplus_api_key'];
        } else {
            throw new ILSException('DAIA/daiaplus_api_key configuration needs to be set.');
        }
        // use DAIA specific timeout setting for http requests if configured
        if ((isset($this->config['DAIA']['timeout']))) {
            $this->daiaTimeout = $this->config['DAIA']['timeout'];
        }
        if (isset($this->config['DAIA']['daiaResponseFormat'])) {
            $this->daiaResponseFormat = strtolower(
                $this->config['DAIA']['daiaResponseFormat']
            );
        } else {
            $this->debug('No daiaResponseFormat setting found, using default: xml');
            $this->daiaResponseFormat = 'xml';
        }
        if (isset($this->config['DAIA']['daiaIdPrefix'])) {
            $this->daiaIdPrefix = $this->config['DAIA']['daiaIdPrefix'];
        } else {
            $this->debug('No daiaIdPrefix setting found, using default: ppn:');
            $this->daiaIdPrefix = 'ppn:';
        }
        if (isset($this->config['DAIA']['multiQuery'])) {
            $this->multiQuery = $this->config['DAIA']['multiQuery'];
        } else {
            $this->debug('No multiQuery setting found, using default: false');
        }
        if (isset($this->config['DAIA']['daiaContentTypes'])) {
            $this->contentTypesResponse = $this->config['DAIA']['daiaContentTypes'];
        } else {
            $this->debug('No ContentTypes for response defined. Accepting any.');
        }
        if (isset($this->config['DAIA']['daiaCache'])) {
            $this->daiaCacheEnabled = $this->config['DAIA']['daiaCache'];
        } else {
            $this->debug('Caching not enabled, disabling it by default.');
        }
        if (isset($this->config['General'])
            && isset($this->config['General']['cacheLifetime'])
        ) {
            $this->cacheLifetime = $this->config['General']['cacheLifetime'];
        } else {
            $this->debug(
                'Cache lifetime not set, using VuFind\ILS\Driver\AbstractBase ' .
                'default value.'
            );
        }
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $id id for query in daia
     *
     * @return xml or json object
     * @throws ILSException
     */
    protected function doHTTPRequest($id)
    {
        $http_headers = [
            'Content-type: ' . $this->contentTypesRequest[$this->daiaResponseFormat],
            'Accept: ' . $this->contentTypesRequest[$this->daiaResponseFormat],
        ];

        $params = [
            'apikey' => $this->apiKey,
            //'id' => $id,
            'format' => $this->daiaResponseFormat,
            'language' => 'de',
        ];

        try {
            $result = $this->httpService->get(
                $this->baseUrl.$id,
                $params, $this->daiaTimeout, $http_headers
            );
        } catch (\Exception $e) {
            throw new ILSException(
                'HTTP request exited with Exception ' . $e->getMessage() .
                ' for record: ' . $id
            );
        }

        if (!$result->isSuccess()) {
            throw new ILSException(
                'HTTP status ' . $result->getStatusCode() .
                ' received, retrieving availability information for record: ' . $id
            );
        }

        // check if result matches daiaResponseFormat
        if ($this->contentTypesResponse != null) {
            if ($this->contentTypesResponse[$this->daiaResponseFormat]) {
                $contentTypesResponse = array_map(
                    'trim',
                    explode(
                        ',',
                        $this->contentTypesResponse[$this->daiaResponseFormat]
                    )
                );
                list($responseMediaType) = array_pad(
                    explode(
                        ';',
                        $result->getHeaders()->get('ContentType')->getFieldValue(),
                        2
                    ),
                    2,
                    null
                ); // workaround to avoid notices if encoding is not set in header
                if (!in_array(trim($responseMediaType), $contentTypesResponse)) {
                    throw new ILSException(
                        'DAIA-ResponseFormat not supported. Received: ' .
                        $responseMediaType . ' - ' .
                        'Expected: ' .
                        $this->contentTypesResponse[$this->daiaResponseFormat]
                    );
                }
            }
        }

        return $result->getBody();
    }

    /**
     * Parse an array with DAIA status information.
     *
     * @param string $id        Record id for the DAIA array.
     * @param array  $daiaArray Array with raw DAIA status information.
     *
     * @return array            Array with VuFind compatible status information.
     */
    protected function parseDaiaArray($id, $daiaArray)
    {
        $doc_id = null;
        $doc_href = null;
        if (isset($daiaArray['id'])) {
            $doc_id = $daiaArray['id'];
        }
        if (isset($daiaArray['href'])) {
            // url of the document (not needed for VuFind)
            $doc_href = $daiaArray['href'];
        }
        if (isset($daiaArray['message'])) {
            // log messages for debugging
            $this->logMessages($daiaArray['message'], 'document');
        }
        // if one or more items exist, iterate and build result-item
        if (isset($daiaArray['item']) && is_array($daiaArray['item'])) {
            $number = 0;
            foreach ($daiaArray['item'] as $item) {
                $result_item = [];
                $result_item['id'] = $id;
                // custom DAIA field
                $result_item['doc_id'] = $doc_id;
                $result_item['item_id'] = $item['id'];
                // custom DAIA field used in getHoldLink()
                $result_item['ilslink']
                    = ($item['href'] ?? $doc_href);
                // count items
                $number++;
                $result_item['number'] = $this->getItemNumber($item, $number);
                // set default value for barcode
                $result_item['barcode'] = $this->getItemBarcode($item);
                // set default value for reserve
                $result_item['reserve'] = $this->getItemReserveStatus($item);
                // get callnumber
                $result_item['callnumber'] = $this->getItemCallnumber($item);
                // get location
                $result_item['location'] = $this->getItemDepartment($item);
                // custom DAIA field
                $result_item['locationid'] = $this->getItemDepartmentId($item);
                // get location link
                $result_item['locationhref'] = $this->getItemDepartmentLink($item);
                // custom DAIA field
                $result_item['storage'] = $this->getItemStorage($item);
                // custom DAIA field
                $result_item['storageid'] = $this->getItemStorageId($item);
                // custom DAIA field
                $result_item['storagehref'] = $this->getItemStorageLink($item);
                // status and availability will be calculated in own function
                $result_item = $this->getItemStatus($item) + $result_item;
                // add result_item to the result array

                // keep DAIA+ data
                if (isset($item['daiaplus'])) {
                    $result_item['daiaplus'] = $item['daiaplus'];
                }

                $result[] = $result_item;
            } // end iteration on item
        }

        return $result;
    }

    /**
     * Parse a DAIA document depending on its type.
     *
     * Parse a DAIA document depending on its type and return a VuFind
     * compatible array of status information.
     * Supported types are:
     *      - array (for JSON results)
     *
     * @param string $id      Record Id corresponding to the DAIA document
     * @param mixed  $daiaDoc The DAIA document, only array is supported
     *
     * @return array An array with status information for the record
     * @throws ILSException
     */
    protected function parseDaiaDoc($id, $daiaDoc)
    {
        if (is_array($daiaDoc)) {
            return $this->parseDaiaArray($id, $daiaDoc);
        } else {
            throw new ILSException(
                'Unsupported document type (did not match Array or DOMNode).'
            );
        }
    }
}
