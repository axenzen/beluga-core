<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace DependentWorks\AjaxHandler;

//use DependentWorks\DependentWorks;
use VuFind\AjaxHandler\AbstractBase;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\Stdlib\Parameters;
use Zend\Config\Config;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class GetDependentWorks extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * ZF configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Request
     *
     * @var Request
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param array             $config   ZF configuration
     * @param Request           $request  HTTP request
     */
    public function __construct(Config $config, ResultsManager $resultsManager)
    {
        $this->config = $config->toArray();
        $this->resultsManager = $resultsManager;
    }


    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $limit = $this->config['Global']['limit'] ?? 1;
        $sortFlag = $this->config['Global']['sort'] ?? SORT_REGULAR;
        $switchToRegularSearch = ($this->config['Global']['switch_to_regular_search'] == 'y');

        $ppn = $params->fromQuery('ppn');
        if (empty($ppn)) {
            return $this->formatResponse([]);
        }
        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $results = $this->resultsManager->get($backend);
        $results->getOptions()->setLimitOptions([$limit, $limit]);
        $paramsObj = $results->getParams();
        $paramsObj->initFromRequest(new Parameters(['lookfor' => 'hierarchy_top_id:'.$ppn.' -id:'.$ppn]));

        $records = $results->getResults();
        $resultTotal = $results->getResultTotal();

        if ($switchToRegularSearch && $resultTotal > $limit) {
            $resultString = $resultTotal . ' ' . $this->translate('results') . ': ' . $this->translate('show all');
            return $this->formatResponse([['resultString' => (string) $resultString]]);
        }

        $data = [];
        foreach ($records as $i => $record) {
            $dependentWorksData = $record->getMarcData('DependentWorksData');
            $title = $part = $date = $sort = '';
            $sort = $i; // Keep this default setting outside inner foreach loop, otherwise $sort gets reset to $i even if value of $dependentWorksDate['sort']['data'][0] was previously set.
            foreach ($dependentWorksData as $dependentWorksDate) {
                if (empty($title) && !empty($dependentWorksDate['title']['data'][0])) {
                    $title = $dependentWorksDate['title']['data'][0];
                }
                if (empty($part) && !empty($dependentWorksDate['part']['data'][0])) {
                    $part = $dependentWorksDate['part']['data'][0];
                }
                if (!empty($dependentWorksDate['date']['data'][0])) {
                    $date = $dependentWorksDate['date']['data'][0];
                }
                if (!empty($dependentWorksDate['sort']['data'][0])) {
                    $sort = $dependentWorksDate['sort']['data'][0];
                }
            }
            if (empty($sort)) {
                $sort = $i;
            }
            $prefix = (empty($date)) ? $part . '. ' : $part . ', ' . $date . '. ';
            $data[$sort] = ['id' => $record->getUniqueID(),
                            'prefix' => $prefix,
                            'title' => $title,
                            'part' => $part,
                            'date' => $date];
        }
        krsort($data, $sortFlag);
        return $this->formatResponse(array_values($data));
    }
} 
