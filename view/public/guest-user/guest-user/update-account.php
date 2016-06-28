

<?php echo $this->messages(); ?>

<?php $form->prepare(); ?>

<?php echo $this->pageTitle($this->translate('Register')); ?>

<?php echo $this->form()->openTag($form); ?>
<div id="page-actions">
    <button><?php echo $this->translate('Register'); ?></button>
</div>
<label ><?php echo $this->registerLabel ?>
</label>
<?php echo $this->formCollection($form, false); ?>
<?php $this->trigger('view.add.form.after'); ?>
<?php echo $this->form()->closeTag(); ?>

<?php $this->trigger('view.add.after'); ?>
