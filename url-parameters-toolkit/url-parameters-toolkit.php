<?php

/**
 * Plugin Name:       URL  Paramaters ToolKit for SureCart
 * Description:       URL  Paramaters ToolKit for SureCart's plugin description
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Version:           1.34
 * Author:            Reallyusefulplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       url_paramaters_toolkit_for_sure
 * Website:           https://reallyusefulplugins.com
 * (C) Nathan Foley
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$plugin_prefix = 'URLPARAMATERSTOOLKITFORSURE';

// Extract the version number
$plugin_data = get_file_data(__FILE__, ['Version' => 'Version']);

// Plugin Constants
define($plugin_prefix . '_DIR', plugin_basename(__DIR__));
define($plugin_prefix . '_BASE', plugin_basename(__FILE__));
define($plugin_prefix . '_PATH', plugin_dir_path(__FILE__));
define($plugin_prefix . '_VER', $plugin_data['Version']);
define($plugin_prefix . '_CACHE_KEY', 'url_paramaters_toolkit_for_sure-cache-key-for-plugin');
define($plugin_prefix . '_REMOTE_URL', 'https://reallyusefulplugins.com/wp-content/uploads/downloads/641/info.json');

require constant($plugin_prefix . '_PATH') . 'inc/update.php';

new DPUpdateChecker(
    constant($plugin_prefix . '_BASE'),
    constant($plugin_prefix . '_VER'),
    constant($plugin_prefix . '_CACHE_KEY'),
    constant($plugin_prefix . '_REMOTE_URL'),
);

// Update Checker Above this Line 
// Exit if accessed directly.

/**
 * Add the URL Parameters settings page under Settings.
 */
add_action('admin_menu', 'rup_sc_url_params_admin_menu');
function rup_sc_url_params_admin_menu() {
    add_options_page(
        'Pricing Pairs',          // Page title
        'URL Parameters',         // Menu title
        'manage_options',         // Capability
        'sc-url-parms',           // Menu slug (updated)
        'rup_sc_url_params_admin_page' // Callback function (updated)
    );
}

/**
 * Admin page output and form processing with tabbed navigation.
 */
function rup_sc_url_params_admin_page() {
    // Retrieve current pricing pairs.
    $pairs = get_option('rup_sc_url_params_pairs', array());
    
    // Retrieve general settings.
    $token_key = get_option('rup_sc_url_params_price_token_key', 'price_token');
    $baseurl   = get_option('rup_sc_url_params_baseurl', home_url());
    
    // Retrieve choice settings.
    $enable_choice       = get_option('rup_sc_url_params_enable_choice', '0');
    $choice_param_key    = get_option('rup_sc_url_params_choice_param_key', 'pricechoice');
    $choice_console_logs = get_option('rup_sc_url_params_choice_console_logs', '0');

    // Process General Settings update.
    if ( isset($_POST['update_general_settings']) ) {
        $new_token_key = sanitize_text_field($_POST['token_key']);
        $new_baseurl   = esc_url_raw($_POST['baseurl']);
        update_option('rup_sc_url_params_price_token_key', $new_token_key);
        update_option('rup_sc_url_params_baseurl', $new_baseurl);
        $token_key = $new_token_key;
        $baseurl   = $new_baseurl;
        echo '<div class="updated"><p>General settings updated!</p></div>';
    }

    // Process Choice Settings update.
    if ( isset($_POST['update_choice_settings']) ) {
        $new_enable_choice       = isset($_POST['enable_choice']) ? '1' : '0';
        $new_choice_param_key    = sanitize_text_field($_POST['choice_param_key']);
        $new_choice_console_logs = isset($_POST['choice_console_logs']) ? '1' : '0';
        update_option('rup_sc_url_params_enable_choice', $new_enable_choice);
        update_option('rup_sc_url_params_choice_param_key', $new_choice_param_key);
        update_option('rup_sc_url_params_choice_console_logs', $new_choice_console_logs);
        $enable_choice       = $new_enable_choice;
        $choice_param_key    = $new_choice_param_key;
        $choice_console_logs = $new_choice_console_logs;
        echo '<div class="updated"><p>Choice settings updated!</p></div>';
    }

    // Process deletion if requested.
    if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['token']) ) {
        $token_to_delete = sanitize_text_field($_GET['token']);
        if ( isset($pairs[$token_to_delete]) ) {
            unset($pairs[$token_to_delete]);
            update_option('rup_sc_url_params_pairs', $pairs);
            echo '<div class="updated"><p>Pricing pair deleted!</p></div>';
        }
    }

    // Process updating an existing pair.
    if ( isset($_POST['update_pair']) ) {
        $original_token = sanitize_text_field($_POST['original_token']);
        $new_token      = sanitize_text_field($_POST['new_token']);
        $new_price      = floatval($_POST['new_price']);
        if ( isset($pairs[$original_token]) ) {
            unset($pairs[$original_token]);
        }
        if ( !empty($new_token) && $new_price > 0 ) {
            $pairs[$new_token] = $new_price;
            update_option('rup_sc_url_params_pairs', $pairs);
            echo '<div class="updated"><p>Pricing pair updated!</p></div>';
        }
    }

    // Process adding a new pair.
    if ( isset($_POST['add_pair']) ) {
        $new_token = sanitize_text_field($_POST['new_token']);
        $new_price = floatval($_POST['new_price']);
        if ( !empty($new_token) && $new_price > 0 ) {
            $pairs[$new_token] = $new_price;
            update_option('rup_sc_url_params_pairs', $pairs);
            echo '<div class="updated"><p>Pricing pair added!</p></div>';
        }
    }

    // Determine active tab.
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pairs';

    ?>
    <div class="wrap">
        <h1>URL Parameters for SureCart Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo admin_url('options-general.php?page=sc-url-parms&tab=pairs'); ?>" class="nav-tab <?php echo $active_tab == 'pairs' ? 'nav-tab-active' : ''; ?>">Name Your Price Settings</a>
            <a href="<?php echo admin_url('options-general.php?page=sc-url-parms&tab=choice'); ?>" class="nav-tab <?php echo $active_tab == 'choice' ? 'nav-tab-active' : ''; ?>">Pre-Selected Choice Settings</a>
        </h2>

        <?php if ( $active_tab == 'pairs' ) : ?>
            <!-- General Settings Section -->
            <h2>General Settings</h2>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="token_key">Price Token Parameter Key</label>
                        </th>
                        <td>
                            <input type="text" name="token_key" id="token_key" value="<?php echo esc_attr($token_key); ?>" />
                            <p class="description">This is the query parameter key that the Price JS and REST API will use. Default is "price_token".</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="baseurl">Base URL</label>
                        </th>
                        <td>
                            <input type="text" name="baseurl" id="baseurl" value="<?php echo esc_attr($baseurl); ?>" />
                            <p class="description">Enter your site's base URL (e.g. https://example.com). Defaults to your site's home URL.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Update General Settings', 'primary', 'update_general_settings'); ?>
            </form>

            <!-- Add New Pair Section -->
            <h2>Add New Pair</h2>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="new_token">Token</label>
                        </th>
                        <td>
                            <input type="text" name="new_token" id="new_token" value="" />
                            <button type="button" id="generate_random_token" class="button">Generate Random Token</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="new_price">Price</label>
                        </th>
                        <td>
                            <input type="number" step="0.01" name="new_price" id="new_price" value="" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Add Pricing Pair', 'primary', 'add_pair'); ?>
            </form>

            <!-- Existing Pairs Section -->
            <h2>Existing Pricing Pairs</h2>
            <?php if ( ! empty($pairs) ) : ?>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pairs as $token => $price ) : ?>
                            <tr>
                                <td><?php echo esc_html($token); ?></td>
                                <td><?php echo esc_html($price); ?></td>
                                <td>
                                    <a class="button button-secondary rup-sc-url-params-delete" href="<?php echo esc_url(admin_url('options-general.php?page=sc-url-parms&action=delete&token=' . urlencode($token))); ?>" onclick="return confirm('Are you sure you want to delete this pair?');">Delete</a>
                                    <button type="button" class="button button-secondary rup-sc-url-params-edit-button" data-token="<?php echo esc_attr($token); ?>" data-price="<?php echo esc_attr($price); ?>">Edit</button>
                                </td>
                            </tr>
                            <tr class="rup-sc-url-params-edit-row" id="rup-sc-url-params-edit-<?php echo esc_attr($token); ?>" style="display:none;">
                                <td colspan="3">
                                    <form method="post">
                                        <input type="hidden" name="original_token" value="<?php echo esc_attr($token); ?>" />
                                        <table>
                                            <tr>
                                                <td>
                                                    <label>Token: </label>
                                                    <input type="text" name="new_token" value="<?php echo esc_attr($token); ?>" />
                                                </td>
                                                <td>
                                                    <label>Price: </label>
                                                    <input type="number" step="0.01" name="new_price" value="<?php echo esc_attr($price); ?>" />
                                                </td>
                                                <td>
                                                    <input type="submit" name="update_pair" value="Update Pair" class="button button-primary" />
                                                    <button type="button" class="button rup-sc-url-params-cancel-edit" data-token="<?php echo esc_attr($token); ?>">Cancel</button>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No pricing pairs set yet.</p>
            <?php endif; ?>

        <?php elseif ( $active_tab == 'choice' ) : ?>

            <!-- Choice Parameter Settings Section -->
            <h2>Choice Parameter Settings</h2>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Choice Parameter</th>
                        <td>
                            <label for="enable_choice">
                                <input type="checkbox" name="enable_choice" id="enable_choice" value="1" <?php checked($enable_choice, '1'); ?> />
                                Enable automatic selection of a choice based on URL parameter.
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="choice_param_key">Choice Parameter Key</label>
                        </th>
                        <td>
                            <input type="text" name="choice_param_key" id="choice_param_key" value="<?php echo esc_attr($choice_param_key); ?>" />
                            <p class="description">This is the query parameter key used by the Choice JS. Default is "pricechoice".</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Console Logs</th>
                        <td>
                            <label for="choice_console_logs">
                                <input type="checkbox" name="choice_console_logs" id="choice_console_logs" value="1" <?php checked($choice_console_logs, '1'); ?> />
                                Allow console logs for debugging the choice selection.
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Update Choice Settings', 'primary', 'update_choice_settings'); ?>
            </form>

        <?php endif; ?>

    </div>
    <script>
        // Generate a random token when button is clicked.
        document.getElementById('generate_random_token').addEventListener('click', function(e) {
            e.preventDefault();
            const tokenField = document.getElementById('new_token');
            tokenField.value = Math.random().toString(36).substring(2, 12);
        });

        // Show inline edit form.
        document.querySelectorAll('.rup-sc-url-params-edit-button').forEach(function(button) {
            button.addEventListener('click', function() {
                const token = this.getAttribute('data-token');
                document.getElementById('rup-sc-url-params-edit-' + token).style.display = 'table-row';
            });
        });

        // Hide inline edit form.
        document.querySelectorAll('.rup-sc-url-params-cancel-edit').forEach(function(button) {
            button.addEventListener('click', function() {
                const token = this.getAttribute('data-token');
                document.getElementById('rup-sc-url-params-edit-' + token).style.display = 'none';
            });
        });

        // Clean up the URL.
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            if (params.has('page')) {
                const pageParam = params.get('page');
                const newUrl = window.location.pathname + '?page=' + encodeURIComponent(pageParam);
                history.replaceState(null, document.title, newUrl);
            }
        });
    </script>
    <?php
}

/**
 * Register REST endpoint.
 */
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/price', [
        'methods'  => 'GET',
        'callback' => 'rup_sc_url_params_get_price',
    ]);
});
function rup_sc_url_params_get_price(WP_REST_Request $request) {
    $pairs = get_option('rup_sc_url_params_pairs', []);
    $token_key = get_option('rup_sc_url_params_price_token_key', 'price_token');
    $token = $request->get_param($token_key);
    if ( isset($pairs[$token]) ) {
        return rest_ensure_response(['price' => $pairs[$token]]);
    } else {
        return new WP_Error('invalid_token', 'Invalid token provided', ['status' => 400]);
    }
}

/**
 * Enqueue inline script for retrieving and setting the custom price.
 */
function rup_sc_url_params_enqueue_custom_inline_script() {
    wp_register_script( 'rup-sc-url-params-inline-script', '' );
    wp_enqueue_script( 'rup-sc-url-params-inline-script' );
    
    $token_key = get_option('rup_sc_url_params_price_token_key', 'price_token');
    $baseurl   = get_option('rup_sc_url_params_baseurl', home_url());
    
    $inline_script = "
    document.addEventListener('DOMContentLoaded', function() {
      const tokenParam = '" . esc_js($token_key) . "';
      const params = new URLSearchParams(window.location.search);
      const token = params.get(tokenParam);
      if (!token) return;
      
      fetch('" . esc_js($baseurl) . "/wp-json/custom/v1/price?' + tokenParam + '=' + encodeURIComponent(token))
        .then(response => response.json())
        .then(data => {
          if (data && data.price) {
            const priceValue = parseFloat(data.price);
            if (!isNaN(priceValue)) {
              const fixedPrice = priceValue.toFixed(2);
              const inputField = document.getElementById('sc-product-custom-amount');
              if (inputField) {
                inputField.value = fixedPrice;
                inputField.readOnly = true;
                function updatePrice() {
                  if (inputField.value !== fixedPrice) {
                    inputField.value = fixedPrice;
                    inputField.dispatchEvent(new Event('input', { bubbles: true }));
                  }
                }
                updatePrice();
                setInterval(updatePrice, 200);
              }
            }
          } else {
            console.error('Invalid token or no price returned.');
          }
        })
        .catch(error => {
          console.error('Error fetching price:', error);
        });
    });
    ";
    wp_add_inline_script( 'rup-sc-url-params-inline-script', $inline_script );
}
add_action( 'wp_enqueue_scripts', 'rup_sc_url_params_enqueue_custom_inline_script' );

/**
 * Conditionally enqueue the inline choice script if enabled.
 */
function rup_sc_url_params_enqueue_custom_choice_inline_script() {
    if ( get_option('rup_sc_url_params_enable_choice', '0') !== '1' ) {
        return;
    }
    
    wp_register_script( 'rup-sc-url-params-inline-choice-script', '' );
    wp_enqueue_script( 'rup-sc-url-params-inline-choice-script' );
    
    $choice_param_key = get_option('rup_sc_url_params_choice_param_key', 'pricechoice');
    $choice_console_logs = get_option('rup_sc_url_params_choice_console_logs', '0') === '1' ? 'true' : 'false';
    
    $inline_choice_script = "
    document.addEventListener('DOMContentLoaded', function() {
      const params = new URLSearchParams(window.location.search);
      const choiceParam = params.get('" . esc_js($choice_param_key) . "');
      if (!choiceParam) return;
      const targetChoice = choiceParam.trim().toLowerCase();
      var loggingEnabled = " . $choice_console_logs . ";
      function log() { if (loggingEnabled) console.log.apply(console, arguments); }
      
      log('Looking for price choice:', targetChoice);
      
      function selectMatchingChoice() {
        const choices = document.querySelectorAll('.sc-choice');
        let matched = null;
        choices.forEach(choice => {
          const nameElem = choice.querySelector('.wp-block-surecart-price-name');
          if (nameElem && nameElem.textContent.trim().toLowerCase() === targetChoice) {
            matched = choice;
          }
        });
        return matched;
      }
      
      function isChoiceSelected(choice) {
        return (choice.getAttribute('aria-checked') === 'true' ||
                choice.classList.contains('sc-choice--checked'));
      }
      
      function trySelectChoice() {
        const match = selectMatchingChoice();
        if (match) {
          log('Match found for:', targetChoice);
          match.click();
          setTimeout(() => {
            if (!isChoiceSelected(match)) {
              log('Option not registered as selected; clicking again.');
              match.click();
            } else {
              log(targetChoice, 'has been successfully selected.');
            }
          }, 300);
        } else {
          log('Matching option not yet available, retrying...');
          setTimeout(trySelectChoice, 200);
        }
      }
      
      window.addEventListener('load', trySelectChoice);
    });
    ";
    wp_add_inline_script( 'rup-sc-url-params-inline-choice-script', $inline_choice_script );
}
add_action( 'wp_enqueue_scripts', 'rup_sc_url_params_enqueue_custom_choice_inline_script' );