<?php

/**
 * Prints the inventory object out as json.
 *
 * @TODO: Access control!
 */
function aegir_ansible_inventory_endpoint() {

    $inventory = aegir_ansible_inventory_data();
    print json_encode($inventory, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Returns an "inventory" object from our hostmaster.
 * @return stdClass
 */
function aegir_ansible_inventory_data() {
    // Build attributes object
    $inventory = new stdClass;
    $inventory->aegir_servers->hosts = array();
    $inventory->_meta->hostvars = new stdClass();

    // Get all server nodes.
    $sql = "SELECT nid FROM node
      WHERE type = 'server' AND status = 1";
    $server_nids = db_query($sql)->fetchCol();
    $server_nodes = node_load_multiple($server_nids);

    foreach ($server_nodes as $server_node) {
        // Add host to inventory.
        $inventory->aegir_servers->hosts[] = $server_node->title;

        // Server Variables
        // These variables are applied just to that host.
        $inventory->{$server_node->title}->hosts[] = $server_node->title;
        $inventory->{$server_node->title}->vars = $server_node->ansible_vars;

            // The variable 'ansible_user' maybe used to force ansible to connect via this user.
        // This is disabled so that our ansible runner can connect as root via the command line.
        // If this variable is set, the `-u root` command line option is ignored.
        // $inventory->{$server_node->title}->vars['ansible_user'] = 'aegir';

        // Add a "group" for each service type.
        foreach ($server_node->services as $service => $service_data) {

          // Add to "service" group ("http", "db")
          $inventory->{$service}->hosts[] = $server_node->title;

          // Add to "service type" group ("apache", "nginx", "mysql")
          $inventory->{$service_data->type}->hosts[] = $server_node->title;
        }
    }

    return $inventory;
}