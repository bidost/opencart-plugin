<?php
$_['heading_title']          = 'Blockonomics';

// Text
$_['text_payment']           = 'Payment';
$_['text_success']           = 'Success: You have modified the Blockonomics payment module!';
$_['text_edit']              = 'Edit Blockonomics';
$_['text_changes']           = 'There are unsaved changes.';
$_['text_blockonomics']            = '<a onclick="window.open(\'https://www.blockonomics.co/\');">Blockonomics</a>';
$_['text_general']           = 'General';
$_['text_advanced']      = 'Blockonomics';
$_['text_all_geo_zones']     = 'All Geo Zones';
$_['text_yes']               = 'Yes';
$_['text_no']                = 'No';
$_['text_popup_blocked']     = 'Pop-up Blocked';
$_['text_popup']             = 'It appears your browser is blocking Blockonomics&apos;s pop-up. Click below to continue.';
$_['text_are_you_sure']      = 'Are you sure?';
$_['text_gen_secret']        = 'Generate Secret';
$_['text_test_setup']        = 'Test Setup';
$_['text_store_name']        = 'Blockonomics store';
$_['text_default_title']     = 'Bitcoin';
$_['text_confirmation_recommended'] = '2 (Recommended)';

// Tab
$_['tab_settings']           = 'Settings';

// Button
$_['button_disconnect']      = 'Disconnect';
$_['button_send']            = 'Send';
$_['button_continue']        = 'Continue';

// Entry
$_['entry_api_access']       = 'API Access';
$_['entry_risk_speed']       = 'Risk/Speed';
$_['entry_send_buyer_info']  = 'Send Buyer Information';
$_['entry_geo_zone']         = 'Geo Zone';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_callback_secret']  = 'Callback Secret';
$_['entry_test_setup']       = 'Test Setup';
$_['entry_complete_status']     = 'Order Status';
$_['entry_callback_url']     = 'Callback URL';
$_['entry_api_key']          = 'API Key';
$_['entry_underpayment_slack'] = 'Underpayment Slack %';
$_['entry_extra_margin']     = 'Extra Currency Rate Margin %';
$_['entry_network_confirmation'] = 'Network Confirmations';
$_['entry_title']            = 'Title';
$_['entry_description']      = 'Description';
$_['entry_debug']            = 'Debug Logging';
$_['entry_name']             = 'Name';
$_['entry_email_address']    = 'Email Address';
$_['entry_subject']          = 'Subject';
$_['entry_description']      = 'Description';
$_['entry_send_logs']        = 'Send Blockonomics Log';
$_['entry_send_server_info'] = 'Send Server Information';

// Help
$_['help_underpayment_slack'] = 'Allow payments that are off by a small percentage (0-20%). A payment that is less than the expected amount minus this percentage cancels the order.';
$_['help_extra_margin']       = 'Increase live fiat to BTC rate by small percent (0-4%). The customer pays this percentage more BTC.';
$_['help_network_confirmation'] = 'Network confirmations required for the payment to complete. With 0, the order is complete when the transaction is seen on the network. Unconfirmed Replace-By-Fee transactions are never accepted.';
$_['help_title']              = 'Payment method name that the customer sees during checkout. Leave empty to use the default name.';
$_['help_description']        = 'Text that the customer sees above the confirm button during checkout. Leave empty to show no text.';
$_['help_callback_url']       = 'On the Blockonomics Dashboard (Stores), set the HTTP Callback URL of your store to this URL.';

// Warning
$_['warning_permission']        = 'Warning: You do not have permission to modify the Blockonomics payment module.';
$_['warning_unable_to_connect'] = 'Warning: Unable to connect to Blockonomics. Check the log for more information.';
$_['warning_disconnected']      = 'Warning: You are no longer connected to Blockonomics. <a href="%s" class="alert-link">Click here to reconnect.</a>';

// Error
$_['error_permission']                 = 'Warning: You do not have permission to modify the Blockonomics payment module!';
$_['error_callback_url']                    = '`Notification URL` needs to be a valid URL';
$_['error_api_key']                    = '`Blocknomics API Key` should not be empty';
$_['error_extra_margin']               = '`Extra Currency Rate Margin %` must be a number from 0 to 4';
$_['error_underpayment_slack']         = '`Underpayment Slack %` must be a number from 0 to 20';
$_['error_network_confirmation']       = '`Network Confirmations` must be 0, 1 or 2';
$_['error_callback_test_setup']                 = 'There is a problem in your callback url';

// Test Setup
$_['congrats'] = 'Congrats ! Setup is all done';
$_['error_blockedHttps'] = 'Your server is blocking outgoing HTTPS calls';
$_['error_incorrectApi'] = 'API Key is incorrect';
$_['error_noSecret'] = 'The callback secret is empty. Click Generate Secret, save the settings and add the new Callback URL to your Blockonomics store.';
$_['error_invalidResponse'] = 'Invalid response was received from Blockonomics. Please retry.';
$_['error_noStore'] = 'Please add a <a href="https://www.blockonomics.co/dashboard#/store" target="_blank"><i>Store</i></a> on the Blockonomics Dashboard. Use the Callback URL shown below as the HTTP Callback URL of the store.';
$_['error_noMatchingStore'] = 'No Blockonomics store has the Callback URL shown below. Please add a <a href="https://www.blockonomics.co/dashboard#/store" target="_blank">new store</a> (or edit your store) and set its HTTP Callback URL to the Callback URL shown below. Then save the settings and click Test Setup again.';
$_['error_noBtcWallet'] = 'Please enable the Bitcoin (BTC) payment method on your <a href="https://www.blockonomics.co/dashboard#/store" target="_blank"><i>Blockonomics store</i></a>.';

// Log
$_['log_error_install']          = 'The Blockonomics payment extension was not installed correctly or the files are corrupt. Please reinstall the extension. If this message persists after a reinstall, contact support@blockonomics.co with this message.';

