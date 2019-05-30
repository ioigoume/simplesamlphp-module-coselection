<?php

use SimpleSAML\Module;

/**
 * Template form for attribute selection.
 *
 * Parameters:
 * - 'srcMetadata': Metadata/configuration for the source.
 * - 'dstMetadata': Metadata/configuration for the destination.
 * - 'yesTarget': Target URL for the yes-button. This URL will receive a POST request.
 * - 'yesData': Parameters which should be included in the yes-request.
 * - 'noTarget': Target URL for the no-button. This URL will receive a GET request.
 * - 'noData': Parameters which should be included in the no-request.
 * - 'attributes': The attributes which are about to be released.
 * - 'sppp': URL to the privacy policy of the destination, or FALSE.
 *
 * @package SimpleSAMLphp
 */
assert('is_array($this->data["srcMetadata"])');
assert('is_array($this->data["dstMetadata"])');
assert('is_string($this->data["yesTarget"])');
assert('is_array($this->data["yesData"])');
assert('is_string($this->data["noTarget"])');
assert('is_array($this->data["noData"])');
assert('is_array($this->data["attributes"])');

// assert('is_array($this->data["hiddenAttributes"])');

assert('is_array($this->data["selectco"])');
assert('$this->data["sppp"] === false || is_string($this->data["sppp"])');

// Parse parameters

if (array_key_exists('name', $this->data['srcMetadata'])) {
  $srcName = $this->data['srcMetadata']['name'];
}
elseif (array_key_exists('OrganizationDisplayName', $this->data['srcMetadata'])) {
  $srcName = $this->data['srcMetadata']['OrganizationDisplayName'];
}
else {
  $srcName = $this->data['srcMetadata']['entityid'];
}

if (is_array($srcName)) {
  $srcName = $this->t($srcName);
}

if (array_key_exists('name', $this->data['dstMetadata'])) {
  $dstName = $this->data['dstMetadata']['name'];
}
elseif (array_key_exists('OrganizationDisplayName', $this->data['dstMetadata'])) {
  $dstName = $this->data['dstMetadata']['OrganizationDisplayName'];
}
else {
  $dstName = $this->data['dstMetadata']['entityid'];
}

if (is_array($dstName)) {
  $dstName = $this->t($dstName);
}

$srcName = htmlspecialchars($srcName);
$dstName = htmlspecialchars($dstName);
$attributes = $this->data['attributes'];
$selectCos = $this->data['selectco'];
$this->data['header'] = $this->t('{coselection:coselection:co_selection_header}');
$this->data['head'] = '<link rel="stylesheet" type="text/css" href="/' . $this->data['baseurlpath'] . 'module.php/coselection/resources/css/style.css" />' . "\n";
$this->includeAtTemplateBase('includes/header.php');
?>
<p>
<?php

  if (array_key_exists('descr_purpose', $this->data['dstMetadata'])) {
    echo '<br>' . $this->t('{coselection:coselection:co_selection_purpose}', [
      'SPNAME' => $dstName,
      'SPDESC' => $this->getTranslation(SimpleSAMLUtilsArrays::arrayize($this->data['dstMetadata']['descr_purpose'], 'en')) ,
    ]);
  }

  echo '<h3 id="attributeheader">' . $this->t('{coselection:coselection:co_selection_cos_header}', [
      'SPNAME' => $dstName,
      'IDPNAME' => $srcName
    ]) . '</h3>';

  if (!empty($this->data['intro'])) {
    echo '<h4 id="intro_header">'.$this->data['intro'].'</h4>';
//  } else {
//    echo $this->t('{coselection:coselection:co_selection_accept}', [
//      'SPNAME' => $dstName,
//      'IDPNAME' => $srcName
//    ]);
  }

//  echo presentCos($selectCos);
  echo "<script type=\"text/javascript\" src=\"" . htmlspecialchars(Module::getModuleURL('coselection/resources/js/jquery-3.3.1.slim.min.js')) . "\"></script>";
  echo "<script type=\"text/javascript\" src=\"" . htmlspecialchars(Module::getModuleURL('coselection/resources/js/attributeselector.js')) . "\"></script>";


?>
</p>
<!--  Form that will be sumbitted on Yes -->
<form style="display: inline; margin: 0px; padding: 0px" action="<?php echo htmlspecialchars($this->data['yesTarget']); ?>">
  <p style="margin: 1em">
    <?php
      foreach($this->data['yesData'] as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
      }
      echo presentCos($selectCos);
      //echo '<input type="hidden" name="coSelection" />';
    ?>
  </p>
  <button type="submit" name="yes" class="btn" id="yesbutton">
      <?php echo htmlspecialchars($this->t('{coselection:coselection:yes}')) ?>
  </button>
</form>

<!--  Form that will be submitted on cancel-->
<form style="display: inline; margin-left: .5em;" action="<?php echo htmlspecialchars($this->data['logoutLink']); ?>" method="get">
  <?php
    foreach($this->data['logoutData'] as $name => $value) {
      echo ('<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />');
    }
  ?>
  <button type="submit" class="btn" name="no" id="nobutton">
      <?php echo htmlspecialchars($this->t('{coselection:coselection:no}')) ?>
  </button>
</form>

<?php

if ($this->data['sppp'] !== false) {
  echo "<p>" . htmlspecialchars($this->t('{coselection:coselection:co_selection_privacy_policy}')) . " ";
  echo "<a target='_blank' href='" . htmlspecialchars($this->data['sppp']) . "'>" . $dstName . "</a>";
  echo "</p>";
}

/**
 * Recursive co array listing function
 *
 * @param array                     $attributes Attributes to be presented
 * @param string                    $nameParent Name of parent element
 *
 * @return string HTML representation of the attributes
 */

function presentCos($selectCos)
{

  $str= '<div>';
  $str.= '<ul style="list-style-type: none">';
  foreach($selectCos as $id => $name) {
      // create the radio buttons
      $str .= '<li><input class="attribute-selection" style="margin-right: 10px" type="radio" value="'.$id.':'.$name.'" name="coSelection">'.$name.'<br></li>';
  } // end foreach
  $str.= '</ul>';
  $str.= '</div>';
  return $str;
}

$this->includeAtTemplateBase('includes/footer.php');
