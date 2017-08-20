<?php

$js = "
var guestUserPasswordAgainText = '" . $this->translate('Password again for match') . "';
var guestUserPasswordsMatchText = '" . $this->translate('Passwords match!') . "';
var guestUserPasswordsNoMatchText = '" . $this->translate("Passwords do not match!") . "'; ";

queue_js_string($js);
queue_js_file('guest-user-password');
queue_css_file('skeleton');
$css = "form > div { clear: both; padding-top: 10px;} .two.columns {width: 30%;}";
queue_css_string($css);
$pageTitle = $this->translate('Update Account');
echo head(['bodyclass' => 'update-account', 'title' => $pageTitle]);
?>
<h1><?php echo $pageTitle; ?></h1>
<div id='primary'>
<?php echo flash(); ?>
<?php echo $this->form; ?>
</div>
<?php echo foot(); ?>