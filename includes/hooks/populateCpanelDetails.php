<?php
/**
 * cP Conf 2015 Demo Hook Functions
 *
 * Please refer to the documentation @ http://docs.whmcs.com/Hooks for more
 * information. These hooks are commented out by default. Uncomment to use.
 *
 * @author WHMCS Limited <development@whmcs.com>
 * @copyright Copyright (c) WHMCS Limited 2005-2015
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Service\Service;
use WHMCS\View\Menu\Item;

require_once ROOTDIR
    . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'hooks'
    . DIRECTORY_SEPARATOR . 'cpanelConference2015'
    . DIRECTORY_SEPARATOR . 'cpanel-xmlapi.php';

/**
 * Get the current server load for an active cPanel product
 *
 * @param Service $service The product being viewed
 * @return SimpleXMLElement|null
 */
function getLoadAverageFromService(Service $service)
{
    // No need to do anything if the service is not active or suspended on the server.
    if (in_array($service->status, array('Pending', 'Terminated', 'Cancelled', 'Fraud'))) {
        return null;
    }

    // We only want to do this work on cPanel products, use other hooks for other modules.
    if ($service->product->module != 'cpanel') {
        return null;
    }

    // See http://docs.whmcs.com/Interacting_With_The_Database
    $server = Capsule::table('tblservers')
        ->where('id', $service->serverId)
        ->first();

    // See https://github.com/CpanelInc/xmlapi-php
    $cpServer = new xmlapi($server->ipaddress, $server->username, decrypt($server->password));

    // See https://documentation.cpanel.net/display/SDK/WHM+API+0+Functions+-+loadavg
    $loadAverage = $cpServer->loadavg();

    return $loadAverage;
}

/**
 * Add information about the system's load average to the template
 *
 * @param array $params The current template variables to display
 * @return array key value pairs of variables to add to the template
 */
function populateLoadAverageInProductDetailsPage($params)
{
    $serviceId = $params['serviceid'];

    // See http://docs.whmcs.com/classes/classes/WHMCS.Service.Service.html for details on this model
    /** @var Service $service */
    try {
        $service = Service::findOrFail($serviceId);
    } catch (Exception $e) {
        logActivity('Exception caught when trying to load the Service Model:' . $e->getMessage());
        return null;
    }

    $loadAverage = getLoadAverageFromService($service);

    // Simple conversion from SimpleXMLElement to array
    return array('loadAverage' => (array)$loadAverage);
}

/**
 * Add system load information in raw html to display on product details page
 *
 * @param array $params The key 'service' contains a WHMCS\Service\Service model
 * @return string html
 */
function populateLoadAverageInProductDetailsOutput($params)
{
    $service = $params['service'];
    $loadAverage = getLoadAverageFromService($service);

    $bodyHtml = <<<EOT
<div class="alert-info" role="alert">
    The server you are on has a current five minute load average of: {$loadAverage->five}
</div>
<br>
EOT;

    return $bodyHtml;
}

/**
 * Add load information to the Products/Services homepage panel
 *
 * @param Item $basePanel collection of homepage panels
 */
function populateLoadAverageInHomepagePanels(Item $basePanel)
{
    $servicesPanel = $basePanel->getChild('Active Products/Services');

    // If this is not populated at all we need to skip adding items.
    if (is_null($servicesPanel)) {
        return;
    }

    foreach ($servicesPanel->getChildren() as $serviceLink) {
        parse_str(parse_url($serviceLink->getUri(), PHP_URL_QUERY));

        /** @var int $id Created by parse_str() */
        /** @var Service $service */
        // See http://docs.whmcs.com/classes/classes/WHMCS.Service.Service.html for details on this model
        $service = Service::findOrFail($id);
        $loadAverage = getLoadAverageFromService($service);

        if ($loadAverage) {
            $label = $serviceLink->getLabel();
            $label .= "<br>Load average - One: {$loadAverage->one} ";
            $label .= "Five: {$loadAverage->five} ";
            $label .= "Fifteen: {$loadAverage->fifteen}";
            $serviceLink->setLabel($label);
        }
    }
}

// This registers our hooks to the relevant hook points
add_hook('ClientAreaProductDetailsPreModuleTemplate', 1, 'populateLoadAverageInProductDetailsPage');
add_hook('ClientAreaProductDetailsOutput', 1, 'populateLoadAverageInProductDetailsOutput');
add_hook('ClientAreaHomepagePanels', 1, 'populateLoadAverageInHomepagePanels');
