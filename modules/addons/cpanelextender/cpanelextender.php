<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Service\Service;

require_once ROOTDIR
    . DIRECTORY_SEPARATOR . 'includes'
    . DIRECTORY_SEPARATOR . 'hooks'
    . DIRECTORY_SEPARATOR . 'cpanelConference2015'
    . DIRECTORY_SEPARATOR . 'cpanel-xmlapi.php';

function cpanelextender_config() {
    $configarray = array(
        "name" => "cPanel Extender Addon",
        "description" => "This adds custom actions to the client area client details page.",
        "version" => "0.1-alpha",
        "author" => "WHMCS",
    );
    return $configarray;
}

function cpanelextender_clientarea($vars) {
    $whmcs = \App::self();

    $action = $whmcs->get_req_var('action');

    if ($action == 'addftpuser') {
        $serviceId = $whmcs->get_req_var('id');
        $ftpUsername = $whmcs->get_req_var('ftp_prefix');
        $ftpPassword = $whmcs->get_req_var('ftp_pw');

        echo cpanelextender_addFtpAccount($serviceId, $ftpUsername, $ftpPassword);
        exit;
    }

    // If we get here, log an error to the activity log and exit.
    logActivity("cPanel Extender Addon Module Client Area called for action $action and we did not know what to do.");
    exit;
}

function cpanelextender_addFtpAccount($serviceId, $ftpUsername, $ftpPassword) {

    try {
        $service = Service::findOrFail($serviceId);
    } catch (Exception $e) {
        logActivity('Exception caught when trying to load the Service Model:' . $e->getMessage());
        return json_encode(
            array(
                'message' => 'Unable to load service model for: ' . $serviceId,
                'success' => 0
            )
        );
    }

    $cPServer = cpanelextender_getCpanelAPIFromService($service);
    $cPServer->set_output('json');

    $args = array(
        'user' => $ftpUsername,
        'pass' => $ftpPassword
    );

    $result = $cPServer->api2_query($service->username, 'Ftp', 'addftp', $args);
    $result =  json_decode($result, true);
    $result = $result['cpanelresult'];

    if (array_key_exists('error', $result)) {
        $result['return']['reason'] = $result['error'];
    }

    $result['return']['result'] = $result['data'][0]['result'];

    return json_encode($result['return']);
}

function cpanelextender_getCpanelAPIFromService(Service $service)
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

    return $cpServer;
}
