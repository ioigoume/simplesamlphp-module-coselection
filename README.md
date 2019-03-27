# coselection
simplesamlphp **1.14** module that enables selection of a CO when the users belongs to multiple ones

In order to enable the plugin follow the steps below:
- Copy/Clone the plugin under the path `./modules`
- Bare in mind that the empty file `default-enable` should be present under the plugin's parent directory. If it is not, create it. - Add the plugin to list of triggered one in the file `./metadata/saml20-idp-hosted.php`
```bash
165             80 => array(
166                 'class' => 'coselection:CoSelection',
167                 //'intro' => 'VO list:', // default to null
168                 'requiredattributes' => array(
169                     'eduPersonUniqueId' => array(
170                         //'mode' => 'radio',
171                     ),
172                 ),
173             ),
```
- The plugin must be triggered before the `attrauthcomanage` one.
