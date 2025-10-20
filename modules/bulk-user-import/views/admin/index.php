<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var $this yii\web\View */
/** @var $model modules\bulkUserImport\models\UploadForm */

$this->title = Yii::t('BulkUserImportModule.base', 'Bulk User Import');
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <strong><?= Html::encode($this->title) ?></strong>
    </div>
    <div class="panel-body">
        <p><?= Yii::t('BulkUserImportModule.base', 'Upload an XLSX file containing the users to import. The header row must include the columns: <code>name</code>, <code>last name</code>, <code>email</code>, <code>groups</code>. Additional columns are ignored.'); ?></p>
        <p><?= Yii::t('BulkUserImportModule.base', 'Group entries can use IDs or names. Separate multiple groups with semicolons.'); ?></p>
        <p>
            <?= Yii::t('BulkUserImportModule.base', 'Example file: {link}', [
                'link' => Html::a('bulk_user_import_example.xlsx', ['/bulk-user-import/admin/download-sample']),
            ]) ?>
        </p>

        <?php $form = ActiveForm::begin([
            'options' => ['enctype' => 'multipart/form-data'],
        ]); ?>

        <?= $form->field($model, 'file')->fileInput(['accept' => '.xlsx']) ?>

        <div class="form-group">
            <?= Html::submitButton(Yii::t('BulkUserImportModule.base', 'Upload and preview'), ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
