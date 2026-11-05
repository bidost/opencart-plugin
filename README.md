# opencart-extension
  * Blockonomics extension for OpenCart v4 (tested with v4.0.x and v4.1.x)

# Compatibility
  * OpenCart 4.0.0.0 - 4.0.1.x: the extension uses the `route|method` URL format and the `getMethod()` payment method API.
  * OpenCart 4.0.2.0 and later (4.1.x too): the extension uses the `route.method` URL format and the `getMethods()` payment method API.
  * The extension finds the OpenCart version automatically. You do not have to change settings.

# Install 
  * Download latest 'blockonomics.ocmod.zip' file from [releases](https://github.com/blockonomics/opencart3-plugin/releases)
  * Open OpenCart admin page.
  * Navigate to Extenstions->Installer and upload the zip file.
  * Navigate to Extensions->Extenstions and select type "Payments" and install blockonomics extension.
  * Open extension editing, insert your API key and click Save.
  * Copy the Callback URL from the extension settings.
  * On the [Blockonomics Dashboard](https://www.blockonomics.co/dashboard#/store) (Stores), add a store or edit your store. Set the HTTP Callback URL of the store to the Callback URL from the extension settings. Enable the Bitcoin (BTC) payment method on the store.
  * In the extension settings, click Test Setup.
  * Note: Test Setup does not set the Callback URL on Blockonomics. You must set it on the Blockonomics Dashboard (Stores). If you click Generate Secret, the Callback URL changes and you must update it on the store too.

# Make the zip file
  * The name of the zip file must be `blockonomics.ocmod.zip`. OpenCart uses the file name as the extension folder name (`extension/blockonomics/`). Other file names (for example `blockonomics-opencart.ocmod.zip`) do not work.
  * `install.json` must be in the root of the zip file:

```bash
zip -r blockonomics.ocmod.zip admin catalog system install.json
```

# Upgrade from an earlier version
  * Replace the files in `extension/blockonomics/` on the server with the new files. The settings and the `blockonomics_bitcoin_orders` table stay in the database.
  * Do not uninstall the extension in Extensions->Extensions->Payments to upgrade. Uninstall deletes the settings and the `blockonomics_bitcoin_orders` table.
  * Callback URLs made by OpenCart 4.0.0.0 - 4.0.1.x (`...blockonomics|callback...`) continue to work on OpenCart 4.0.2.0 and later.
  * After the upgrade, click Test Setup. Earlier versions used the old `/api/address` and `/api/update_callback` endpoints to set the callback URL automatically. This version uses the Blockonomics Stores API (`/api/v2/stores`), the same as the current Blockonomics WooCommerce plugin. If Test Setup cannot find a store with your Callback URL, add the Callback URL to your store on the Blockonomics Dashboard.

# Blockonomics API calls
  * `POST /api/new_address?match_callback=secret%3D<callback secret>&crypto=BTC` (Test Setup adds `reset=1`)
  * `GET /api/price?currency=<store currency>&crypto=BTC`
  * `GET /api/v2/stores?wallets=true` (Test Setup only)
  * Callback from Blockonomics: `...blockonomics.callback&secret=...&addr=...&status=...&value=...&txid=...[&rbf=1]`. Unconfirmed Replace-By-Fee (`rbf`) payments are not used. After a payment is confirmed (status 2), other callbacks for the same address are ignored.
