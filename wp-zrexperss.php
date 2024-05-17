<?php
/*
Plugin Name: ZRExperss Colis API
Description: Sends POST request to Colis API after order completion in WooCommerce.
Version: 1.0
Author: ABDELDJABAR
*/




// Add admin menu
add_action( 'admin_menu', 'colis_api_admin_menu' );

function colis_api_admin_menu() {
    add_options_page( 'Colis API Settings', 'Colis API Settings', 'manage_options', 'colis-api-settings', 'colis_api_settings_page' );
}

// Settings page content
function colis_api_settings_page() {
    ?>
    <div class="wrap">
        <h2>Colis API Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'colis-api-settings-group' ); ?>
            <?php do_settings_sections( 'colis-api-settings-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Token</th>
                    <td><input type="text" name="colis_api_token" value="<?php echo esc_attr( get_option('colis_api_token') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Key</th>
                    <td><input type="text" name="colis_api_key" value="<?php echo esc_attr( get_option('colis_api_key') ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action( 'admin_init', 'colis_api_register_settings' );

function colis_api_register_settings() {
    register_setting( 'colis-api-settings-group', 'colis_api_token' );
    register_setting( 'colis-api-settings-group', 'colis_api_key' );
}




add_action( 'woocommerce_order_actions', 'add_custom_button_to_order_actions', 20, 2 );

function add_custom_button_to_order_actions( $actions, $order ) {
    // Add your custom button
    $actions['send_request_coli'] =__( 'Envoyer coli sur le site ZR express', 'woocommerce' ); 

    return $actions;
}

add_action( 'woocommerce_order_action_send_request_coli', 'send_request_coli' );
function send_request_coli( $order ) {

	// Get order ID
    $order_id = $order->get_id();

        // Get order object
        $order = wc_get_order( $order_id );

        // Get necessary order data
        $billing_data = $order->get_address();
        $order_total = $order->get_total();
        $items = $order->get_items();

        // Initialize shipping type to "A Domicile" by default
        $type_livraison = '0'; // "A Domicile"
        $shipping_name ='';

    $shipping_items = $order->get_items('shipping');

    // Loop through shipping items
    foreach ($shipping_items as $shipping_item_id => $shipping_item) {
        $shipping_method_title = $shipping_item->get_method_title();

            if ( $shipping_method_title === 'A Domicile' ) {
                $type_livraison = '0'; // "A Domicile"
            } else {
                $type_livraison = '1'; // "Bureau"
            }
            // Break the loop after processing the shipping item
            break;
        
    }
                // Prepare data for POST request
        $data = array(
            'Colis' => array(
                array(
                    'TypeLivraison' =>  $type_livraison,
                    'TypeColis' => '0',
                    'Confrimee' => '',
                    'Client' => $billing_data['first_name'] . ' ' . $billing_data['last_name'],
                    'MobileA' => $billing_data['phone'],
                    'MobileB' => '',
                    'Adresse' => $billing_data['address_1'] . ', ' . $billing_data['address_2'],
                    'IDWilaya' =>substr( $billing_data['state'], 3 ),
                    'Commune' => $billing_data['city'],
                    'Total' => $order_total,
                    'Note' => '',
                    'TProduit' => 'Literie',
                    'id_Externe' => $order_id,
                    'Source' => ''
                )
            )
        );

        // Send POST request
        $response = wp_remote_post( 'https://procolis.com/api_v1/add_colis', array(
            'headers' => array(
                'token' => get_option( 'colis_api_token' ), // Retrieve token from options
                'key' => get_option( 'colis_api_key' ), // Retrieve key from options
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode( $data ),
            'timeout'     => 50,
        ) );

        update_option('debug_liversion_1',$response);
        // Check if request was successful
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( "Failed to send POST request: $error_message" );
        } else {
            // Request was successful, you can handle response if needed
            $response_body = wp_remote_retrieve_body( $response );
          
            $response_data = json_decode($response_body, true);
            if ($response_data !== null) {
                // Access the "Colis" array and get the first element
                $colis = $response_data['Colis'][0];

                // Access the value of "MessageRetour"
                $message_retour = $colis['MessageRetour'];

                
                if ($message_retour === 'Good') {
                    $order->add_order_note('Order sent to ZR Express.');
                }
                } else {
                    // Handle JSON decoding error
                    wp_die("Error decoding JSON response");
                }
        }
        
}





// Adding custom status to admin order list bulk actions dropdown
function zr_action_send_request_coli( $actions ) {

    $actions['send_request_coli'] = __( 'Envoyer coli', 'woocommerce' );
     
    return $actions;
}
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'zr_action_send_request_coli', 20, 1 );

function handle_action_send_coli( $redirect_to, $doaction, $post_ids ) {
   
    if ( $doaction === 'send_request_coli' ) {
        foreach ( $post_ids as $post_id ) {
            bulk_send_request_coli($post_id);
        }

        
    }

    return $redirect_to;
}

// Hook into the filter. Replace 'my_bulk_action' with your action's name.
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'handle_action_send_coli', 10, 3 );


function bulk_send_request_coli( $order_id ) {



        // Get order object
        $order = wc_get_order( $order_id );

        // Get necessary order data
        $billing_data = $order->get_address();
        $order_total = $order->get_total();
        $items = $order->get_items();

        // Initialize shipping type to "A Domicile" by default
        $type_livraison = '0'; // "A Domicile"
        $shipping_name ='';

    $shipping_items = $order->get_items('shipping');

    // Loop through shipping items
    foreach ($shipping_items as $shipping_item_id => $shipping_item) {
        $shipping_method_title = $shipping_item->get_method_title();

            if ( $shipping_method_title === 'A Domicile' ) {
                $type_livraison = '0'; // "A Domicile"
            } else {
                $type_livraison = '1'; // "Bureau"
            }
            // Break the loop after processing the shipping item
            break;
        
    }
                // Prepare data for POST request
        $data = array(
            'Colis' => array(
                array(
                    'TypeLivraison' =>  $type_livraison,
                    'TypeColis' => '0',
                    'Confrimee' => '',
                    'Client' => $billing_data['first_name'] . ' ' . $billing_data['last_name'],
                    'MobileA' => $billing_data['phone'],
                    'MobileB' => '',
                    'Adresse' => $billing_data['address_1'] . ', ' . $billing_data['address_2'],
                    'IDWilaya' =>substr( $billing_data['state'], 3 ),
                    'Commune' => $billing_data['city'],
                    'Total' => $order_total,
                    'Note' => '',
                    'TProduit' => 'Literie',
                    'id_Externe' => $order_id,
                    'Source' => ''
                )
            )
        );

        // Send POST request
        $response = wp_remote_post( 'https://procolis.com/api_v1/add_colis', array(
            'headers' => array(
                'token' => get_option( 'colis_api_token' ), // Retrieve token from options
                'key' => get_option( 'colis_api_key' ), // Retrieve key from options
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode( $data ),
            'timeout'     => 50,
        ) );

        update_option('debug_liversion_1',$response);
        // Check if request was successful
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( "Failed to send POST request: $error_message" );
        } else {
            // Request was successful, you can handle response if needed
            $response_body = wp_remote_retrieve_body( $response );
          
            $response_data = json_decode($response_body, true);
            if ($response_data !== null) {
                // Access the "Colis" array and get the first element
                $colis = $response_data['Colis'][0];

                // Access the value of "MessageRetour"
                $message_retour = $colis['MessageRetour'];

                
                if ($message_retour === 'Good') {
                    $order->add_order_note('Order sent to ZR Express.');
                }
                } else {
                    // Handle JSON decoding error
                    wp_die("Error decoding JSON response");
                }
        }
        
}

?>