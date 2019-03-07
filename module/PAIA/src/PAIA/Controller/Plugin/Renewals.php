<?php
/**
 * VuFind Action Helper - Renewals Support Methods
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace PAIA\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Zend action helper to perform renewal-related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Renewals extends \VuFind\Controller\Plugin\Renewals
{
    /**
     * Process renewal requests.
     *
     * @param \Zend\Stdlib\Parameters $request Request object
     * @param \VuFind\ILS\Connection  $catalog ILS connection object
     * @param array                   $patron  Current logged in patron
     *
     * @return array                  The result of the renewal, an
     * associative array keyed by item ID (empty if no renewals performed)
     */
    public function processRenewals($request, $catalog, $patron)
    {
        // Pick IDs to renew based on which button was pressed:
        $all = $request->get('renewAll') || $request->get('renewSelectedAll');
        $selected = $request->get('renewSelected');
        if (!empty($all)) {
            $ids = $request->get('renewAllIDS');
        } elseif (!empty($selected)) {
            $ids = $request->get('selectAll')
                ? $request->get('selectAllIDS')
                : $request->get('renewSelectedIDS');
        } else {
            $ids = [];
        }

        // Retrieve the flashMessenger helper:
        $flashMsg = $this->getController()->flashMessenger();

        // If there is actually something to renew, attempt the renewal action:
        if (is_array($ids) && !empty($ids)) {
            $renewResult = $catalog->renewMyItems(
                ['details' => $ids, 'patron' => $patron]
            );
            if ($renewResult !== false) {
                // Assign Blocks to the Template
                if (isset($renewResult['blocks'])
                    && is_array($renewResult['blocks'])
                ) {
                    foreach ($renewResult['blocks'] as $block) {
                        $flashMsg->addMessage($block, 'info');
                    }
                }

                // Send back result details:
                return $renewResult['details'];
            } else {
                // System failure:
                $flashMsg->addMessage('renew_error', 'error');
            }
        } elseif (!empty($all) || !empty($selected)) {
            // Button was clicked but no items were selected:
            $flashMsg->addMessage('renew_empty_selection', 'error');
        }

        return [];
    }
}