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
  * Open extension editing, insert your API key and click Test Setup

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
