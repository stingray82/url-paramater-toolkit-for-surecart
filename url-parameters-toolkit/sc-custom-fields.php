<?php
/**
 * File: sc-custom-fields.php
 * Description: Adds extra functionality to the URL Parameters Toolkit for SureCart:
 *   - Appends a "Custom Fields" tab to the admin navigation.
 *   - Provides an admin UI for managing URL parameter → SC-Input name mappings.
 *     (The admin only enters the SC-Input's name attribute value; the plugin builds a valid selector.)
 *   - Adds admin inline JavaScript that fixes edit/cancel buttons, repositions the custom fields content,
 *     and cleans up the URL.
 *   - Enqueues front‑end inline JavaScript that maps URL parameters to custom fields,
 *     including special handling for SureCart <sc-input> components.
 *   - Enqueues a custom stylesheet (fields.css) located in inc/css/fields.css.
 * Author: Nathan Foley / ReallyUsefulPlugins.com
 * Version: 1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Enqueue custom stylesheet from inc/css/fields.css */
function sccf_enqueue_styles() {
    wp_enqueue_style( 'fields-css', plugin_dir_url( __FILE__ ) . 'inc/css/fields.css', array(), '1.0' );
}
add_action( 'wp_enqueue_scripts', 'sccf_enqueue_styles' );

/**
 * ADMIN INLINE SCRIPT
 * This script runs on the toolkit settings page (page=sc-url-parms) and:
 * - Safely attaches the random-token generator.
 * - Enables inline editing for pricing pairs and custom field mappings.
 * - Appends a new "Custom Fields" tab link to the nav-tab wrapper.
 * - If the active tab is "custom_fields", repositions the custom fields content container
 *   so that it appears in the same area as the other tab contents.
 * - Cleans up the URL (preserving only 'page' and 'tab' parameters).
 */
function sccf_admin_inline_script() {
    if ( isset($_GET['page']) && $_GET['page'] === 'sc-url-parms' ) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Safely attach to the "Generate Random Token" button.
            const randomTokenBtn = document.getElementById('generate_random_token');
            if (randomTokenBtn) {
                randomTokenBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tokenField = document.getElementById('new_token');
                    if (tokenField) {
                        tokenField.value = Math.random().toString(36).substring(2, 12);
                    }
                });
            }

            console.log("[SC Custom Fields] Admin inline script loaded.");

            // Inline editing for pricing pairs.
            document.querySelectorAll('.rup-sc-url-params-edit-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    const token = this.getAttribute('data-token');
                    const row = document.getElementById('rup-sc-url-params-edit-' + token);
                    if (row) {
                        row.style.display = 'table-row';
                    }
                });
            });
            document.querySelectorAll('.rup-sc-url-params-cancel-edit').forEach(function(button) {
                button.addEventListener('click', function() {
                    const token = this.getAttribute('data-token');
                    const row = document.getElementById('rup-sc-url-params-edit-' + token);
                    if (row) {
                        row.style.display = 'none';
                    }
                });
            });

            // Inline editing for custom field mappings.
            document.querySelectorAll('.custom-field-edit-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    const param = this.getAttribute('data-param');
                    const row = document.getElementById('custom-field-edit-' + param);
                    if (row) {
                        row.style.display = 'table-row';
                    }
                });
            });
            document.querySelectorAll('.custom-field-cancel-edit').forEach(function(button) {
                button.addEventListener('click', function() {
                    const param = this.getAttribute('data-param');
                    const row = document.getElementById('custom-field-edit-' + param);
                    if (row) {
                        row.style.display = 'none';
                    }
                });
            });

            // Append a "Custom Fields" tab if not already present.
            const navWrapper = document.querySelector('.nav-tab-wrapper');
            if (navWrapper) {
                let found = false;
                navWrapper.querySelectorAll('a.nav-tab').forEach(function(link) {
                    if (link.textContent.trim() === 'Custom Fields') {
                        found = true;
                    }
                });
                if (!found) {
                    const customTab = document.createElement('a');
                    customTab.href = window.location.pathname + '?page=sc-url-parms&tab=custom_fields';
                    customTab.classList.add('nav-tab');
                    customTab.textContent = 'Custom Fields';
                    navWrapper.appendChild(customTab);
                }
            }

            // If the custom_fields tab is active, reposition its content.
            const params = new URLSearchParams(window.location.search);
            if (params.get('tab') === 'custom_fields') {
                // Assume the main admin page has a container with class "wrap"
                // and the nav tabs are inside an element with class "nav-tab-wrapper".
                const wrap = document.querySelector('.wrap');
                const navWrapper = document.querySelector('.nav-tab-wrapper');
                const customContent = document.getElementById('custom-fields-tab-content');
                if (wrap && navWrapper && customContent) {
                    // Insert our custom fields content immediately after the nav tabs.
                    navWrapper.parentNode.insertBefore(customContent, navWrapper.nextSibling);
                }
            }

            // Clean up the URL by preserving only 'page' and 'tab' parameters.
            if (params.has('page')) {
                const pageParam = params.get('page');
                const tabParam  = params.get('tab');
                const newParams = new URLSearchParams();
                newParams.set('page', pageParam);
                if (tabParam) {
                    newParams.set('tab', tabParam);
                }
                const newUrl = window.location.pathname + '?' + newParams.toString();
                history.replaceState(null, document.title, newUrl);
            }
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'sccf_admin_inline_script');

/**
 * RENDER ADMIN UI FOR CUSTOM FIELDS TAB
 * When the URL includes ?page=sc-url-parms&tab=custom_fields, this outputs the custom field mapping management UI.
 * Note: Instead of asking for a full CSS selector, we now ask only for the SC-Input's name attribute value.
 * The output is wrapped in a container with id "custom-fields-tab-content".
 */
function sccf_render_custom_fields_admin_ui() {
    if ( isset($_GET['page']) && $_GET['page'] === 'sc-url-parms' && isset($_GET['tab']) && $_GET['tab'] === 'custom_fields' ) {

        // Process adding a new mapping.
        if ( isset($_POST['add_custom_field']) ) {
            $new_param = sanitize_text_field($_POST['new_param']);
            // Capture only the SC-Input name attribute value.
            $new_field_name = sanitize_text_field($_POST['new_field_selector']);
            $custom_mappings = get_option('rup_sc_custom_field_mappings', array());
            if ( !empty($new_param) && !empty($new_field_name) ) {
                $custom_mappings[$new_param] = $new_field_name;
                update_option('rup_sc_custom_field_mappings', $custom_mappings);
                echo '<div class="updated"><p>Custom field mapping added!</p></div>';
            }
        }
        // Process deletion.
        if ( isset($_GET['action']) && $_GET['action'] === 'delete_custom_field' && isset($_GET['param_key']) ) {
            $param_key_to_delete = sanitize_text_field($_GET['param_key']);
            $custom_mappings = get_option('rup_sc_custom_field_mappings', array());
            if ( isset($custom_mappings[$param_key_to_delete]) ) {
                unset($custom_mappings[$param_key_to_delete]);
                update_option('rup_sc_custom_field_mappings', $custom_mappings);
                echo '<div class="updated"><p>Custom field mapping deleted!</p></div>';
            }
        }
        // Process updating an existing mapping.
        if ( isset($_POST['update_custom_field']) ) {
            $original_param = sanitize_text_field($_POST['original_param']);
            $new_param = sanitize_text_field($_POST['new_param']);
            $new_field_name = sanitize_text_field($_POST['new_field_selector']);
            $custom_mappings = get_option('rup_sc_custom_field_mappings', array());
            if ( isset($custom_mappings[$original_param]) ) {
                unset($custom_mappings[$original_param]);
            }
            if ( !empty($new_param) && !empty($new_field_name) ) {
                $custom_mappings[$new_param] = $new_field_name;
                update_option('rup_sc_custom_field_mappings', $custom_mappings);
                echo '<div class="updated"><p>Custom field mapping updated!</p></div>';
            }
        }
        ?>
        <div id="custom-fields-tab-content" style="display:block; margin-top:20px;">
            <h1>Custom Field Mapping Settings</h1>
            <p>Map a URL parameter key to an SC-Input’s <strong>name</strong> attribute value.
                The plugin will build a valid selector (<code>sc-input[name="Custom Item"]</code>) for you.</p>
            
            <!-- Form to add a new mapping -->
            <h3>Add New Mapping</h3>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="new_param">URL Parameter Key</label></th>
                        <td><input type="text" name="new_param" id="new_param" value="" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_field_selector">SC-Input Name Attribute</label></th>
                        <td>
                            <input type="text" name="new_field_selector" id="new_field_selector" value="" />
                            <p class="description">For example: Custom Item (the plugin will use <code>sc-input[name="Custom Item"]</code>).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Add Custom Field Mapping', 'primary', 'add_custom_field'); ?>
            </form>
            
            <!-- Existing mappings listing -->
            <h3>Existing Mappings</h3>
            <?php
            $custom_mappings = get_option('rup_sc_custom_field_mappings', array());
            if ( ! empty($custom_mappings) ) :
            ?>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th>URL Parameter Key</th>
                            <th>SC-Input Name Attribute</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $custom_mappings as $param_key => $field_name ) : ?>
                            <tr>
                                <td><?php echo esc_html($param_key); ?></td>
                                <td><?php echo esc_html($field_name); ?></td>
                                <td>
                                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('options-general.php?page=sc-url-parms&tab=custom_fields&action=delete_custom_field&param_key=' . urlencode($param_key))); ?>" onclick="return confirm('Are you sure you want to delete this mapping?');">Delete</a>
                                    <button type="button" class="button button-secondary custom-field-edit-button" data-param="<?php echo esc_attr($param_key); ?>">Edit</button>
                                </td>
                            </tr>
                            <tr class="custom-field-edit-row" id="custom-field-edit-<?php echo esc_attr($param_key); ?>" style="display:none;">
                                <td colspan="3">
                                    <form method="post">
                                        <input type="hidden" name="original_param" value="<?php echo esc_attr($param_key); ?>" />
                                        <table>
                                            <tr>
                                                <td>
                                                    <label>URL Parameter Key:</label>
                                                    <input type="text" name="new_param" value="<?php echo esc_attr($param_key); ?>" />
                                                </td>
                                                <td>
                                                    <label>SC-Input Name Attribute:</label>
                                                    <input type="text" name="new_field_selector" value="<?php echo esc_attr($field_name); ?>" />
                                                </td>
                                                <td>
                                                    <input type="submit" name="update_custom_field" value="Update Mapping" class="button button-primary" />
                                                    <button type="button" class="button custom-field-cancel-edit" data-param="<?php echo esc_attr($param_key); ?>">Cancel</button>
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
                <p>No custom field mappings set yet.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
add_action('admin_notices', 'sccf_render_custom_fields_admin_ui');

/**
 * FRONT-END INLINE SCRIPT
 * This script (enqueued via wp_footer) loops through each custom field mapping.
 * For each mapping, it builds a valid CSS selector (sc-input[name="VALUE"]) using the admin-supplied SC-Input name attribute,
 * and if the URL contains the corresponding parameter, it sets the field’s value.
 */
function sccf_enqueue_custom_fields_script() {
    $custom_mappings = get_option('rup_sc_custom_field_mappings', array());
    if ( empty($custom_mappings) ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("[SC Custom Fields] Front-end custom fields script loaded.");
        const params = new URLSearchParams(window.location.search);
        <?php foreach ($custom_mappings as $param_key => $field_name) : 
            $js_param_key = esc_js($param_key);
            $js_field_name = esc_js($field_name);
        ?>
        if (params.has('<?php echo $js_param_key; ?>')) {
            const fieldValue = params.get('<?php echo $js_param_key; ?>');
            // Build the selector automatically.
            const selector = 'sc-input[name="<?php echo $js_field_name; ?>"]';
            let field;
            try {
                field = document.querySelector(selector);
            } catch (err) {
                console.error('Invalid selector generated:', selector, err);
                return;
            }
            if (field) {
                if (field.tagName && field.tagName.toLowerCase() === 'sc-input') {
                    field.setAttribute('value', fieldValue);
                    if (field.shadowRoot) {
                        const innerInput = field.shadowRoot.querySelector('input');
                        if (innerInput) {
                            innerInput.value = fieldValue;
                            innerInput.dispatchEvent(new Event('input', { bubbles: true }));
                            innerInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                    console.log('Populated sc-input for param "<?php echo $js_param_key; ?>" with value:', fieldValue);
                } else {
                    field.value = fieldValue;
                    console.log('Populated field for param "<?php echo $js_param_key; ?>" with value:', fieldValue);
                }
            }
        }
        <?php endforeach; ?>
    });
    </script>
    <?php
}
add_action('wp_footer', 'sccf_enqueue_custom_fields_script');
