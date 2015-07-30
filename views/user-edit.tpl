<? $pageTitle = $entity . ' List – ' . $formType ?>
  
<h1><?= $formType ?> <?= $entity ?></h1>

<? View::form([
  'action' => $formType == 'Add' ? $controller->url('add-submit') : $controller->url('edit-submit', ['id' => $item->id]),
  'class' => 'medium',
  'onsubmit' => "if (document.getElementById('email_field').value == '') {alert('Email is a required field.'); return false; }",
  'data' => $item
]) ?>
  <? View::fieldRow([
    'name' => 'name'
  ]) ?>
  
  <? View::fieldRow([
    'name' => 'email',
    'class' => 'large',
    'note' => 'the password must be reset if you change the email'
  ]) ?>
  
  <h2>Password Reset</h2>
  <? View::fieldRow([
    'name' => 'new_password'
  ]) ?>
  
  <div class="buttons">
    <input type="submit" value="Save <?= $entity ?>">
  </div>
<? View::closeForm() ?>
