# simplesamlphp-module-coselection
Simplesamlphp module, tested with version **1.14**, that enables the selection of a CO/VO; when the user belongs and is active to more than one

In order to enable the plugin follow the steps below:
- Copy/Clone the plugin under the path `./modules`
- Bare in mind that the empty file `default-enable` should be present under the plugin's parent directory. If it is not, create it.
- Add the plugin into the list of the triggered ones, in the file `./metadata/saml20-idp-hosted.php`

# Example authproc filter configuration
```bash
80 => [
    'class' => 'coselection:CoSelection',
    'intro' => 'VO list:', // default to null
    'requiredattributes' => [
        'eduPersonUniqueId' => [
            //'mode' => 'radio',
        ],
    ],
    // List of SP entity IDs that should be excluded
    //'spBlacklist' => [],
],
```
- The plugin must be triggered before the `attrauthcomanage` one.
